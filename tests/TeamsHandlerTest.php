<?php

namespace LmsTeamsNotificator\Tests;

use LmsTeamsNotificator\Logging\TeamsHandler;
use LmsTeamsNotificator\Logging\TeamsLogger;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;

class TeamsHandlerTest extends TestCase
{
    const URL = 'https://teams.test/hook';

    /** @var FakeHttpClient */
    private $client;

    protected function setUp(): void
    {
        $this->client = new FakeHttpClient();
    }

    public function testTheMessageFormatPostsTheMessageAndItsContextAsMarkdown()
    {
        $this->logger(TeamsHandler::FORMAT_MESSAGE, 'Perseis')
            ->error('Build failed', array('tag' => 'v1.2.3', 'images' => array('web' => 2), 'retry' => false));

        $this->assertSame(
            array('message' => "Perseis:\n\nBuild failed\n\n**tag**: v1.2.3\n\n**images**: {\"web\":2}\n\n**retry**: false\n\n"),
            $this->onlyPayload()
        );
    }

    public function testTheAdaptiveCardFormatPostsACardColouredByLevel()
    {
        $this->logger(TeamsHandler::FORMAT_ADAPTIVE_CARD)->info('Build started', array('tag' => 'v1.2.3', 'images' => array('web' => 2)));

        $this->assertSame(array(
            'type'        => 'message',
            'attachments' => array(
                array(
                    'contentType' => 'application/vnd.microsoft.card.adaptive',
                    'content'     => array(
                        '$schema' => 'http://adaptivecards.io/schemas/adaptive-card.json',
                        'type'    => 'AdaptiveCard',
                        'version' => '1.2',
                        'msteams' => array('width' => 'Full'),
                        'body'    => array(
                            array('type' => 'TextBlock', 'size' => 'Medium', 'weight' => 'Bolder', 'color' => 'good', 'text' => 'Build started'),
                            $this->cardRow('tag', 'v1.2.3'),
                            $this->cardRow('images', "{\n    \"web\": 2\n}"),
                        ),
                    ),
                ),
            ),
        ), $this->onlyPayload());
    }

    public function testEachLevelHasItsCardColour()
    {
        $logger = $this->logger(TeamsHandler::FORMAT_ADAPTIVE_CARD);
        $expected = array(
            'emergency' => 'attention',
            'alert'     => 'warning',
            'critical'  => 'attention',
            'error'     => 'attention',
            'warning'   => 'warning',
            'notice'    => 'accent',
            'info'      => 'good',
            'debug'     => 'default',
        );

        foreach (array_keys($expected) as $level) {
            $logger->log($level, 'Message');
        }

        $colors = array();
        foreach ($this->client->requests as $request) {
            $colors[] = $request['payload']['attachments'][0]['content']['body'][0]['color'];
        }
        $this->assertSame(array_values($expected), $colors);
    }

    public function testObjectsAreShownByTheirClass()
    {
        $this->logger(TeamsHandler::FORMAT_MESSAGE)->error('Failed', array(
            'exception' => new \RuntimeException('x'),
            'user'      => new SerializableUser(),
        ));

        $this->assertSame(
            array('message' => "Failed\n\n**exception**: RuntimeException\n\n**user**: " . SerializableUser::class . "\n\n"),
            $this->onlyPayload()
        );
    }

    public function testRecordsBelowTheLevelAreNotPosted()
    {
        $logger = new Logger('teams', array(new TeamsHandler(self::URL, 'error', TeamsHandler::FORMAT_MESSAGE, null, $this->client)));

        $logger->warning('Ignored');

        $this->assertSame(array(), $this->client->requests);
    }

    public function testAnUnknownFormatIsRejected()
    {
        $this->expectException(\InvalidArgumentException::class);

        new TeamsHandler(self::URL, 'debug', 'markdown');
    }

    public function testTheLaravelChannelBuildsATeamsHandler()
    {
        $logger = (new TeamsLogger())(array('url' => self::URL, 'level' => 'error', 'format' => 'adaptive_card'));

        $this->assertInstanceOf(TeamsHandler::class, $logger->getHandlers()[0]);
        $this->assertFalse($logger->getHandlers()[0]->isHandling($this->record(Logger::WARNING)));
        $this->assertTrue($logger->getHandlers()[0]->isHandling($this->record(Logger::ERROR)));
    }

    public function testWithoutAUrlTheChannelLetsTheNextHandlersOfAStackReceiveTheRecords()
    {
        $next = new TestHandler();
        $handlers = (new TeamsLogger())(array('url' => null, 'level' => 'error'))->getHandlers();
        $stack = new Logger('stack', array_merge($handlers, array($next)));

        $stack->error('Message');

        $this->assertTrue($next->hasErrorRecords());
    }

    /**
     * @param string $format
     * @param string|null $title
     * @return Logger
     */
    private function logger($format, $title = null)
    {
        return new Logger('teams', array(new TeamsHandler(self::URL, 'debug', $format, $title, $this->client)));
    }

    /**
     * @return array
     */
    private function onlyPayload()
    {
        $this->assertCount(1, $this->client->requests);
        $this->assertSame(self::URL, $this->client->requests[0]['url']);

        return $this->client->requests[0]['payload'];
    }

    /**
     * @param string $key
     * @param string $value
     * @return array
     */
    private function cardRow($key, $value)
    {
        return array(
            'type'    => 'ColumnSet',
            'columns' => array(
                array('type' => 'Column', 'items' => array(array('type' => 'TextBlock', 'text' => $key, 'weight' => 'Bolder')), 'width' => 'stretch'),
                array('type' => 'Column', 'items' => array(array('type' => 'TextBlock', 'text' => $value, 'wrap' => true)), 'width' => 'stretch'),
            ),
        );
    }

    /**
     * Registro mínimo para isHandling(): un array en Monolog 2 y un LogRecord en Monolog 3.
     *
     * @param int $level
     * @return array|\Monolog\LogRecord
     */
    private function record($level)
    {
        if (class_exists('Monolog\LogRecord')) {
            return new \Monolog\LogRecord(new \DateTimeImmutable(), 'teams', Logger::toMonologLevel($level), 'Message');
        }

        return array('level' => $level);
    }
}
