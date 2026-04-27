<?php

class ScannerErros {
    private $db;
    private $usuarioId;

    public function __construct($db, $usuarioId) {
        $this->db = $db;
        $this->usuarioId = $usuarioId;
    }

    public function getResumo() {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as total_erros, 
                   (SELECT COUNT(*) FROM respostas_usuario WHERE usuario_id = ? AND correta = 1) as total_acertos
            FROM respostas_usuario 
            WHERE usuario_id = ? AND correta = 0
        ");
        $stmt->execute([$this->usuarioId, $this->usuarioId]);
        $res = $stmt->fetch();
        
        $total = $res['total_erros'] + $res['total_acertos'];
        $taxa = $total > 0 ? round(($res['total_acertos'] / $total) * 100, 1) : 0;
        
        return [
            'total_erros' => $res['total_erros'],
            'taxa_acerto' => $taxa,
            'padrao_principal' => "Confusão entre 'Culpabilidade' e 'Ilicitude'" // Hardcoded para MVP, IA preencherá depois
        ];
    }

    public function getPadroes() {
        // Simulação de detecção de padrões. No futuro, isso usará a matriz de temas da IA.
        return [
            [
                'nome' => 'Confusão Conceitual',
                'frequencia' => 45,
                'disciplina' => 'Direito Penal',
                'impacto' => 'Alto',
                'descricao' => 'Você tende a trocar conceitos de crimes omissivos por comissivos.'
            ],
            [
                'nome' => 'Erro por Pressa (Tempo)',
                'frequencia' => 30,
                'disciplina' => 'Português',
                'impacto' => 'Médio',
                'descricao' => 'Erros em questões de interpretação longa no final do simulado.'
            ],
            [
                'nome' => 'Negligência em Exceções',
                'frequencia' => 25,
                'disciplina' => 'Direito Adm.',
                'impacto' => 'Crítico',
                'descricao' => 'Você domina a regra geral, mas erra 90% das exceções de licitação.'
            ]
        ];
    }
}
