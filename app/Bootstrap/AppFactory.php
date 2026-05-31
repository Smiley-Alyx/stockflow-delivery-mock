<?php

declare(strict_types=1);

namespace App\Bootstrap;

use App\Http\Controllers\HealthController;
use DI\ContainerBuilder;
use Slim\App;
use Slim\Factory\AppFactory as SlimAppFactory;

final class AppFactory
{
    public static function create(): App
    {
        $containerBuilder = new ContainerBuilder();
        $containerBuilder->addDefinitions([
            'config' => static fn (): array => require dirname(__DIR__, 2) . '/config/delivery_mock.php',
            HealthController::class => static function ($container): HealthController {
                /** @var array{service_name: string} $config */
                $config = $container->get('config');

                return new HealthController($config['service_name']);
            },
        ]);

        $container = $containerBuilder->build();

        SlimAppFactory::setContainer($container);
        $app = SlimAppFactory::create();

        $app->addBodyParsingMiddleware();
        $app->addRoutingMiddleware();

        /** @var array{debug_enabled: bool} $config */
        $config = $container->get('config');
        $app->addErrorMiddleware($config['debug_enabled'], true, true);

        $healthController = $container->get(HealthController::class);

        $app->get('/', [$healthController, 'index']);
        $app->get('/health', [$healthController, 'health']);
        $app->get('/ready', [$healthController, 'ready']);

        return $app;
    }
}
