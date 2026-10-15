# teams-notificator

Paquete PHP para enviar notificaciones a Microsoft Teams (Power Automate / webhook).

Compatible con PHP 7.2 a 8.x. El canal de log funciona con Monolog 2 (Laravel 7 a 9) y Monolog 3 (Laravel 10 en adelante).

## Instalación

```bash
composer require iseazylms/teams-notificator
```

## Envío directo

```php
use LmsTeamsNotificator\LmsTeamsNotificator;

(new LmsTeamsNotificator('Proceso terminado', $webhookUrl))
    ->setText('**Duración**: 3 min')
    ->notify();
```

Envía `{"message": "..."}` al webhook, para un flujo de Power Automate que lea el campo `message`. Si el envío falla, lanza `HttpRequestException`.

## Canal de log (Laravel)

Requiere `monolog/monolog` (Laravel ya lo incluye). En `config/logging.php`:

```php
'teams' => [
    'driver' => 'custom',
    'via'    => \LmsTeamsNotificator\Logging\TeamsLogger::class,
    'url'    => env('TEAMS_LOG_WEBHOOK_URL'),
    'level'  => env('TEAMS_LOG_LEVEL', 'error'),
    'format' => 'message',      // o 'adaptive_card'
    'title'  => 'Mi aplicación', // opcional
],
```

- `format`:
  - `message` (por defecto): `{"message": "..."}` con el mensaje y una línea `**clave**: valor` por cada dato del contexto.
  - `adaptive_card`: una Adaptive Card con el mensaje en el color del nivel y una fila por cada dato del contexto, para un webhook que publique la tarjeta tal cual.
- `title`: texto que precede al mensaje, para distinguir el origen.
- Sin `url`, el canal descarta los registros.
- En el contexto, los objetos se muestran por su clase y el resto de valores que no son texto, como JSON.

Fuera de Laravel, `LmsTeamsNotificator\Logging\TeamsHandler` es un handler de Monolog normal.

## Tests

```bash
composer install
composer test
```

Para inyectar un cliente HTTP falso en los tests, `LmsTeamsNotificator` y `TeamsHandler` aceptan una implementación de `HttpClientInterface`.
