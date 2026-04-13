<?php
/**
 * Controller AJAX/POST: Upload e processamento de edital
 * Recebe o PDF → extrai texto → chama Gemini → salva no banco
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/PDFParser.php';
require_once __DIR__ . '/../classes/GeminiService.php';
exigirLogin('../login.php');
header('Content-Type: application/json');

$uid = (int)$_SESSION['usuario_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'msg' => 'Método inválido.']); exit;
}

// ---- Validar arquivo ----
if (empty($_FILES['pdf_file']) || $_FILES['pdf_file']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['ok' => false, 'msg' => 'Nenhum arquivo enviado ou erro no upload.']); exit;
}

$file    = $_FILES['pdf_file'];
$tmpPath = $file['tmp_name'];
$origName= basename($file['name']);
$ext     = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

if ($ext !== 'pdf') {
    echo json_encode(['ok' => false, 'msg' => 'Apenas arquivos PDF são aceitos.']); exit;
}

$tamanhoMB = $file['size'] / 1048576;
if ($tamanhoMB > UPLOAD_MAX_MB) {
    echo json_encode(['ok' => false, 'msg' => "O arquivo excede o limite de " . UPLOAD_MAX_MB . " MB."]); exit;
}

if (!PDFParser::isPDF($tmpPath)) {
    echo json_encode(['ok' => false, 'msg' => 'O arquivo não parece ser um PDF válido.']); exit;
}

// ---- Mover para uploads/editais ----
$nomeArquivo = 'edital_' . $uid . '_' . time() . '.pdf';
$destino     = UPLOAD_EDITAIS . $nomeArquivo;

if (!is_dir(UPLOAD_EDITAIS)) mkdir(UPLOAD_EDITAIS, 0755, true);

if (!move_uploaded_file($tmpPath, $destino)) {
    echo json_encode(['ok' => false, 'msg' => 'Erro ao salvar o arquivo. Verifique permissões da pasta uploads/.']); exit;
}

// ---- Criar registro inicial no banco ----
$db = getDB();
$ins = $db->prepare(
    "INSERT INTO editais (usuario_id, nome_concurso, arquivo_pdf, status_processamento) VALUES (?,?,?,?)"
);
$ins->execute([$uid, $origName, $nomeArquivo, 'processando']);
$editalId = (int)$db->lastInsertId();

// ---- Extrair texto do PDF ----
try {
    $texto = PDFParser::extrairTexto($destino);
} catch (\Exception $e) {
    $db->prepare("UPDATE editais SET status_processamento='erro' WHERE id=?")->execute([$editalId]);
    echo json_encode(['ok' => false, 'msg' => 'Falha ao ler o PDF: ' . $e->getMessage()]); exit;
}

if (strlen(trim($texto)) < 200) {
    $db->prepare("UPDATE editais SET status_processamento='erro' WHERE id=?")->execute([$editalId]);
    echo json_encode(['ok' => false, 'msg' => 'O PDF parece estar vazio ou protegido contra cópia. Tente um PDF diferente.']); exit;
}

// ---- Chamar Gemini para estruturar o edital ----
try {
    $gemini = new GeminiService();
    $dados  = $gemini->parseEdital($texto);
} catch (\Exception $e) {
    // Se a API falhar, salva o texto puro e deixa o usuário configurar manualmente
    $db->prepare("UPDATE editais SET conteudo_texto=?, status_processamento='erro', nome_concurso=? WHERE id=?")
       ->execute([mb_substr($texto, 0, 65000), $origName, $editalId]);
    echo json_encode(['ok' => false, 'msg' => 'Edital salvo, mas houve erro na análise automática: ' . $e->getMessage(), 'edital_id' => $editalId]);
    exit;
}

// ---- Salvar dados estruturados no banco ----
try {
    $db->beginTransaction();

    // Atualizar edital
    $nomeConcurso = $dados['concurso'] ?? $origName;
    $dataProva    = !empty($dados['data_prova']) && $dados['data_prova'] !== 'null' ? $dados['data_prova'] : null;

    $db->prepare(
        "UPDATE editais SET nome_concurso=?, banca=?, orgao=?, data_prova=?, conteudo_texto=?, status_processamento='concluido' WHERE id=?"
    )->execute([
        $nomeConcurso,
        $dados['banca'] ?? null,
        $dados['orgao'] ?? null,
        $dataProva,
        mb_substr($texto, 0, 65000),
        $editalId
    ]);

    // Salvar cargo único (fictício) para agrupar as matérias
    $stCargo = $db->prepare("INSERT INTO cargos (edital_id, nome, selecionado) VALUES (?, ?, ?)");
    $stCargo->execute([$editalId, 'Plano de Estudos', 1]);
    $cargoId = (int)$db->lastInsertId();

    // Salvar disciplinas extraídas pela IA
    $disciplinas = $dados['disciplinas'] ?? [];
    
    if (empty($disciplinas)) {
        $db->rollBack();
        $db->prepare("UPDATE editais SET status_processamento='erro' WHERE id=?")->execute([$editalId]);
        echo json_encode(['ok' => false, 'msg' => 'A IA não conseguiu identificar as matérias neste edital.']); 
        exit;
    }

    foreach ($disciplinas as $disc) {
        $stDisc = $db->prepare(
            "INSERT INTO disciplinas (cargo_id, nome, peso) VALUES (?,?,?)"
        );
        $stDisc->execute([
            $cargoId,
            $disc['nome'] ?? 'Disciplina',
            (float)($disc['peso'] ?? 1.0),
        ]);
        $discId = (int)$db->lastInsertId();

        // Salvar tópicos
        $topicos = $disc['topicos'] ?? [];
        $stTop = $db->prepare("INSERT INTO topicos_edital (disciplina_id, nome) VALUES (?,?)");
        foreach ($topicos as $top) {
            if (!empty(trim($top))) {
                $stTop->execute([$discId, trim($top)]);
            }
        }
    }

    $db->commit();

    echo json_encode([
        'ok'        => true,
        'edital_id' => $editalId,
        'concurso'  => $nomeConcurso,
        'materias'  => count($disciplinas),
        'redirect'  => APP_URL . '/aluno/upload_edital.php?step=diagnostico&edital=' . $editalId . '&cargo=' . $cargoId,
    ]);

} catch (\Exception $e) {
    $db->rollBack();
    echo json_encode(['ok' => false, 'msg' => 'Erro ao salvar dados: ' . $e->getMessage()]);
}
