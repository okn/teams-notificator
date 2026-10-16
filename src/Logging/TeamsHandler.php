<?php

namespace LmsTeamsNotificator\Logging;

use LmsTeamsNotificator\HttpClient;
use LmsTeamsNotificator\HttpClientInterface;
use LmsTeamsNotificator\LmsTeamsNotificator;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Logger;

/**
 * Handler de Monolog que envía cada registro a un webhook de Teams.
 *
 * Funciona con Monolog 2 y 3: write() no tipa el registro (Monolog 2 pasa un array y
 * Monolog 3 un LogRecord) y lo lee como array, que LogRecord también admite.
 */
class TeamsHandler extends AbstractProcessingHandler
{
    /** Payload {"message": "..."} para un flujo de Power Automate que lee ese campo. */
    const FORMAT_MESSAGE = 'message';

    /** Adaptive Card, para un webhook que publica la tarjeta tal cual en el canal. */
    const FORMAT_ADAPTIVE_CARD = 'adaptive_card';

    /** Color de la tarjeta según el nivel del registro. */
    const COLORS = array(
        'EMERGENCY' => 'attention',
        'ALERT'     => 'warning',
        'CRITICAL'  => 'attention',
        'ERROR'     => 'attention',
        'WARNING'   => 'warning',
        'NOTICE'    => 'accent',
        'INFO'      => 'good',
        'DEBUG'     => 'default',
    );

    /** @var string */
    private $webhookUrl;

    /** @var string */
    private $format;

    /** @var string|null */
    private $title;

    /** @var HttpClientInterface */
    private $httpClient;

    /**
     * @param string $webhookUrl
     * @param int|string $level
     * @param string $format FORMAT_MESSAGE o FORMAT_ADAPTIVE_CARD
     * @param string|null $title Texto que precede al mensaje, para distinguir el origen
     * @param HttpClientInterface|null $httpClient Cliente HTTP; por defecto, cURL
     * @param bool $bubble
     */
    public function __construct(
        $webhookUrl,
        $level = 'debug',
        $format = self::FORMAT_MESSAGE,
        $title = null,
        ?HttpClientInterface $httpClient = null,
        $bubble = true
    ) {
        if (!in_array($format, array(self::FORMAT_MESSAGE, self::FORMAT_ADAPTIVE_CARD), true)) {
            throw new \InvalidArgumentException("Formato de Teams no válido: {$format}");
        }

        parent::__construct(Logger::toMonologLevel($level), $bubble);

        $this->webhookUrl = $webhookUrl;
        $this->format = $format;
        $this->title = $title;
        $this->httpClient = $httpClient ?: new HttpClient();
    }

    /**
     * @param array|\Monolog\LogRecord $record
     */
    protected function write($record): void
    {
        $message = $this->title !== null && $this->title !== ''
            ? $this->title . ":\n\n" . $record['message']
            : (string) $record['message'];

        if ($this->format === self::FORMAT_ADAPTIVE_CARD) {
            $this->httpClient->postJson(
                $this->webhookUrl,
                $this->card($message, $record['level_name'], $record['context'])
            );

            return;
        }

        $notificator = new LmsTeamsNotificator($message, $this->webhookUrl, $this->httpClient);
        foreach ($record['context'] as $key => $value) {
            $notificator->setText('**' . $key . '**: ' . $this->text($value, 0));
        }
        $notificator->notify();
    }

    /**
     * @param string $message
     * @param string $levelName
     * @param array $context
     * @return array
     */
    private function card($message, $levelName, array $context)
    {
        $body = array(
            array(
                'type'   => 'TextBlock',
                'size'   => 'Medium',
                'weight' => 'Bolder',
                'color'  => self::COLORS[$levelName],
                'text'   => $message,
            ),
        );

        foreach ($context as $key => $value) {
            $body[] = array(
                'type'    => 'ColumnSet',
                'columns' => array(
                    array(
                        'type'  => 'Column',
                        'items' => array(array('type' => 'TextBlock', 'text' => (string) $key, 'weight' => 'Bolder')),
                        'width' => 'stretch',
                    ),
                    array(
                        'type'  => 'Column',
                        'items' => array(array('type' => 'TextBlock', 'text' => $this->text($value, JSON_PRETTY_PRINT), 'wrap' => true)),
                        'width' => 'stretch',
                    ),
                ),
            );
        }

        return array(
            'type'        => 'message',
            'attachments' => array(
                array(
                    'contentType' => 'application/vnd.microsoft.card.adaptive',
                    'content'     => array(
                        '$schema' => 'http://adaptivecards.io/schemas/adaptive-card.json',
                        'type'    => 'AdaptiveCard',
                        'version' => '1.2',
                        'msteams' => array('width' => 'Full'),
                        'body'    => $body,
                    ),
                ),
            ),
        );
    }

    /**
     * Teams solo muestra texto: los objetos se identifican solo por su clase, para no
     * enviar sus datos (un modelo serializable puede contener datos personales), y el
     * resto de valores que no son texto se muestran como JSON.
     *
     * @param mixed $value
     * @param int $jsonFlags
     * @return string
     */
    private function text($value, $jsonFlags)
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_object($value)) {
            return get_class($value);
        }

        $json = json_encode($value, $jsonFlags | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $json === false ? '' : $json;
    }
}
