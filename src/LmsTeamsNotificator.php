<?php

namespace LmsTeamsNotificator;

final class LmsTeamsNotificator
{
    /** @var HttpClientInterface */
    private $httpClient;

    /** @var string */
    private $webhookUrl;

    /** @var string */
    private $message = '';

    /**
     * @param string $message
     * @param string $webhookUrl
     * @param HttpClientInterface|null $httpClient Cliente HTTP; por defecto, cURL
     */
    public function __construct($message, $webhookUrl, ?HttpClientInterface $httpClient = null)
    {
        $this->httpClient = $httpClient ?: new HttpClient();
        $this->webhookUrl = $webhookUrl;
        $this->message = $message . "\n\n";
    }

    /**
     * Permite cambiar dinámicamente el webhook de Teams
     *
     * @param string $webhookUrl
     * @return self
     */
    public function setTeamsWebHookUrl(string $webhookUrl): self
    {
        $this->webhookUrl = $webhookUrl;
        return $this;
    }

    /**
     * Concatena texto al mensaje, terminando cada llamada con "\n\n"
     *
     * @param string $text
     * @return self
     */
    public function setText($text)
    {
        $this->message .= $text . "\n\n";
        return $this;
    }

    /**
     * Envía el mensaje acumulado al webhook configurado
     *
     * @return void
     */
    public function notify()
    {
        if (trim($this->message) === '') {
            return;
        }

        $this->httpClient->postJson($this->webhookUrl, [
            'message' => $this->message,
        ]);
    }

    /**
     * Limpia el mensaje acumulado (opcional, si reutilizas el objeto)
     *
     * @return self
     */
    public function reset()
    {
        $this->message = '';
        return $this;
    }
}
