<?php
/**
 * HackConcursos - Serviço de Integração com Google Gemini API
 */
require_once __DIR__ . '/../config/config.php';

class GeminiService {

    private string $apiKey;
    private string $model;
    private string $baseUrl;

    public function __construct() {
        $this->apiKey  = GEMINI_API_KEY;
        $this->model   = GEMINI_MODEL;
        $this->baseUrl = GEMINI_BASE_URL;
    }

    /**
     * Extrai informações estruturadas de um edital em texto puro.
     * Retorna array com: concurso, banca, data_prova, cargos, disciplinas.
     */
    public function parseEdital(string $textoEdital): array {
        $prompt = <<<PROMPT
Você é um analisador especializado em editais de concursos públicos brasileiros.
Analise o texto do edital abaixo e retorne um JSON válido com a seguinte estrutura:

{
  "concurso": "NOME DO CONCURSO",
  "banca": "NOME DA BANCA",
  "orgao": "ÓRGÃO OU INSTITUIÇÃO",
  "data_prova": "YYYY-MM-DD ou null",
  "disciplinas": [
    {
      "nome": "NOME DA MATÉRIA",
      "peso": 1.0,
      "topicos": ["Tópico 1", "Tópico 2"]
    }
  ]
}

REGRAS CRÍTICAS:
1. Ignore divisões de cargos. Liste todas as disciplinas e matérias de prova encontradas no conteúdo programático.
2. Retorne APENAS o JSON. Sem comentários, sem markdown, sem justificativas.
3. Se não encontrar nada, retorne a estrutura vazia.

TEXTO DO EDITAL:
{texto}
PROMPT;

        $prompt = str_replace('{texto}', mb_substr($textoEdital, 0, 30000), $prompt);
        $resposta = $this->chamarAPI($prompt);

        // Extrator Robusto: Localiza o JSON real ignorando qualquer texto extra da IA
        $inicio = strpos($resposta, '{');
        $fim    = strrpos($resposta, '}');
        
        if ($inicio !== false && $fim !== false) {
            $resposta = substr($resposta, $inicio, $fim - $inicio + 1);
        }

        $dados = json_decode(trim($resposta), true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            // Em caso de erro, vamos logar no error_log do PHP para diagnóstico
            error_log("Erro JSON Gemini: " . $resposta);
            throw new \Exception('A IA enviou os dados em um formato inesperado. Tente enviar o arquivo novamente.');
        }
        
        return $dados;
    }

    /**
     * Chat Mentor: Responde perguntas gerais sobre estudos, matérias ou o edital.
     */
    public function chatEdital(string $pergunta, string $contextoEdital, array $historico = [], string $contextoAluno = '', string $nomeConcurso = '', string $banca = ''): string {
        $systemPrompt = "Você é o 'Mentor Hack', um especialista em concursos públicos de alto desempenho.
Seu objetivo é ser o co-piloto do aluno, ajudando-o a dominar as matérias e o edital.

DIRETRIZES:
1. CONTEXTO: Você tem acesso aos dados do edital ativo e do aluno abaixo. Use-os para responder com precisão.
2. PERSONALIDADE: Seja direto, motivador e estratégico.
3. FORMATAÇÃO: Sempre que responder, termine ou decore sua resposta com uma linha de muitos asteriscos (********************) para manter o estilo do Mentor Hack.
4. INSTRUÇÃO: Se o aluno perguntar sobre o edital, você JÁ TEM os dados. Nunca diga que não tem o edital se ele estiver listado no contexto abaixo.";

        $fullContext = "### PAINEL DE CONTEXTO ATIVO ###\n";
        $fullContext .= "CONCURSO: " . ($nomeConcurso ?: 'Não identificado') . "\n";
        $fullContext .= "BANCA: " . ($banca ?: 'Não identificado') . "\n\n";
        $fullContext .= "## STATUS DO ALUNO ##\n{$contextoAluno}\n\n";
        $fullContext .= "## FRAGMENTO DO EDITAL ##\n" . mb_substr($contextoEdital, 0, 10000) . "\n\n";
        $fullContext .= "PERGUNTA ATUAL: {$pergunta}";

        $contents = [];

        // Adicionar histórico
        foreach ($historico as $msg) {
            $contents[] = [
                'role'  => $msg['papel'],
                'parts' => [['text' => $msg['mensagem']]]
            ];
        }

        // Adicionar mensagem atual
        $contents[] = [
            'role'  => 'user',
            'parts' => [['text' => $fullContext]]
        ];

        return $this->chamarAPI('', $contents, $systemPrompt);
    }

    /**
     * Gera questões de simulado profissionais para uma disciplina e banca específica.
     */
    public function gerarQuestoes(string $disciplina, array $topicos, int $quantidade = 10, string $banca = 'FGV', string $dificuldade = 'media'): array {
        $topicosStr = implode(', ', array_slice($topicos, 0, 10));
        
        $prompt = <<<PROMPT
Você é um EXAMINADOR SÊNIOR da banca {$banca}, especialista em concursos públicos de alto nível.
Sua tarefa é gerar {$quantidade} questões inéditas e profissionais de múltipla escolha para a disciplina "{$disciplina}".

DIRETRIZES TÉCNICAS:
1. Nível de Dificuldade: {$dificuldade} (Ajuste a complexidade do enunciado e das alternativas).
2. Estilo da Banca: Siga rigorosamente o perfil da banca {$banca} (Ex: FGV costuma ser teórica e cansativa, CESPE direta, FCC técnica).
3. Tópicos Alvo: {$topicosStr}.
4. Estrutura: 5 alternativas (A-E), sendo apenas uma correta.
5. Distratores: As alternativas incorretas devem ser plausíveis, baseadas em erros comuns de candidatos.
6. Explicação do Hack: Forneça uma explicação curta mas "matadora", citando a lei, artigo ou jurisprudência se for o caso.

REQUISITO DE SAÍDA:
Retorne APENAS um JSON válido no seguinte formato:
[
  {
    "enunciado": "Texto da questão...",
    "alternativa_a": "...",
    "alternativa_b": "...",
    "alternativa_c": "...",
    "alternativa_d": "...",
    "alternativa_e": "...",
    "gabarito": "A",
    "explicacao": "Explicação estratégica..."
  }
]
PROMPT;

        $resposta = $this->chamarAPI($prompt);
        
        // Extrator Robusto: Localiza o início e fim do array JSON ignorando ruídos
        $inicio = strpos($resposta, '[');
        $fim    = strrpos($resposta, ']');
        
        if ($inicio !== false && $fim !== false) {
            $resposta = substr($resposta, $inicio, $fim - $inicio + 1);
        }

        $dados = json_decode(trim($resposta), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("Erro JSON Gemini Questoes: " . $resposta);
            throw new \Exception('A IA enviou as questões em um formato inesperado. Tente novamente.');
        }
        return (array)$dados;
    }

    /**
     * Chama a API do Gemini (geração de texto simples ou com histórico).
     */
    private function chamarAPI(string $prompt = '', array $contents = [], string $systemInstruction = ''): string {
        if (empty($this->apiKey) || $this->apiKey === 'SUA_CHAVE_AQUI') {
            throw new \Exception('API Key do Gemini não configurada. Acesse config/config.php.');
        }

        // URL sem a chave (passaremos no header)
        $url = $this->baseUrl . $this->model . ':generateContent';

        if (empty($contents)) {
            $contents = [['role' => 'user', 'parts' => [['text' => $prompt]]]];
        }

        $payload = [
            'contents'         => $contents,
            'generationConfig' => [
                'temperature'     => 0.3,
                'maxOutputTokens' => 8192,
            ]
        ];

        // Adicionar instrução de sistema se fornecida
        if (!empty($systemInstruction)) {
            $payload['system_instruction'] = [
                'parts' => [['text' => $systemInstruction]]
            ];
        }

        $body = json_encode($payload);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'x-goog-api-key: ' . $this->apiKey // Autenticação via Header
            ],
            CURLOPT_TIMEOUT        => 120,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new \Exception("Erro de rede: {$error}");
        }

        $json = json_decode($response, true);

        if ($httpCode !== 200) {
            $msg = $json['error']['message'] ?? 'Erro desconhecido na API.';
            throw new \Exception("Erro API Gemini ({$httpCode}): {$msg}");
        }

        return $json['candidates'][0]['content']['parts'][0]['text'] ?? '';
    }
}
