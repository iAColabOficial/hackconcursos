<?php
/**
 * Controller: Simulados Action
 * Gerenciamento de geração e respostas de simulados
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/GeminiService.php';
require_once __DIR__ . '/../classes/TokenManager.php';
exigirLogin('../login.php');
header('Content-Type: application/json');

$uid = (int)$_SESSION['usuario_id'];
$action = $_GET['action'] ?? '';

// Verificar CSRF em todas as requisições POST (FIX M3)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    $csrfToken = $headers['X-CSRF-Token'] ?? $headers['x-csrf-token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!verificarCSRFToken($csrfToken)) {
        echo json_encode(['ok' => false, 'msg' => 'Acesso negado: Token CSRF inválido ou expirado.']);
        exit;
    }
}

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
        
        // 1. Verificar tokens/plano via TokenManager (FIX A9 + Token integration)
        $check = TokenManager::verificar($uid, COST_IA_SIMULADO);
        if (!$check['pode']) {
            echo json_encode(['ok' => false, 'msg' => $check['msg'], 'paywall' => true]);
            exit;
        }

        // Verificação de limite para plano gratuito (using fresh DB value from $check['plano'])
        if ($check['plano'] === 'free') {
            $countQ = $db->prepare("SELECT COUNT(*) FROM simulados WHERE usuario_id = ?");
            $countQ->execute([$uid]);
            $count = $countQ->fetchColumn();
            if ($count >= 5) {
                echo json_encode(['ok' => false, 'msg' => 'Você atingiu o limite de 5 simulados do plano gratuito. Faça upgrade para ter acesso ilimitado.']);
                exit;
            }
        }

        $banca       = sanitize($body['banca'] ?? 'FGV');
        $dificuldade = sanitize($body['dificuldade'] ?? 'media');

        // 2. Validar cargo ativo
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
                $questoesIA = $gemini->gerarQuestoes($nomeDisc, $topicos, $numParaEsta, $banca, $dificuldade);

                // VERIFICAÇÃO DE CONEXÃO (Anti-Timeout)
                try {
                    $db->query("SELECT 1");
                } catch (Exception $e) {
                    $db = getDB();
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
                        error_log('simulado_action INSERT falhou para questão de ' . $nomeDisc);
                    }
                }
            } catch (\Exception $e) {
                error_log('simulado_action gerarQuestoes: ' . $e->getMessage());
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

        // 5. Debitar tokens (só depois de ter resultado com sucesso - FIX A3)
        TokenManager::consumirOuFalhar($uid, COST_IA_SIMULADO, 'Geração de Simulado IA');

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
        
        // Buscar gabarito e verificar ownership do simulado para evitar IDOR (A1)
        $qQ = $db->prepare("
            SELECT q.gabarito 
            FROM questoes q
            JOIN simulados s ON s.id = q.simulado_id
            WHERE q.id = ? AND q.simulado_id = ? AND s.usuario_id = ?
        ");
        $qQ->execute([$questaoId, $simuladoId, $uid]);
        $gabarito = $qQ->fetchColumn();

        if (!$gabarito) {
            echo json_encode(['ok' => false, 'msg' => 'Questão não encontrada.']);
            exit;
        }

        $correta = ($resposta === $gabarito) ? 1 : 0;

        $posQ = $db->prepare("SELECT COUNT(*) FROM respostas_usuario WHERE simulado_id = ? AND usuario_id = ? AND questao_id != ?");
        $posQ->execute([$simuladoId, $uid, $questaoId]);
        $posicao_questao = (int)$posQ->fetchColumn() + 1;

        // Salvar ou atualizar resposta
        $insRes = $db->prepare("
            INSERT INTO respostas_usuario (simulado_id, questao_id, usuario_id, resposta, correta, posicao_questao)
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE resposta = VALUES(resposta), correta = VALUES(correta), respondida_em = CURRENT_TIMESTAMP
        ");
        $insRes->execute([$simuladoId, $questaoId, $uid, $resposta, $correta, $posicao_questao]);

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
