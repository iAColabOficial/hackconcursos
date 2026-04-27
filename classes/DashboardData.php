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

        // 3. Taxa de Acerto Geral
        $stmt = $this->db->prepare("SELECT SUM(correta) as acertos, COUNT(id) as total FROM respostas_usuario WHERE usuario_id = ?");
        $stmt->execute([$this->usuarioId]);
        $respostas = $stmt->fetch();
        $metrics['taxa_acerto'] = $respostas['total'] > 0 ? round(($respostas['acertos'] / $respostas['total']) * 100, 1) : 0;

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

        // 6. Missão Atual
        $stmt = $this->db->prepare("
            SELECT t.*, d.nome as disciplina_nome 
            FROM tarefas_estudo t 
            JOIN disciplinas d ON t.disciplina_id = d.id
            JOIN planos_estudo p ON t.plano_id = p.id
            WHERE p.usuario_id = ? AND t.concluida = 0 AND p.ativo = 1
            ORDER BY t.data_prevista ASC, t.id ASC LIMIT 1
        ");
        $stmt->execute([$this->usuarioId]);
        $metrics['missao'] = $stmt->fetch(PDO::FETCH_ASSOC);

        return $metrics;
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
