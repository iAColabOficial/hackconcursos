<?php
/**
 * HackConcursos - Serviço de Integração com OpenAI API (ChatGPT)
 */
require_once __DIR__ . '/../config/config.php';

class OpenAIService {

    private string $apiKey;
    private string $model;
    private string $baseUrl;

    public function __construct() {
        $this->apiKey  = OPENAI_API_KEY;
        $this->model   = OPENAI_MODEL;
        $this->baseUrl = OPENAI_BASE_URL;
    }

    /**
     * Extrai informações estruturadas de um edital.
     */
    public function parseEdital(string $textoEdital): array {
        $systemPrompt = "Você é um analisador especializado em editais de concursos públicos brasileiros. Retorne APENAS um JSON válido. Não use markdown.";
        $userPrompt = "Analise o texto do edital e retorne um JSON com esta estrutura:
{
  \"concurso\": \"Nome\",
  \"banca\": \"Banca\",
  \"orgao\": \"Órgão\",
  \"data_prova\": \"YYYY-MM-DD ou null\",
  \"cargos\": [
    {
      \"nome\": \"Nome\",
      \"nivel\": \"fundamental|medio|superior\",
      \"vagas\": 0,
      \"salario\": 0.0,
      \"disciplinas\": [
        { \"nome\": \"Matéria\", \"peso\": 1.0, \"topicos\": [\"T1\", \"T2\"] }
      ]
    }
  ]
}

TEXTO: " . mb_substr($textoEdital, 0, 15000);

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userPrompt]
        ];

        $resposta = $this->chamarAPI($messages);
        
        // Limpar markdown se a IA colocar
        $resposta = preg_replace('/```json|```/', '', $resposta);
        $dados = json_decode(trim($resposta), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('Falha ao interpretar resposta da OpenAI.');
        }
        return $dados;
    }

    /**
     * Responde perguntas sobre o edital.
     */
    public function chatEdital(string $pergunta, string $contextoEdital, array $historico = []): string {
        $messages = [
            ['role' => 'system', 'content' => "Você é um mentor de concursos. Contexto do edital: " . mb_substr($contextoEdital, 0, 10000)]
        ];

        foreach ($historico as $msg) {
            $role = ($msg['papel'] === 'user') ? 'user' : 'assistant';
            $messages[] = ['role' => $role, 'content' => $msg['mensagem']];
        }

        $messages[] = ['role' => 'user', 'content' => $pergunta];

        return $this->chamarAPI($messages);
    }

    /**
     * Gera questões de simulado.
     */
    public function gerarQuestoes(string $disciplina, array $topicos, int $quantidade = 10): array {
        $prompt = "Gere {$quantidade} questões de múltipla escolha para concurso sobre {$disciplina} (Tópicos: " . implode(', ', array_slice($topicos, 0, 10)) . "). 
        Retorne um array JSON: [{\"enunciado\":\"...\",\"alternativa_a\":\"...\",\"alternativa_b\":\"...\",\"alternativa_c\":\"...\",\"alternativa_d\":\"...\",\"alternativa_e\":\"...\",\"gabarito\":\"A|B|C|D|E\",\"explicacao\":\"...\"}]";

        $messages = [
            ['role' => 'system', 'content' => "Você é um professor de banca examinadora. Retorne apenas JSON."],
            ['role' => 'user', 'content' => $prompt]
        ];

        $resposta = $this->chamarAPI($messages);
        $resposta = preg_replace('/```json|```/', '', $resposta);
        
        return json_decode(trim($resposta), true) ?? [];
    }

    /**
     * Chamada genérica para o Chat Completion da OpenAI.
     */
    private function chamarAPI(array $messages): string {
        if (empty($this->apiKey) || $this->apiKey === 'SUA_CHAVE_AQUI') {
            throw new \Exception('API Key da OpenAI não enviada. Verifique o arquivo .env.');
        }

        $url = $this->baseUrl . 'chat/completions';

        $body = json_encode([
            'model'       => $this->model,
            'messages'    => $messages,
            'temperature' => 0.3
        ]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey
            ],
            CURLOPT_TIMEOUT => 60
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $json = json_decode($response, true);

        if ($httpCode !== 200) {
            $msg = $json['error']['message'] ?? 'Erro desconhecido na OpenAI.';
            throw new \Exception("Erro OpenAI ({$httpCode}): {$msg}");
        }

        return $json['choices'][0]['message']['content'] ?? '';
    }
}
