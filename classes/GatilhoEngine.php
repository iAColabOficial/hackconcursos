<?php

class GatilhoEngine {
    private $db;
    private $usuarioId;

    public function __construct($db, $usuarioId) {
        $this->db = $db;
        $this->usuarioId = $usuarioId;
    }

    public function verificarGatilhoSimulado($simuladoId) {
        $st = $this->db->prepare("SELECT total_questoes, acertos FROM simulados WHERE id = ? AND usuario_id = ?");
        $st->execute([$simuladoId, $this->usuarioId]);
        $sim = $st->fetch(PDO::FETCH_ASSOC);

        if (!$sim || $sim['total_questoes'] == 0) return null;

        $pct = round(($sim['acertos'] / $sim['total_questoes']) * 100);

        // Buscar ofertas ativas
        $stOf = $this->db->prepare("SELECT * FROM ofertas WHERE ativo = 1");
        $stOf->execute();
        $ofertas = $stOf->fetchAll(PDO::FETCH_ASSOC);

        foreach ($ofertas as $of) {
            // Regra: score_simulado_abaixo X
            if ($of['gatilho_tipo'] === 'score_simulado_abaixo' || $of['gatilho_tipo'] === 'baixa_performance') {
                $val = (int)$of['gatilho_valor'];
                if ($val == 0) $val = 60; // default 60%
                if ($pct < $val) {
                    return $of;
                }
            }
        }
        return null;
    }
}
