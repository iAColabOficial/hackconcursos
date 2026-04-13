<?php
/**
 * HackConcursos - PDFParser
 * Extrai texto de PDFs sem dependências externas (leitura binária básica)
 * Para PDFs complexos recomenda-se instalar pdftotext via poppler
 */
class PDFParser {

    /**
     * Extrai texto de um arquivo PDF.
     * Tenta pdftotext primeiro (se disponível no servidor), depois leitura binária.
     */
    public static function extrairTexto(string $caminho): string {
        if (!file_exists($caminho)) {
            throw new \RuntimeException("Arquivo não encontrado: $caminho");
        }

        // Tentativa 1: pdftotext (mais preciso, disponível em muitos servidores Linux)
        $texto = self::viaPdfToText($caminho);
        if (!empty(trim($texto))) {
            return $texto;
        }

        // Tentativa 2: leitura binária do PDF (funciona para PDFs simples não criptografados)
        $texto = self::viaBinario($caminho);
        if (!empty(trim($texto))) {
            return $texto;
        }

        throw new \RuntimeException('Não foi possível extrair texto do PDF. Verifique se o arquivo não está protegido.');
    }

    /**
     * Usa o comando pdftotext do sistema operacional.
     */
    private static function viaPdfToText(string $caminho): string {
        // Verificar se o comando existe
        $check = shell_exec('which pdftotext 2>/dev/null || where pdftotext 2>nul');
        if (empty($check)) {
            // Tentar caminhos comuns no Windows (XAMPP + poppler)
            $paths = [
                'C:\\poppler\\bin\\pdftotext.exe',
                'C:\\Program Files\\poppler\\bin\\pdftotext.exe',
                '/usr/bin/pdftotext',
            ];
            $exe = '';
            foreach ($paths as $p) {
                if (file_exists($p)) { $exe = '"' . $p . '"'; break; }
            }
            if (empty($exe)) return '';
        } else {
            $exe = 'pdftotext';
        }

        $safe = escapeshellarg($caminho);
        $output = shell_exec("$exe $safe - 2>/dev/null");
        return $output ?? '';
    }

    /**
     * Extrai texto via leitura do binário PDF (Suporta streams comprimidos).
     */
    private static function viaBinario(string $caminho): string {
        $conteudo = file_get_contents($caminho);
        if ($conteudo === false) return '';

        $texto = '';
        
        // 1. Tentar capturar streams comprimidos (/Filter /FlateDecode)
        preg_match_all('/stream\s*(.*?)\s*endstream/s', $conteudo, $matches);
        foreach ($matches[1] as $stream) {
            $stream = trim($stream);
            // Tentar descompactar se for Zlib
            $data = @gzuncompress($stream);
            if ($data === false) $data = @gzdecode($stream);
            
            if ($data !== false) {
                // Extrair texto entre parênteses do stream descompactado
                preg_match_all('/\(([^)]*)\)/', $data, $strings);
                foreach ($strings[1] as $s) {
                    if (strlen(trim($s)) > 2) $texto .= $s . ' ';
                }
            }
        }

        // 2. Fallback: Capturar texto simples direto no binário (não comprimido)
        if (strlen(trim($texto)) < 100) {
            preg_match_all('/BT\s*(.*?)\s*ET/s', $conteudo, $matches);
            foreach ($matches[1] as $bloco) {
                preg_match_all('/\(([^)]*)\)/', $bloco, $strings);
                foreach ($strings[1] as $s) {
                    $texto .= $s . ' ';
                }
            }
        }

        // 3. Fallback Final: Limpeza de caracteres estranhos
        $texto = preg_replace('/[^\x20-\x7E\xC0-\xFF]/', ' ', $texto);
        $texto = preg_replace('/\s{2,}/', ' ', $texto);
        
        return trim($texto);
    }

    /**
     * Verifica se o arquivo é um PDF válido.
     */
    public static function isPDF(string $caminho): bool {
        if (!file_exists($caminho)) return false;
        $header = file_get_contents($caminho, false, null, 0, 5);
        return $header === '%PDF-';
    }

    /**
     * Retorna o tamanho do PDF em MB.
     */
    public static function tamanhoMB(string $caminho): float {
        return round(filesize($caminho) / 1048576, 2);
    }
}
