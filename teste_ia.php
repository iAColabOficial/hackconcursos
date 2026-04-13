<?php
/**
 * Script de Diagnóstico da API Gemini
 * Acesse este arquivo pelo navegador para ver quais modelos estão disponíveis para sua chave.
 */
require_once __DIR__ . '/config/config.php';

header('Content-Type: text/html; charset=utf-8');

echo "<style>body { font-family: sans-serif; background: #1a1a1a; color: #fff; padding: 2rem; } pre { background: #000; padding: 1rem; border-radius: 8px; overflow: auto; }</style>";
echo "<h1>Diagnóstico de Inteligência Artificial</h1>";
echo "<p>Testando conexão com a Google Generative AI API...</p>";

$apiKey = GEMINI_API_KEY;
$url = "https://generativelanguage.googleapis.com/v1/models?key=" . $apiKey;

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$res = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError) {
    echo "<h2 style='color:red'>Erro de Conexão (CURL):</h2>";
    echo "<pre>{$curlError}</pre>";
} else {
    echo "<h2>Resposta do Google (Código HTTP {$httpCode}):</h2>";
    $json = json_decode($res, true);
    
    if (isset($json['error'])) {
        echo "<h3 style='color:orange'>O Google retornou um erro:</h3>";
        echo "<pre>" . json_encode($json['error'], JSON_PRETTY_PRINT) . "</pre>";
    } elseif (isset($json['models'])) {
        echo "<h3 style='color:lightgreen'>Sucesso! Modelos disponíveis para sua chave:</h3>";
        echo "<ul>";
        foreach ($json['models'] as $m) {
            $name = str_replace('models/', '', $m['name']);
            $supported = implode(', ', $m['supportedGenerationMethods']);
            echo "<li><b>{$name}</b> (Suporta: {$supported})</li>";
        }
        echo "</ul>";
        echo "<h4>JSON Completo:</h4>";
        echo "<pre>" . json_encode($json, JSON_PRETTY_PRINT) . "</pre>";
    } else {
        echo "<h3>Resposta Inesperada:</h3>";
        echo "<pre>{$res}</pre>";
    }
}
