<?php
/**
 * HackConcursos - StudyPlanner
 * Algoritmo de geração de plano de estudos personalizado
 * Distribui as disciplinas por ciclos considerando: peso, dificuldade do aluno e tempo disponível
 */
require_once __DIR__ . '/../config/config.php';

class StudyPlanner {

    private PDO $db;
    private $limiteFraco = 60; // Abaixo de 60% é considerado fraqueza
    private $limiteDominado = 90; // Acima de 90% pode pular ou espaçar mais
    private $usuarioId; // Adicionado para rastreio

    public function __construct() {
        $this->db = getDB();
    }

    /**
     * Gera o plano de estudos completo para um usuário/cargo/diagnóstico.
     * Agora focado em MISSÕES por TÓPICO.
     */
    public function gerarPlano(int $usuarioId, int $cargoId, int $diagnosticoId): int {
        $diag = $this->getDiagnostico($diagnosticoId);
        if (!$diag) throw new \RuntimeException('Diagnóstico não encontrado.');

        $horasDia   = (float) $diag['horas_disponivel'];
        $diasEstudo = explode(',', $diag['dias_estudo']);
        $diaFolga   = (int) $diag['dia_folga'];

        // 1. Buscar disciplinas e SEUS TÓPICOS
        $disciplinas = $this->getDisciplinasComDificuldade($cargoId, $diagnosticoId);
        if (empty($disciplinas)) throw new \RuntimeException('Nenhuma disciplina encontrada.');

        $dataProva = $this->getDataProva($cargoId);
        $dataInicio = new \DateTime();
        $urgenciaMult = 1.0;

        if ($dataProva) {
            $diasRestantes = $dataInicio->diff($dataProva)->days;
            if ($diasRestantes > 0 && $diasRestantes < 45) $urgenciaMult = 1.3;
            $diasTotais = max(15, $diasRestantes);
        } else {
            $diasTotais = 180;
        }

        // 2. Calcular distribuição de carga horária
        $horasPonderadasDia = $horasDia * (count($diasEstudo) / 7);
        $horasTotais = $horasPonderadasDia * $diasTotais;
        $disciplinas = $this->calcularHoras($disciplinas, $horasTotais, $urgenciaMult);

        // 3. Criar o plano
        $planoId = $this->criarPlano($usuarioId, $cargoId, $diagnosticoId, $dataInicio->format('Y-m-d'));

        // 4. Gerar as MISSÕES (Tópico a Tópico)
        $this->gerarMissoes($planoId, $disciplinas, $diasEstudo, $horasDia, $dataInicio);

        return $planoId;
    }

    /**
     * Algoritmo de Geração de Missões Estruturadas
     */
    private function gerarMissoes(int $planoId, array $disciplinas, array $diasEstudo, float $horasDia, \DateTime $inicio): void {
        $minutosDisponiveisDia = $horasDia * 60;
        $data = clone $inicio;
        $diaContador = 0;
        $maxDias = 240; // Limite de 8 meses de planejamento

        // Preparar pool de tópicos por disciplina
        $poolMissoes = [];
        foreach ($disciplinas as $disc) {
            $topicos = $this->getTopicosPorDisciplina((int)$disc['id']);
            
            // Se não houver tópicos cadastrados, criamos um tópico genérico para não travar o plano
            if (empty($topicos)) {
                $topicos = [['id' => null, 'nome' => 'Conteúdo Geral', 'cobrado_frequentemente' => 0]];
            }

            // Calcular quantas missões (sessões) essa disciplina terá
            $horasPorMissao = 1.5;
            $totalMissoes = max(1, ceil($disc['horas_alocadas'] / $horasPorMissao));
            
            $missoesDisc = [];
            for ($i = 0; $i < $totalMissoes; $i++) {
                // Seleciona tópico (em loop se houver menos tópicos que sessões)
                $topico = $topicos[$i % count($topicos)];
                
                // Define Relevância
                $relevancia = $topico['cobrado_frequentemente'] ? 'alta' : 'media';
                
                // Estrutura a Instrução da Missão (Ação Clara)
                $instrucao = $this->gerarInstrucaoMissao($disc['nivel'], $relevancia);
                
                // Define Resultado Esperado
                $resultado = $this->gerarResultadoEsperado($disc['nivel']);

                $missoesDisc[] = [
                    'disciplina_id' => $disc['id'],
                    'topico_id'     => $topico['id'],
                    'titulo'        => $topico['nome'],
                    'tipo'          => 'estudo',
                    'duracao'       => (int)($horasPorMissao * 60),
                    'relevancia'    => $relevancia,
                    'instrucao'     => $instrucao,
                    'resultado'     => $resultado
                ];
            }
            $poolMissoes[] = $missoesDisc;
        }

        // Intercalação Round-Robin para manter o ciclo de estudos dinâmico
        $cronogramaFinal = [];
        $continua = true;
        $idx = 0;
        while ($continua) {
            $continua = false;
            foreach ($poolMissoes as &$fila) {
                if (!empty($fila)) {
                    $cronogramaFinal[] = array_shift($fila);
                    $continua = true;
                }
            }
        }

        // Inserção no Banco
        $stmt = $this->db->prepare("
            INSERT INTO tarefas_estudo 
            (plano_id, disciplina_id, topico_id, titulo, tipo, data_prevista, duracao_minutos, relevancia, instrucao_missao, resultado_esperado)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $missaoIdx = 0;
        $totalMissoes = count($cronogramaFinal);

        while ($missaoIdx < $totalMissoes && $diaContador < $maxDias) {
            $diaSemana = $data->format('N');
            if (in_array((string)$diaSemana, $diasEstudo)) {
                $minDia = $minutosDisponiveisDia;
                
                while ($minDia >= 45 && $missaoIdx < $totalMissoes) {
                    $m = $cronogramaFinal[$missaoIdx];
                    $dur = min($m['duracao'], $minDia);

                    $stmt->execute([
                        $planoId,
                        $m['disciplina_id'],
                        $m['topico_id'],
                        $m['titulo'],
                        $m['tipo'],
                        $data->format('Y-m-d'),
                        $dur,
                        $m['relevancia'],
                        $m['instrucao'],
                        $m['resultado']
                    ]);
                    
                    $tarefaId = (int)$this->db->lastInsertId();
                    
                    // Agenda Revisões Estratégicas (baseadas no tópico)
                    $this->agendarRevisoesMissao($planoId, $m, $data, $tarefaId);

                    $minDia -= $dur;
                    $missaoIdx++;
                }
            }
            $data->modify('+1 day');
            $diaContador++;
        }
    }

    /**
     * PROCESSAMENTO REATIVO (Lógica Pura - Sem IA)
     * Analisa o último resultado e decide se injeta reforço.
     */
    public function processarDesempenhoMissao(int $tarefaId): array {
        $st = $this->db->prepare("SELECT * FROM tarefas_estudo WHERE id = ?");
        $st->execute([$tarefaId]);
        $t = $st->fetch();

        if (!$t || !$t['questoes_total']) return ['status' => 'ok'];

        $percentual = ($t['questoes_acerto'] / $t['questoes_total']) * 100;

        // Se desempenho foi baixo, injeta REFORÇO AUTOMÁTICO
        if ($percentual < $this->limiteFraco) {
            $this->agendarReforcoImediato($t);
            $frequencia = $this->getFrequenciaErroTopico($t['usuario_id'], $t['topico_id']);
            
            $this->registrarEvento($t['usuario_id'], 'alerta_erro_exibido', json_encode(['tarefa_id' => $tarefaId, 'frequencia' => $frequencia]));

            return [
                'status' => 'alerta',
                'msg' => 'Desempenho abaixo da meta.',
                'frequencia' => $frequencia,
                'sugerir_ia' => true
            ];
        }

        return ['status' => 'sucesso', 'msg' => 'Excelente! Tópico dominado.', 'sugerir_ia' => false];
    }

    private function getFrequenciaErroTopico(int $uid, int $topicoId): int {
        $st = $this->db->prepare("
            SELECT COUNT(*) FROM tarefas_estudo t
            JOIN planos_estudo p ON p.id = t.plano_id
            WHERE p.usuario_id = ? AND t.topico_id = ? AND (t.questoes_acerto / t.questoes_total) < 0.6
        ");
        $st->execute([$uid, $topicoId]);
        return (int)$st->fetchColumn();
    }

    private function registrarEvento(int $uid, string $evento, string $meta): void {
        $st = $this->db->prepare("INSERT INTO eventos_usuario (usuario_id, evento, metadata) VALUES (?, ?, ?)");
        $st->execute([$uid, $evento, $meta]);
    }

    /**
     * Agenda uma missão de reforço para o próximo dia disponível
     */
    private function agendarReforcoImediato(array $tarefaOriginal): void {
        $st = $this->db->prepare("
            INSERT INTO tarefas_estudo 
            (plano_id, disciplina_id, topico_id, titulo, tipo, data_prevista, duracao_minutos, relevancia, instrucao_missao, resultado_esperado)
            VALUES (?, ?, ?, ?, ?, DATE_ADD(CURDATE(), INTERVAL 1 DAY), ?, ?, ?, ?)
        ");
        
        $instrucao = "MISSÃO DE REFORÇO: Seu desempenho anterior foi baixo. Foque em revisar a base teórica e refazer as questões que errou.";
        
        $st->execute([
            $tarefaOriginal['plano_id'],
            $tarefaOriginal['disciplina_id'],
            $tarefaOriginal['topico_id'],
            "Reforço: " . str_replace('Reforço: ', '', $tarefaOriginal['titulo']),
            'estudo',
            45, // Sessão mais curta de foco
            'alta', // Reforço é sempre prioridade alta
            $instrucao,
            "75% de acerto (Recuperação)"
        ]);
    }

    /**
     * Gera uma instrução acionável baseada no nível do aluno
     */
    private function gerarInstrucaoMissao(string $nivel, string $relevancia): string {
        if ($nivel === 'iniciante') {
            return "FOCO EM TEORIA: Leia o material base e faça um mapa mental. Finalize com 5 questões de fixação.";
        } elseif ($nivel === 'intermediario') {
            return "ESTUDO ATIVO: Revise seus pontos de dúvida e realize 15 questões. Se errar mais de 3, volte ao PDF.";
        } else { // avançado
            return "MODO GUERRA: Realize 30 questões direto. Foque apenas nas justificativas dos erros e súmulas relacionadas.";
        }
    }

    /**
     * Define o critério de sucesso da missão
     */
    private function gerarResultadoEsperado(string $nivel): string {
        $metas = ['iniciante' => '60% de acerto', 'intermediario' => '75% de acerto', 'avancado' => '90% de acerto'];
        return $metas[$nivel] ?? '70% de acerto';
    }

    private function getTopicosPorDisciplina(int $disciplinaId): array {
        $st = $this->db->prepare("SELECT id, nome, cobrado_frequentemente FROM topicos_edital WHERE disciplina_id = ? ORDER BY cobrado_frequentemente DESC, id ASC");
        $st->execute([$disciplinaId]);
        return $st->fetchAll();
    }

    private function agendarRevisoesMissao(int $planoId, array $missao, \DateTime $dataOrigem, int $origemId): void {
        $ciclos = [1 => 'revisao_24h', 7 => 'revisao_7d', 30 => 'revisao_30d'];
        $stmt = $this->db->prepare("
            INSERT INTO tarefas_estudo 
            (plano_id, disciplina_id, topico_id, titulo, tipo, data_prevista, duracao_minutos, relevancia, instrucao_missao, resultado_esperado)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        foreach ($ciclos as $dias => $tipo) {
            $dataRev = (clone $dataOrigem)->modify("+$dias days");
            $instrucao = ($tipo === 'revisao_24h') ? "REVISÃO RÁPIDA: Releia seus grifos e resolva as 3 questões que errou ontem." : "REVISÃO TÁTICA: Resolva 10 questões mistas deste tópico.";
            
            $stmt->execute([
                $planoId,
                $missao['disciplina_id'],
                $missao['topico_id'],
                "Revisão: " . $missao['titulo'],
                $tipo,
                $dataRev->format('Y-m-d'),
                30,
                $missao['relevancia'],
                $instrucao,
                $missao['resultado']
            ]);
        }
    }

    /**
     * Distribui horas por disciplina baseando-se em peso, dificuldade e urgência.
     */
    private function calcularHoras(array $disciplinas, float $horasTotais, float $urgenciaMult = 1.0): array {
        $fatores = ['iniciante' => 1.5, 'intermediario' => 1.0, 'avancado' => 0.7];

        $pontosTotal = 0;
        foreach ($disciplinas as &$d) {
            $fator  = $fatores[$d['nivel'] ?? 'intermediario'] ?? 1.0;
            $dificuldade = max(1, min(10, (int)($d['dificuldade'] ?? 5))) / 5;
            $d['pontos'] = (float)$d['peso'] * $fator * $dificuldade * $urgenciaMult;
            $pontosTotal += $d['pontos'];
        }
        unset($d);

        foreach ($disciplinas as &$d) {
            $d['horas_alocadas'] = ($d['pontos'] / max(1, $pontosTotal)) * $horasTotais;
            $d['horas_alocadas'] = max(2.0, $d['horas_alocadas']);
        }
        unset($d);

        return $disciplinas;
    }

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
        $this->db->prepare("UPDATE planos_estudo SET ativo = 0 WHERE usuario_id = ?")->execute([$userId]);
        $st = $this->db->prepare(
            "INSERT INTO planos_estudo (usuario_id, cargo_id, diagnostico_id, data_inicio, ativo) VALUES (?,?,?,?,1)"
        );
        $st->execute([$userId, $cargoId, $diagId, $inicio]);
        return (int)$this->db->lastInsertId();
    }

    public function reorganizarAtrasadas(int $planoId): int {
        $hoje = date('Y-m-d');
        $st = $this->db->prepare(
            "SELECT id FROM tarefas_estudo
             WHERE plano_id = ? AND concluida = 0 AND data_prevista < ? AND tipo = 'estudo'
             ORDER BY data_prevista ASC"
        );
        $st->execute([$planoId, $hoje]);
        $atrasadas = $st->fetchAll(PDO::FETCH_COLUMN);

        if (empty($atrasadas)) return 0;

        $data = new \DateTime('tomorrow');
        $upd  = $this->db->prepare("UPDATE tarefas_estudo SET data_prevista = ?, atrasada = 1 WHERE id = ?");

        foreach ($atrasadas as $id) {
            $upd->execute([$data->format('Y-m-d'), $id]);
            $data->modify('+1 day');
        }

        return count($atrasadas);
    }

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

    public function getResumoStatus(int $usuarioId): string {
        $progresso = $this->getProgressoGeral($usuarioId);
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

    public function getAnaliseRisco(int $usuarioId): array {
        $db = $this->db;
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
        $tarefasRestantes = $progresso['total'] - $progresso['concluidas'];
        $horasNecessarias = ($tarefasRestantes * 1.2);
        if ($diasRestantes <= 0) return ['nivel' => 'critico', 'msg' => 'A prova já passou ou é hoje! Foco total.', 'cor' => '#ef4444'];
        $horasPorDiaNecessarias = $horasNecessarias / $diasRestantes;
        $capacidadeAtual = (float)$info['horas_dia'];
        $ratio = $horasPorDiaNecessarias / max(0.5, $capacidadeAtual);

        if ($ratio > 2.5) {
            $nivel = 'extremo';
            $msg = "RISCO EXTREMO: Você precisaria de " . round($horasPorDiaNecessarias, 1) . "h/dia. Ritmo atual insuficiente.";
            $cor = '#ef4444';

            // Alerta de Risco por Email (1x ao dia)
            $stCheck = $db->prepare("SELECT COUNT(*) FROM eventos_usuario WHERE usuario_id = ? AND evento = 'risco_extremo' AND DATE(criado_em) = CURDATE()");
            $stCheck->execute([$usuarioId]);
            if ($stCheck->fetchColumn() == 0) {
                $this->registrarEvento($usuarioId, 'risco_extremo', json_encode(['ratio' => $ratio, 'horas' => $horasPorDiaNecessarias]));
                
                $stU = $db->prepare("SELECT email, nome FROM usuarios WHERE id = ?");
                $stU->execute([$usuarioId]);
                $usr = $stU->fetch();
                if ($usr && !empty($usr['email'])) {
                    $to = $usr['email'];
                    $subject = "ALERTA CRÍTICO: Risco de Reprovação Detectado";
                    $message = "Olá {$usr['nome']},\n\nO sistema detectou que o seu ritmo de estudos atual é insuficiente para cobrir o edital a tempo. Você precisaria de " . round($horasPorDiaNecessarias, 1) . "h/dia.\n\nAcesse o painel e ajuste sua rota agora!";
                    $headers = "From: alertas@hackconcursos.com\r\n";
                    @mail($to, $subject, $message, $headers);
                }
            }
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
