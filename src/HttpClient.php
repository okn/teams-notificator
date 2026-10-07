<?php

namespace LmsTeamsNotificator;

final class HttpClient
{
    /**
     * @param string $url
     * @param array $payload
     * @param array $headers
     * @return array{status:int, body:string}
     */
    public function postJson($url, array $payload, array $headers = array())
    {
        $ch = curl_init($url);

        if ($ch === false) {
            throw new HttpRequestException('No se pudo inicializar cURL.');
        }

        $defaultHeaders = array(
            'Accept: application/json',
            'Content-Type: application/json',
        );

        $allHeaders = array_values(array_unique(array_merge($defaultHeaders, $headers)));

        // Sustituye bytes UTF-8 inválidos (p. ej. textos de Jira) en lugar de fallar
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($json === false) {
            throw new HttpRequestException('No se pudo convertir el payload a JSON: ' . json_last_error_msg());
        }

        curl_setopt_array($ch, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => $allHeaders,
            CURLOPT_POSTFIELDS     => $json,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT        => 30,
        ));

        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);

        curl_close($ch);

        if ($body === false) {
            throw new HttpRequestException("cURL error: {$err}");
        }

        if ($status >= 400) {
            throw new HttpRequestException("HTTP {$status}: {$body}", $status);
        }

        return array('status' => $status, 'body' => (string) $body);
    }
}
