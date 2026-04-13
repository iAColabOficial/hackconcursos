<?php
/**
 * Controller: Simulados Action
 * Gerenciamento de geração e respostas de simulados
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/GeminiService.php';
exigirLogin('../login.php');
header('Content-Type: application/json');

$uid = (int)$_SESSION['usuario_id'];
$action = $_GET['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'gerar') {
    set_time_limit(300); // Dar 5 minutos para o processo todo
    $body = json_decode(file_get_contents('php://input'), true);
    $titulo     = sanitize($body['titulo'] ?? 'Simulado Tático');
    $quantidade = (int)($body['quantidade'] ?? 10);
    $discIds    = $body['disciplinas'] ?? []; // IDs das disciplinas selecionadas

    if (empty($discIds)) {
        echo json_encode(['ok' => false, 'msg' => 'Selecione pelo menos uma disciplina.']);
        exit;
    }

    try {
        $db = getDB();
        
        $banca       = sanitize($body['banca'] ?? 'FGV');
        $dificuldade = sanitize($body['dificuldade'] ?? 'media');

        // 1. Validar cargo ativo
        $cargoQ = $db->prepare("SELECT id FROM cargos WHERE edital_id IN (SELECT id FROM editais WHERE usuario_id = ?) AND selecionado = 1 LIMIT 1");
        $cargoQ->execute([$uid]);
        $cargoId = $cargoQ->fetchColumn();
        
        if (!$cargoId) {
            echo json_encode(['ok' => false, 'msg' => 'Nenhum cargo selecionado encontrado.']);
            exit;
        }

        // 2. Criar cabeçalho do simulado
        $insSim = $db->prepare("INSERT INTO simulados (usuario_id, cargo_id, titulo, total_questoes) VALUES (?, ?, ?, ?)");
        $insSim->execute([$uid, $cargoId, $titulo, $quantidade]);
        $simuladoId = (int)$db->lastInsertId();

        // 3. Gerar questões via Gemini
        $gemini = new GeminiService();
        $questoesPorDisciplina = ceil($quantidade / count($discIds));
        $totalInseridas = 0;

        foreach ($discIds as $dId) {
            if ($totalInseridas >= $quantidade) break;
            
            // Buscar nome da disciplina e tópicos para enriquecer o prompt
            $dq = $db->prepare("SELECT nome FROM disciplinas WHERE id = ?");
            $dq->execute([$dId]);
            $nomeDisc = $dq->fetchColumn();
            
            $tq = $db->prepare("SELECT nome FROM topicos_edital WHERE disciplina_id = ? LIMIT 10");
            $tq->execute([$dId]);
            $topicos = $tq->fetchAll(PDO::FETCH_COLUMN);

            $numParaEsta = min($questoesPorDisciplina, $quantidade - $totalInseridas);
            
            try {
                // Passando banca e dificuldade para a IA
                $questoesIA = $gemini->gerarQuestoes($nomeDisc, $topicos, $numParaEsta, $banca, $dificuldade);
                
                // DEBUG: Salvar resposta da IA para conferência
                file_put_contents(__DIR__ . '/../debug_simulado.txt', "Disc: $nomeDisc\n" . print_r($questoesIA, true), FILE_APPEND);
                
                // VERIFICAÇÃO DE CONEXÃO (Anti-Timeout)
                try {
                    $db->query("SELECT 1");
                } catch (Exception $e) {
                    $db = getDB(); // Tenta pegar uma nova conexão (precisamos limpar o static no getDB)
                }

                $insQ = $db->prepare("
                    INSERT INTO questoes (simulado_id, disciplina_id, enunciado, alternativa_a, alternativa_b, alternativa_c, alternativa_d, alternativa_e, gabarito, explicacao)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");

                foreach ($questoesIA as $qData) {
                    if ($totalInseridas >= $quantidade) break;
                    
                    $saved = $insQ->execute([
                        $simuladoId,
                        $dId,
                        $qData['enunciado'],
                        $qData['alternativa_a'],
                        $qData['alternativa_b'],
                        $qData['alternativa_c'],
                        $qData['alternativa_d'],
                        $qData['alternativa_e'] ?? null,
                        strtoupper($qData['gabarito'] ?? ''),
                        $qData['explicacao'] ?? null
                    ]);
                    
                    if ($saved) {
                        $totalInseridas++;
                    } else {
                        // Log de erro de inserção se falhar
                        $err = $insQ->errorInfo();
                        file_put_contents(__DIR__ . '/../debug_simulado.txt', "Erro INSERT: " . print_r($err, true), FILE_APPEND);
                    }
                }
            } catch (\Exception $e) {
                file_put_contents(__DIR__ . '/../debug_simulado.txt', "Erro Loop: " . $e->getMessage(), FILE_APPEND);
                continue;
            }
        }

        // 4. Atualizar total real inserido caso tenha dado erro em algum
        $db->prepare("UPDATE simulados SET total_questoes = ? WHERE id = ?")->execute([$totalInseridas, $simuladoId]);

        if ($totalInseridas === 0) {
            // Deletar simulado vazio
            $db->prepare("DELETE FROM simulados WHERE id = ?")->execute([$simuladoId]);
            echo json_encode(['ok' => false, 'msg' => 'Falha ao gerar questões com a IA. Tente novamente em alguns segundos.']);
            exit;
        }

        echo json_encode(['ok' => true, 'simulado_id' => $simuladoId]);

    } catch (PDOException $e) {
        echo json_encode(['ok' => false, 'msg' => 'Erro interno de banco de dados.']);
    }
}

// Ação de responder uma questão
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'responder') {
    $body = json_decode(file_get_contents('php://input'), true);
    $simuladoId = (int)($body['simulado_id'] ?? 0);
    $questaoId  = (int)($body['questao_id'] ?? 0);
    $resposta   = strtoupper(trim($body['resposta'] ?? ''));

    if (!$simuladoId || !$questaoId || empty($resposta)) {
        echo json_encode(['ok' => false, 'msg' => 'Dados incompletos.']);
        exit;
    }

    try {
        $db = getDB();
        
        // Buscar gabarito
        $qQ = $db->prepare("SELECT gabarito FROM questoes WHERE id = ? AND simulado_id = ?");
        $qQ->execute([$questaoId, $simuladoId]);
        $gabarito = $qQ->fetchColumn();

        if (!$gabarito) {
            echo json_encode(['ok' => false, 'msg' => 'Questão não encontrada.']);
            exit;
        }

        $correta = ($resposta === $gabarito) ? 1 : 0;

        // Salvar ou atualizar resposta
        $insRes = $db->prepare("
            INSERT INTO respostas_usuario (simulado_id, questao_id, usuario_id, resposta, correta)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE resposta = VALUES(resposta), correta = VALUES(correta), respondida_em = CURRENT_TIMESTAMP
        ");
        $insRes->execute([$simuladoId, $questaoId, $uid, $resposta, $correta]);

        echo json_encode(['ok' => true, 'correta' => (bool)$correta, 'gabarito' => $gabarito]);

    } catch (PDOException $e) {
        echo json_encode(['ok' => false, 'msg' => 'Erro ao salvar resposta.']);
    }
}

// Ação de finalizar simulado
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'finalizar') {
    $body = json_decode(file_get_contents('php://input'), true);
    $simuladoId = (int)($body['simulado_id'] ?? 0);
    $tempoMin   = (int)($body['tempo_minutos'] ?? 0);

    try {
        $db = getDB();
        
        // Contabilizar acertos e erros
        $statsQ = $db->prepare("
            SELECT 
                SUM(CASE WHEN correta = 1 THEN 1 ELSE 0 END) as acertos,
                SUM(CASE WHEN correta = 0 THEN 1 ELSE 0 END) as erros
            FROM respostas_usuario 
            WHERE simulado_id = ? AND usuario_id = ?
        ");
        $statsQ->execute([$simuladoId, $uid]);
        $stats = $statsQ->fetch();

        $acertos = (int)($stats['acertos'] ?? 0);
        $erros   = (int)($stats['erros'] ?? 0);

        // Atualizar simulado
        $upd = $db->prepare("
            UPDATE simulados 
            SET acertos = ?, erros = ?, tempo_minutos = ?, concluido = 1 
            WHERE id = ? AND usuario_id = ?
        ");
        $upd->execute([$acertos, $erros, $tempoMin, $simuladoId, $uid]);

        echo json_encode(['ok' => true]);

    } catch (PDOException $e) {
        echo json_encode(['ok' => false, 'msg' => 'Erro ao finalizar simulado.']);
    }
}
