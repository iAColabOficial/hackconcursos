<?php
/**
 * HackConcursos - StudyPlanner
 * Algoritmo de geração de plano de estudos personalizado
 * Distribui as disciplinas por ciclos considerando: peso, dificuldade do aluno e tempo disponível
 */
require_once __DIR__ . '/../config/config.php';

class StudyPlanner {

    private PDO $db;

    public function __construct() {
        $this->db = getDB();
    }

    /**
     * Gera o plano de estudos completo para um usuário/cargo/diagnóstico.
     * Cria tarefas na tabela tarefas_estudo e revisões automáticas.
     *
     * @param int $usuarioId
     * @param int $cargoId
     * @param int $diagnosticoId
     * @return int ID do plano criado
     */
    public function gerarPlano(int $usuarioId, int $cargoId, int $diagnosticoId): int {
        // 1. Buscar dados do diagnóstico
        $diag = $this->getDiagnostico($diagnosticoId);
        if (!$diag) throw new \RuntimeException('Diagnóstico não encontrado.');

        $horasDia   = (float) $diag['horas_disponivel'];
        $diasEstudo = explode(',', $diag['dias_estudo']); // ex: [1,2,3,4,5]
        $diaFolga   = (int) $diag['dia_folga'];

        // 2. Buscar disciplinas com seus pesos e nível do aluno
        $disciplinas = $this->getDisciplinasComDificuldade($cargoId, $diagnosticoId);
        if (empty($disciplinas)) throw new \RuntimeException('Nenhuma disciplina encontrada para este cargo.');

        // 3. Buscar data da prova (para calcular urgência)
        $dataProva = $this->getDataProva($cargoId);
        $dataInicio = new \DateTime();
        $urgenciaMult = 1.0;

        if ($dataProva) {
            $dataFim = $dataProva;
            $diasRestantes = $dataInicio->diff($dataFim)->days;
            
            // Se a prova for em menos de 45 dias, entramos em "Modo Reta Final"
            if ($diasRestantes > 0 && $diasRestantes < 45) {
                $urgenciaMult = 1.3;
            }
            $diasTotais = max(15, $diasRestantes);
        } else {
            $dataFim = (new \DateTime('+6 months'));
            $diasTotais = 180;
        }

        // 4. Calcular horas por disciplina
        $horasPonderadasDia = $horasDia * (count($diasEstudo) / 7);
        $horasTotais = $horasPonderadasDia * $diasTotais;
        $disciplinas = $this->calcularHoras($disciplinas, $horasTotais, $urgenciaMult);

        // 5. Criar o plano no banco
        $planoId = $this->criarPlano($usuarioId, $cargoId, $diagnosticoId, $dataInicio->format('Y-m-d'));

        // 6. Gerar as tarefas distribuídas nos dias de estudo
        $this->gerarTarefas($planoId, $disciplinas, $diasEstudo, $horasDia, $dataInicio);

        return $planoId;
    }

    /**
     * Distribui horas por disciplina baseando-se em peso, dificuldade e urgência.
     */
    private function calcularHoras(array $disciplinas, float $horasTotais, float $urgenciaMult = 1.0): array {
        // Fator de dificuldade: iniciante=1.5, intermediário=1.0, avançado=0.7
        $fatores = ['iniciante' => 1.5, 'intermediario' => 1.0, 'avancado' => 0.7];

        $pontosTotal = 0;
        foreach ($disciplinas as &$d) {
            $fator  = $fatores[$d['nivel'] ?? 'intermediario'] ?? 1.0;
            // Dificuldade 1-10 também pondera: mais difícil = mais horas
            $dificuldade = max(1, min(10, (int)($d['dificuldade'] ?? 5))) / 5;
            $d['pontos'] = (float)$d['peso'] * $fator * $dificuldade * $urgenciaMult;
            $pontosTotal += $d['pontos'];
        }
        unset($d);

        foreach ($disciplinas as &$d) {
            $d['horas_alocadas'] = ($d['pontos'] / max(1, $pontosTotal)) * $horasTotais;
            $d['horas_alocadas'] = max(2.0, $d['horas_alocadas']); // Mínimo 2 horas por disciplina
        }
        unset($d);

        return $disciplinas;
    }

    /**
     * Gera as tarefas diárias no banco de dados.
     * Distribui em ciclos: estuda todas as disciplinas antes de repetir.
     */
    private function gerarTarefas(int $planoId, array $disciplinas, array $diasEstudo, float $horasDia, \DateTime $inicio): void {
        $minutosHorasDia = $horasDia * 60;

        // Montar um pool de blocos de estudo intercalados por disciplina
        $queues = [];
        $maxSessoes = 0;
        foreach ($disciplinas as $disc) {
            $horasPorSessao = 1.5; // cada sessão = 1.5 hora
            $sessoes = max(1, ceil($disc['horas_alocadas'] / $horasPorSessao));
            $minSessao = (int)($horasPorSessao * 60);
            $maxSessoes = max($maxSessoes, $sessoes);

            $discQueues = [];
            for ($s = 0; $s < $sessoes; $s++) {
                $discQueues[] = [
                    'disciplina_id' => $disc['id'],
                    'titulo'        => $disc['nome'],
                    'tipo'          => 'estudo',
                    'duracao'       => $minSessao,
                    'disc_ref'      => $disc, // para revisões
                ];
            }
            $queues[] = $discQueues;
        }

        // Intercalar blocos usando round-robin
        $blocos = [];
        for ($i = 0; $i < $maxSessoes; $i++) {
            foreach ($queues as &$q) {
                if (!empty($q)) {
                    $blocos[] = array_shift($q);
                }
            }
        }

        // Distribuir blocos pelos dias de estudo
        $data      = clone $inicio;
        $blocoIdx  = 0;
        $total     = count($blocos);
        $maxDias   = 180; // limite de 6 meses
        $diaContador = 0;
        $tarefasInseridas = []; // [tarefa_id => tarefa_array]

        $stmt = $this->db->prepare(
            "INSERT INTO tarefas_estudo (plano_id, disciplina_id, titulo, tipo, data_prevista, duracao_minutos)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmtRev = $this->db->prepare(
            "INSERT INTO tarefas_estudo (plano_id, disciplina_id, titulo, tipo, data_prevista, duracao_minutos)
             VALUES (?, ?, ?, ?, ?, ?)"
        );

        while ($blocoIdx < $total && $diaContador < $maxDias) {
            $diaSemana = (int)$data->format('N'); // 1=Mon...7=Sun
            $diaSemanaStr = (string)$diaSemana;

            if (in_array($diaSemanaStr, $diasEstudo)) {
                $minRestantes = $minutosHorasDia;

                while ($blocoIdx < $total && $minRestantes >= 30) {
                    $bloco = $blocos[$blocoIdx];
                    $dur   = min($bloco['duracao'], $minRestantes);

                    $stmt->execute([
                        $planoId,
                        $bloco['disciplina_id'],
                        $bloco['titulo'],
                        'estudo',
                        $data->format('Y-m-d'),
                        $dur
                    ]);
                    $tarefaId = (int)$this->db->lastInsertId();

                    // Agendar revisões automáticas
                    $this->agendarRevisao($stmtRev, $planoId, $bloco, $data, $tarefaId, 1);   // 24h
                    $this->agendarRevisao($stmtRev, $planoId, $bloco, $data, $tarefaId, 7);   // 7 dias
                    $this->agendarRevisao($stmtRev, $planoId, $bloco, $data, $tarefaId, 30);  // 30 dias

                    $minRestantes -= $dur;
                    $blocoIdx++;
                }
            }

            $data->modify('+1 day');
            $diaContador++;
        }
    }

    /**
     * Agenda uma tarefa de revisão após N dias.
     */
    private function agendarRevisao(\PDOStatement $stmt, int $planoId, array $bloco, \DateTime $dataOrigem, int $tarefaOrigemId, int $dias): void {
        $tipos = [1 => 'revisao_24h', 7 => 'revisao_7d', 30 => 'revisao_30d'];
        $tipo  = $tipos[$dias] ?? 'revisao_7d';

        $dataRevisao = (clone $dataOrigem)->modify("+$dias days");
        $stmt->execute([
            $planoId,
            $bloco['disciplina_id'],
            'Revisão - ' . $bloco['titulo'],
            $tipo,
            $dataRevisao->format('Y-m-d'),
            30 // revisões duram 30 minutos
        ]);
    }

    /**
     * Reorganiza tarefas atrasadas para os próximos dias disponíveis.
     */
    public function reorganizarAtrasadas(int $planoId): int {
        $hoje = date('Y-m-d');

        // Buscar tarefas atrasadas não concluídas
        $st = $this->db->prepare(
            "SELECT id FROM tarefas_estudo
             WHERE plano_id = ? AND concluida = 0 AND data_prevista < ? AND tipo = 'estudo'
             ORDER BY data_prevista ASC"
        );
        $st->execute([$planoId, $hoje]);
        $atrasadas = $st->fetchAll(PDO::FETCH_COLUMN);

        if (empty($atrasadas)) return 0;

        // Reagendar a partir de amanhã
        $data = new \DateTime('tomorrow');
        $upd  = $this->db->prepare("UPDATE tarefas_estudo SET data_prevista = ?, atrasada = 1 WHERE id = ?");

        foreach ($atrasadas as $id) {
            $upd->execute([$data->format('Y-m-d'), $id]);
            $data->modify('+1 day');
        }

        return count($atrasadas);
    }

    // ---- HELPERS ----

    private function getDiagnostico(int $id): ?array {
        $st = $this->db->prepare("SELECT * FROM diagnosticos WHERE id = ?");
        $st->execute([$id]);
        return $st->fetch() ?: null;
    }

    private function getDisciplinasComDificuldade(int $cargoId, int $diagnosticoId): array {
        $st = $this->db->prepare("
            SELECT d.id, d.nome, d.peso,
                   COALESCE(dd.nivel, 'intermediario') AS nivel,
                   COALESCE(dd.dificuldade, 5) AS dificuldade
            FROM disciplinas d
            LEFT JOIN diagnostico_disciplinas dd
                   ON dd.disciplina_id = d.id AND dd.diagnostico_id = ?
            WHERE d.cargo_id = ?
            ORDER BY d.peso DESC
        ");
        $st->execute([$diagnosticoId, $cargoId]);
        return $st->fetchAll();
    }

    private function getDataProva(int $cargoId): ?\DateTime {
        $st = $this->db->prepare("SELECT e.data_prova FROM editais e JOIN cargos c ON c.edital_id = e.id WHERE c.id = ? LIMIT 1");
        $st->execute([$cargoId]);
        $data = $st->fetchColumn();
        return $data ? new \DateTime($data) : null;
    }

    private function criarPlano(int $userId, int $cargoId, int $diagId, string $inicio): int {
        // Desativar planos anteriores para que o novo seja o principal na visualização
        $this->db->prepare("UPDATE planos_estudo SET ativo = 0 WHERE usuario_id = ?")->execute([$userId]);

        $st = $this->db->prepare(
            "INSERT INTO planos_estudo (usuario_id, cargo_id, diagnostico_id, data_inicio, ativo) VALUES (?,?,?,?,1)"
        );
        $st->execute([$userId, $cargoId, $diagId, $inicio]);
        return (int)$this->db->lastInsertId();
    }

    /**
     * Retorna as tarefas de hoje para exibição no dashboard.
     */
    public function getTarefasHoje(int $usuarioId): array {
        $st = $this->db->prepare("
            SELECT t.*, d.nome AS disciplina_nome
            FROM tarefas_estudo t
            JOIN planos_estudo p ON p.id = t.plano_id
            JOIN disciplinas d ON d.id = t.disciplina_id
            WHERE p.usuario_id = ? AND t.data_prevista = CURDATE()
            ORDER BY t.concluida ASC, t.id ASC
            LIMIT 10
        ");
        $st->execute([$usuarioId]);
        return $st->fetchAll();
    }

    /**
     * Calcula progresso geral do plano ativo.
     */
    public function getProgressoGeral(int $usuarioId): array {
        $st = $this->db->prepare("
            SELECT
                COUNT(*) AS total,
                SUM(t.concluida) AS concluidas,
                SUM(CASE WHEN t.concluida = 1 THEN t.duracao_minutos ELSE 0 END) AS minutos_estudados
            FROM tarefas_estudo t
            JOIN planos_estudo p ON p.id = t.plano_id
            WHERE p.usuario_id = ? AND p.ativo = 1
        ");
        $st->execute([$usuarioId]);
        $row = $st->fetch();
        $total     = (int)($row['total'] ?? 0);
        $concluidas= (int)($row['concluidas'] ?? 0);
        return [
            'total'             => $total,
            'concluidas'        => $concluidas,
            'percentual'        => $total > 0 ? round(($concluidas / $total) * 100, 1) : 0,
            'horas_estudadas'   => round(($row['minutos_estudados'] ?? 0) / 60, 1),
        ];
    }

    /**
     * Gera um resumo textual do status do aluno para ser usado pela IA.
     */
    public function getResumoStatus(int $usuarioId): string {
        $progresso = $this->getProgressoGeral($usuarioId);
        
        // Buscar o cargo/concurso atual
        $st = $this->db->prepare("
            SELECT c.nome as cargo, e.nome_concurso
            FROM planos_estudo p
            JOIN cargos c ON c.id = p.cargo_id
            JOIN editais e ON e.id = c.edital_id
            WHERE p.usuario_id = ? AND p.ativo = 1
            LIMIT 1
        ");
        $st->execute([$usuarioId]);
        $info = $st->fetch();
        
        $concurso = $info['nome_concurso'] ?? 'Não definido';
        $cargo    = $info['cargo'] ?? 'Não definido';

        // Buscar nomes das disciplinas com maior dificuldade
        $std = $this->db->prepare("
            SELECT d.nome, dd.dificuldade 
            FROM diagnostico_disciplinas dd
            JOIN disciplinas d ON d.id = dd.disciplina_id
            JOIN diagnosticos diag ON diag.id = dd.diagnostico_id
            WHERE diag.usuario_id = ?
            ORDER BY dd.dificuldade DESC
            LIMIT 3
        ");
        $std->execute([$usuarioId]);
        $dificuldades = $std->fetchAll();
        $difStr = "";
        foreach($dificuldades as $d) {
            $difStr .= "- {$d['nome']} (Nível de dificuldade: {$d['dificuldade']}/10)\n";
        }

        $resumo = "NOME DO CONCURSO: $concurso\n";
        $resumo .= "CARGO: $cargo\n";
        $resumo .= "PROGRESSO TOTAL: {$progresso['percentual']}%\n";
        $resumo .= "HORAS ESTUDADAS: {$progresso['horas_estudadas']}h\n";
        $resumo .= "DISCIPLINAS MAIS DIFÍCEIS PARA O ALUNO:\n" . ($difStr ?: "Nenhum diagnóstico realizado ainda.\n");

        return $resumo;
    }

    /**
     * Calcula o risco estratégico de reprovação baseado no ritmo atual vs data da prova.
     */
    public function getAnaliseRisco(int $usuarioId): array {
        $db = $this->db;
        
        // 1. Pegar dados básicos
        $progresso = $this->getProgressoGeral($usuarioId);
        
        $st = $db->prepare("
            SELECT e.data_prova, p.horas_dia, p.usuario_id
            FROM planos_estudo pl
            JOIN cargos c ON c.id = pl.cargo_id
            JOIN editais e ON e.id = c.edital_id
            JOIN perfis_usuario p ON p.usuario_id = pl.usuario_id
            WHERE pl.usuario_id = ? AND pl.ativo = 1
            LIMIT 1
        ");
        $st->execute([$usuarioId]);
        $info = $st->fetch();
        
        if (!$info || !$info['data_prova']) {
            return ['nivel' => 'baixo', 'msg' => 'Defina uma data de prova para calcular seu risco.', 'cor' => 'var(--neon-green)'];
        }

        $dataProva = new \DateTime($info['data_prova']);
        $hoje = new \DateTime();
        $diasRestantes = $hoje->diff($dataProva)->days;
        
        // 2. Calcular esforço necessário
        $tarefasRestantes = $progresso['total'] - $progresso['concluidas'];
        $horasNecessarias = ($tarefasRestantes * 1.2); // média de 1.2h por tarefa (estudo + revisão)
        
        if ($diasRestantes <= 0) return ['nivel' => 'critico', 'msg' => 'A prova já passou ou é hoje! Foco total.', 'cor' => '#ef4444'];

        $horasPorDiaNecessarias = $horasNecessarias / $diasRestantes;
        $capacidadeAtual = (float)$info['horas_dia'];
        
        $ratio = $horasPorDiaNecessarias / max(0.5, $capacidadeAtual);

        if ($ratio > 2.5) {
            $nivel = 'extremo';
            $msg = "RISCO EXTREMO: Você precisaria de " . round($horasPorDiaNecessarias, 1) . "h/dia. Ritmo atual insuficiente.";
            $cor = '#ef4444';
        } elseif ($ratio > 1.5) {
            $nivel = 'alto';
            $msg = "RISCO ALTO: Seu edital está correndo mais rápido que você. Aumente a carga.";
            $cor = '#f59e0b';
        } elseif ($ratio > 1.0) {
            $nivel = 'medio';
            $msg = "ALERTA: Você está no limite. Qualquer atraso será fatal para sua aprovação.";
            $cor = '#fbbf24';
        } else {
            $nivel = 'baixo';
            $msg = "RITMO SEGURO: Continue assim e você cobrirá o edital com folga.";
            $cor = 'var(--neon-green)';
        }

        return [
            'nivel' => $nivel,
            'msg' => $msg,
            'cor' => $cor,
            'ratio' => $ratio,
            'horas_necessarias_dia' => round($horasPorDiaNecessarias, 1),
            'dias_restantes' => $diasRestantes
        ];
    }
}
