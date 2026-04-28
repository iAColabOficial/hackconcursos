<?php

class EngineAdaptacao {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    /**
     * Chama o motor Python para obter a base estratégica
     */
    public function getBaseEstrategica($targetId, $targetType = 'cargo') {
        $pythonPath = "python"; 
        $scriptPath = dirname(__DIR__) . "/python/strategy_engine.py";
        
        $command = escapeshellcmd("$pythonPath \"$scriptPath\" $targetId $targetType");
        $output = shell_exec($command);
        
        return json_decode($output, true);
    }

    /**
     * Adapta a base ao perfil do usuário e gera missões
     */
    public function gerarPlanoMissions($usuarioId, $targetId, $targetType = 'cargo') {
        // 1. Obter perfil do usuário
        $stmt = $this->db->prepare("SELECT horas_dia, dias_semana, nivel_geral FROM perfis_usuario WHERE usuario_id = ?");
        $stmt->execute([$usuarioId]);
        $perfil = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$perfil) {
            $perfil = ['horas_dia' => 2, 'dias_semana' => '1,2,3,4,5', 'nivel_geral' => 'iniciante'];
        }

        // 2. Obter base estratégica do Python
        $base = $this->getBaseEstrategica($targetId, $targetType);
        if (isset($base['error'])) return $base;

        // 3. Resolver ID do Cargo Local
        $localCargoId = null;
        if ($targetType == 'biblioteca') {
            // Buscar o cargo selecionado do edital mais recente importado pelo usuário
            $stmtC = $this->db->prepare("
                SELECT c.id FROM cargos c
                JOIN editais e ON e.id = c.edital_id
                WHERE e.usuario_id = ? AND c.selecionado = 1
                ORDER BY e.criado_em DESC LIMIT 1
            ");
            $stmtC->execute([$usuarioId]);
            $localCargoId = $stmtC->fetchColumn();
        } else {
            $localCargoId = $targetId;
        }

        if (!$localCargoId) {
            return ['error' => 'Cargo local não encontrado. Por favor, selecione um concurso primeiro.'];
        }

        // 4. Criar o Plano de Estudo no banco se não existir
        $stmtCheck = $this->db->prepare("SELECT id FROM planos_estudo WHERE usuario_id = ? AND ativo = 1 LIMIT 1");
        $stmtCheck->execute([$usuarioId]);
        $planoExistente = $stmtCheck->fetch();

        if ($planoExistente) {
            $planoId = $planoExistente['id'];
            // Limpar missões futuras não concluídas para regerar
            $this->db->prepare("DELETE FROM tarefas_estudo WHERE plano_id = ? AND concluida = 0")->execute([$planoId]);
        } else {
            $stmt = $this->db->prepare("INSERT INTO planos_estudo (usuario_id, cargo_id, data_inicio, ativo) VALUES (?, ?, CURDATE(), 1)");
            $stmt->execute([$usuarioId, $localCargoId]);
            $planoId = $this->db->lastInsertId();
        }

        // 4. Distribuir no tempo
        $diasDisponiveis = explode(',', $perfil['dias_semana']);
        $horasPorDia = $perfil['horas_dia'];
        $minutosPorDia = $horasPorDia * 60;
        
        $currentDate = new DateTime();
        
        // Flatten topicos ordenados
        $topicosOrdenados = [];
        foreach ($base as $disc) {
            foreach ($disc['topicos'] as $topico) {
                $topicosOrdenados[] = [
                    'disciplina_id' => $disc['disciplina_id'],
                    'disciplina_nome' => $disc['nome'],
                    'topico_id' => $topico['id'],
                    'topico_nome' => $topico['nome'],
                    'prioridade' => $topico['prioridade_final']
                ];
            }
        }

        // Ordenar globalmente por prioridade para as missões iniciais
        usort($topicosOrdenados, function($a, $b) {
            return $b['prioridade'] <=> $a['prioridade'];
        });

        // 5. Gerar Tarefas (Missões)
        $topicoIndex = 0;
        $maxDias = 90; 

        for ($d = 0; $d < $maxDias; $d++) {
            $diaSemana = $currentDate->format('N');
            
            if (in_array($diaSemana, $diasDisponiveis)) {
                $minutosRestantes = $minutosPorDia;
                
                while ($minutosRestantes >= 30 && isset($topicosOrdenados[$topicoIndex])) {
                    $topico = $topicosOrdenados[$topicoIndex];
                    $duracao = ($perfil['nivel_geral'] == 'iniciante') ? 90 : 60;
                    if ($duracao > $minutosRestantes) $duracao = $minutosRestantes;

                    $stmt = $this->db->prepare("INSERT INTO tarefas_estudo 
                        (plano_id, disciplina_id, topico_id, titulo, tipo, data_prevista, duracao_minutos) 
                        VALUES (?, ?, ?, ?, 'estudo', ?, ?)");
                    
                    $titulo = "Missão: " . $topico['topico_nome'];
                    $stmt->execute([
                        $planoId, 
                        $topico['disciplina_id'], 
                        $topico['topico_id'], 
                        $titulo, 
                        $currentDate->format('Y-m-d'),
                        $duracao
                    ]);

                    $minutosRestantes -= $duracao;
                    $topicoIndex++;
                }
            }
            
            $currentDate->modify('+1 day');
            if ($topicoIndex >= count($topicosOrdenados)) break;
        }

        return ['status' => 'success', 'plano_id' => $planoId, 'total_missoes' => $topicoIndex];
    }
}
