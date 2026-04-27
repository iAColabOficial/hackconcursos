<?php

class InsightsData {
    private $db;
    private $usuarioId;

    public function __construct($db, $usuarioId) {
        $this->db = $db;
        $this->usuarioId = $usuarioId;
    }

    public function getMapaCalor() {
        $stmt = $this->db->prepare("
            SELECT d.nome, 
                   COUNT(r.id) as questoes, 
                   (SUM(r.correta) / COUNT(r.id)) * 100 as taxa
            FROM respostas_usuario r
            JOIN questoes q ON r.questao_id = q.id
            JOIN disciplinas d ON q.disciplina_id = d.id
            WHERE r.usuario_id = ?
            GROUP BY d.id
        ");
        $stmt->execute([$this->usuarioId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getProjecao() {
        // Simulação de projeção
        return [
            'atual' => 58,
            'ideal' => 82,
            'tendencia' => '+4% / semana',
            'dias_para_meta' => 45
        ];
    }

    public function getPadroesBanca($banca) {
        return [
            ['msg' => "A banca $banca cobra mais jurisprudência do que texto de lei nesta disciplina.", 'tipo' => 'alerta'],
            ['msg' => "Você erra sempre quando aparecem questões de 'Certo ou Errado' com pegadinhas de exclusão.", 'tipo' => 'padrao']
        ];
    }
}
