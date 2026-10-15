<?php

namespace LmsTeamsNotificator\Tests;

use LmsTeamsNotificator\HttpClientInterface;

class FakeHttpClient implements HttpClientInterface
{
    /** @var array<int, array{url: string, payload: array}> */
    public $requests = array();

    public function postJson($url, array $payload, array $headers = array())
    {
        $this->requests[] = array('url' => $url, 'payload' => $payload);

        return array('status' => 200, 'body' => '1');
    }
}
