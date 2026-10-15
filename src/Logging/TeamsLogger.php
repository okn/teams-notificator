<?php

namespace LmsTeamsNotificator\Logging;

use Monolog\Handler\NullHandler;
use Monolog\Logger;

/**
 * Crea el logger de un canal de Laravel con driver "custom":
 *
 *     'teams' => [
 *         'driver' => 'custom',
 *         'via'    => \LmsTeamsNotificator\Logging\TeamsLogger::class,
 *         'url'    => env('TEAMS_LOG_WEBHOOK_URL'),
 *         'level'  => env('TEAMS_LOG_LEVEL', 'error'),
 *         'format' => 'message', // o 'adaptive_card'
 *         'title'  => 'Mi aplicación', // opcional
 *     ],
 *
 * Sin URL, el canal descarta los registros, para que los entornos sin webhook sigan funcionando.
 */
class TeamsLogger
{
    /**
     * @param array $config
     * @return Logger
     */
    public function __invoke(array $config)
    {
        $level = isset($config['level']) ? $config['level'] : 'debug';

        if (empty($config['url'])) {
            return new Logger('teams', array(new NullHandler()));
        }

        return new Logger('teams', array(
            new TeamsHandler(
                $config['url'],
                $level,
                isset($config['format']) ? $config['format'] : TeamsHandler::FORMAT_MESSAGE,
                isset($config['title']) ? $config['title'] : null
            ),
        ));
    }
}
