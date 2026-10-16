<?php

namespace LmsTeamsNotificator;

interface HttpClientInterface
{
    /**
     * Envía el payload como JSON y lanza HttpRequestException si la petición falla.
     *
     * @param string $url
     * @param array $payload
     * @param array $headers
     * @return array{status:int, body:string}
     */
    public function postJson($url, array $payload, array $headers = array());
}
