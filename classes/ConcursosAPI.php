<?php

class ConcursosAPI {
    private $baseUrl = 'http://localhost:5000';

    public function getConcursos($categoria = 'br') {
        $url = $this->baseUrl . '/concursos/' . urlencode(strtolower($categoria));
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15); // timeout em segundos
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode == 200 && $response) {
            return json_decode($response, true);
        }
        
        return [];
    }
    
    public function getEstados() {
        return [
            'br' => 'Nacional',
            'ac' => 'Acre', 'al' => 'Alagoas', 'am' => 'Amazonas', 'ap' => 'Amapá',
            'ba' => 'Bahia', 'ce' => 'Ceará', 'df' => 'Distrito Federal', 'es' => 'Espírito Santo',
            'go' => 'Goiás', 'ma' => 'Maranhão', 'mg' => 'Minas Gerais', 'ms' => 'Mato Grosso do Sul',
            'mt' => 'Mato Grosso', 'pa' => 'Pará', 'pb' => 'Paraíba', 'pe' => 'Pernambuco',
            'pi' => 'Piauí', 'pr' => 'Paraná', 'rj' => 'Rio de Janeiro', 'rn' => 'Rio Grande do Norte',
            'ro' => 'Rondônia', 'rr' => 'Roraima', 'rs' => 'Rio Grande do Sul', 'sc' => 'Santa Catarina',
            'se' => 'Sergipe', 'sp' => 'São Paulo', 'to' => 'Tocantins'
        ];
    }
}
