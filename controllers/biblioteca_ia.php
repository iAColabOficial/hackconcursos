<?php
require_once __DIR__ . '/../config/config.php';
exigirAdmin();

$db = getDB();

// Ação: Listar Disciplinas (GET)
if (isset($_GET['get_disciplinas'])) {
    $id = (int)$_GET['id'];
    $disciplinas = $db->prepare("SELECT * FROM biblioteca_disciplinas WHERE biblioteca_edital_id = ?");
    $disciplinas->execute([$id]);
    echo json_encode(['success' => true, 'disciplinas' => $disciplinas->fetchAll()]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)$_POST['id'];
    
    // Buscar concurso
    $stmt = $db->prepare("SELECT * FROM biblioteca_editais WHERE id = ?");
    $stmt->execute([$id]);
    $concurso = $stmt->fetch();

    if (!$concurso) {
        echo json_encode(['success' => false, 'error' => 'Concurso não encontrado.']);
        exit;
    }

    // Por enquanto, usaremos um texto fictício ou tentaremos ler o PDF se ele existir
    // Em produção, aqui leríamos o conteúdo do arquivo_edital
    $texto_base = "Conteúdo programático: Direito Administrativo, Direito Constitucional, Português, Raciocínio Lógico.";

    $nome = escapeshellarg($concurso['nome_concurso']);
    $texto = escapeshellarg($texto_base);

    // Chamar script Python
    $python_path = "python"; // Ou caminho completo se necessário
    $script_path = BASE_PATH . "/python/process_edital.py";
    
    $command = "$python_path \"$script_path\" $nome $texto 2>&1";
    $output = shell_exec($command);
    
    $result = json_decode($output, true);

    if (isset($result['disciplinas'])) {
        // Salvar disciplinas na biblioteca
        $db->prepare("DELETE FROM biblioteca_disciplinas WHERE biblioteca_edital_id = ?")->execute([$id]);
        
        $ins = $db->prepare("INSERT INTO biblioteca_disciplinas (biblioteca_edital_id, nome, peso_padrao, importancia_ia) VALUES (?, ?, ?, ?)");
        
        foreach ($result['disciplinas'] as $d) {
            $ins->execute([$id, $d['nome'], $d['peso'], $d['importancia']]);
        }
        
        echo json_encode(['success' => true, 'message' => 'Análise concluída com sucesso!']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Falha na IA: ' . ($result['error'] ?? 'Erro desconhecido'), 'output' => $output]);
    }
}
