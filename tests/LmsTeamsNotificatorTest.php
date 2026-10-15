<?php

namespace LmsTeamsNotificator\Tests;

use LmsTeamsNotificator\LmsTeamsNotificator;
use PHPUnit\Framework\TestCase;

class LmsTeamsNotificatorTest extends TestCase
{
    public function testTheAccumulatedMessageIsPosted()
    {
        $client = new FakeHttpClient();

        (new LmsTeamsNotificator('Hola', 'https://teams.test/hook', $client))->setText('Mundo')->notify();

        $this->assertSame(
            array(array('url' => 'https://teams.test/hook', 'payload' => array('message' => "Hola\n\nMundo\n\n"))),
            $client->requests
        );
    }

    public function testAnEmptyMessageIsNotPosted()
    {
        $client = new FakeHttpClient();

        (new LmsTeamsNotificator('', 'https://teams.test/hook', $client))->reset()->notify();

        $this->assertSame(array(), $client->requests);
    }
}
