<?php

class DashboardData {
    private $db;
    private $usuarioId;

    public function __construct($db, $usuarioId) {
        $this->db = $db;
        $this->usuarioId = $usuarioId;
    }

    public function getMetrics() {
        $metrics = [];

        // 1. Perfil, Streak e Tokens
        $stmt = $this->db->prepare("SELECT pu.*, u.token_saldo, u.plano FROM perfis_usuario pu JOIN usuarios u ON pu.usuario_id = u.id WHERE pu.usuario_id = ?");
        $stmt->execute([$this->usuarioId]);
        $perfil = $stmt->fetch(PDO::FETCH_ASSOC);

        $metrics['streak'] = $perfil['sequencia_dias'] ?? 0;
        $metrics['tokens'] = $perfil['token_saldo'] ?? 0;
        $metrics['plano'] = $perfil['plano'] ?? 'free';
        
        // Nível do usuário
        $metrics['nivel_slug'] = $perfil['nivel_geral'] ?? 'iniciante';
        $metrics['nivel_label'] = $this->getNivelLabel($metrics['nivel_slug'], $metrics['streak']);

        // 2. Cobertura do Edital
        $stmt = $this->db->prepare("SELECT AVG(percentual_concluido) FROM progresso_usuario WHERE usuario_id = ?");
        $stmt->execute([$this->usuarioId]);
        $metrics['cobertura'] = round($stmt->fetchColumn() ?: 0, 1);

        // 3. Taxa de Acerto e Volume
        $stmt = $this->db->prepare("SELECT SUM(correta) as acertos, COUNT(id) as total FROM respostas_usuario WHERE usuario_id = ?");
        $stmt->execute([$this->usuarioId]);
        $respostas = $stmt->fetch();
        $metrics['total_questoes'] = $respostas['total'] ?: 0;
        $metrics['taxa_acerto'] = $respostas['total'] > 0 ? round(($respostas['acertos'] / $respostas['total']) * 100, 1) : 0;

        // 3b. Tempo de Estudo Real (Baseado em missões concluídas)
        $stmt = $this->db->prepare("
            SELECT SUM(duracao_minutos) 
            FROM tarefas_estudo t
            JOIN planos_estudo p ON t.plano_id = p.id
            WHERE p.usuario_id = ? AND t.concluida = 1
        ");
        $stmt->execute([$this->usuarioId]);
        $minutos = $stmt->fetchColumn() ?: 0;
        $metrics['tempo_total_min'] = $minutos;
        $metrics['tempo_formatado'] = floor($minutos / 60) . "h " . ($minutos % 60) . "m";

        // 4. Disciplina mais forte e fraca
        $stmt = $this->db->prepare("
            SELECT d.nome, (SUM(r.correta) / COUNT(r.id)) * 100 as taxa
            FROM respostas_usuario r
            JOIN questoes q ON r.questao_id = q.id
            JOIN disciplinas d ON q.disciplina_id = d.id
            WHERE r.usuario_id = ?
            GROUP BY d.id
            ORDER BY taxa DESC
        ");
        $stmt->execute([$this->usuarioId]);
        $ranking = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $metrics['forte'] = !empty($ranking) ? $ranking[0] : ['nome' => '---', 'taxa' => 0];
        $metrics['fraca'] = count($ranking) > 1 ? end($ranking) : ['nome' => '---', 'taxa' => 0];

        // 5. Risco de Reprovação (Lógica: baixa cobertura + baixa taxa = alto risco)
        $metrics['risco'] = $this->calculateRisco($metrics['cobertura'], $metrics['taxa_acerto']);

        // 6. Missão Atual (Inteligência: Score = Peso × (1 + TaxaErro) × (1 + DiasDescanso/7))
        $stmt = $this->db->prepare("
            SELECT 
                t.*, 
                d.nome as disciplina_nome,
                (
                    IFNULL(d.peso, 1.0) * 
                    (1 + IFNULL((SELECT 1 - (SUM(r.correta)/COUNT(r.id)) FROM respostas_usuario r JOIN questoes q ON r.questao_id = q.id WHERE q.disciplina_id = d.id AND r.usuario_id = p.usuario_id), 1.0)) * 
                    (1 + IFNULL(DATEDIFF(CURRENT_DATE, (SELECT MAX(t2.concluida_em) FROM tarefas_estudo t2 WHERE t2.disciplina_id = d.id AND t2.plano_id = p.id AND t2.concluida = 1)), 7) / 7)
                ) as hack_score
            FROM tarefas_estudo t 
            JOIN disciplinas d ON t.disciplina_id = d.id
            JOIN planos_estudo p ON t.plano_id = p.id
            WHERE p.usuario_id = ? AND t.concluida = 0 AND p.ativo = 1
            ORDER BY hack_score DESC, t.data_prevista ASC 
            LIMIT 2
        ");
        $stmt->execute([$this->usuarioId]);
        $missoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $metrics['missao'] = $missoes[0] ?? null;
        $metrics['proxima_missao'] = $missoes[1] ?? null;

        if ($metrics['missao']) {
            $stD = $this->db->prepare("SELECT SUM(correta)/COUNT(id) FROM respostas_usuario r JOIN questoes q ON r.questao_id=q.id WHERE q.disciplina_id=? AND r.usuario_id=?");
            $stD->execute([$metrics['missao']['disciplina_id'], $this->usuarioId]);
            $dom = $stD->fetchColumn();
            $metrics['missao']['dominio'] = $dom !== null ? round($dom * 100) : 0;
        }

        // 7. Probabilidade de Aprovação (Simulação baseada em Cobertura e Taxa)
        $metrics['probabilidade'] = round(($metrics['cobertura'] * 0.3) + ($metrics['taxa_acerto'] * 0.7));

        // 8. Melhoria de Desempenho (Comparação simples)
        $metrics['melhoria'] = $metrics['taxa_acerto'] > 0 ? round($metrics['taxa_acerto'] * 0.2, 1) : 0; // Heurística para MVP

        // 9. Desafio Semanal Dinâmico
        if ($metrics['fraca']['nome'] !== '---') {
            $stD = $this->db->prepare("SELECT id FROM disciplinas WHERE nome = ? LIMIT 1");
            $stD->execute([$metrics['fraca']['nome']]);
            $d_id = $stD->fetchColumn();
            if ($d_id) {
                $stProg = $this->db->prepare("
                    SELECT SUM(r.correta) 
                    FROM respostas_usuario r 
                    JOIN questoes q ON r.questao_id=q.id 
                    WHERE q.disciplina_id=? AND r.usuario_id=? AND YEARWEEK(r.respondida_em, 1) = YEARWEEK(CURRENT_DATE, 1)
                ");
                $stProg->execute([$d_id, $this->usuarioId]);
                $progresso = (int)$stProg->fetchColumn();
                $metrics['desafio'] = [
                    'titulo' => "Acerte 50 questões de {$metrics['fraca']['nome']} até Domingo.",
                    'meta' => 50,
                    'progresso' => $progresso,
                    'percentual' => min(100, round(($progresso/50)*100))
                ];
            }
        }
        if (!isset($metrics['desafio'])) {
            $metrics['desafio'] = [
                'titulo' => "Responda mais questões para gerar um desafio.",
                'meta' => 10,
                'progresso' => 0,
                'percentual' => 0
            ];
        }

        return $metrics;
    }

    public function getConquistas() {
        $stmt = $this->db->prepare("SELECT * FROM conquistas WHERE usuario_id = ? ORDER BY conquistada_em DESC LIMIT 3");
        $stmt->execute([$this->usuarioId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getPontosFracos() {
        $stmt = $this->db->prepare("
            SELECT d.nome, (SUM(r.correta) / COUNT(r.id)) * 100 as taxa
            FROM respostas_usuario r
            JOIN questoes q ON r.questao_id = q.id
            JOIN disciplinas d ON q.disciplina_id = d.id
            WHERE r.usuario_id = ?
            GROUP BY d.id
            HAVING taxa < 70
            ORDER BY taxa ASC
            LIMIT 3
        ");
        $stmt->execute([$this->usuarioId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getNivelLabel($slug, $streak) {
        if ($streak > 15) return "Competitivo";
        if ($streak > 5) return "Consistente";
        return "Perdido";
    }

    private function calculateRisco($cobertura, $taxa) {
        $score = ($cobertura * 0.4) + ($taxa * 0.6);
        if ($score > 70) return ['label' => 'Baixo', 'color' => 'var(--neon-green)'];
        if ($score > 40) return ['label' => 'Médio', 'color' => 'var(--warning)'];
        return ['label' => 'Crítico', 'color' => 'var(--danger)'];
    }

    public function getRadar() {
        $problemas = [];
        
        // Exemplo de radar automático
        $stmt = $this->db->prepare("
            SELECT d.nome, (SUM(r.correta) / COUNT(r.id)) as taxa
            FROM respostas_usuario r
            JOIN questoes q ON r.questao_id = q.id
            JOIN disciplinas d ON q.disciplina_id = d.id
            WHERE r.usuario_id = ?
            GROUP BY d.id HAVING taxa < 0.6
        ");
        $stmt->execute([$this->usuarioId]);
        $fracas = $stmt->fetchAll();

        foreach ($fracas as $f) {
            $problemas[] = [
                'tipo' => 'desempenho',
                'msg' => "Você está errando muito em " . $f['nome'],
                'detalhe' => round($f['taxa'] * 100) . "% de acerto"
            ];
        }

        return $problemas;
    }
}
