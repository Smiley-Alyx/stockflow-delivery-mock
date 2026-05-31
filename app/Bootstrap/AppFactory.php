<?php

declare(strict_types=1);

namespace App\Bootstrap;

use App\Http\Controllers\DebugController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\ShipmentController;
use App\Http\Middleware\DomainExceptionMiddleware;
use DI\ContainerBuilder;
use Psr\Container\ContainerInterface;
use Slim\App;
use Slim\Factory\AppFactory as SlimAppFactory;

final class AppFactory
{
    public static function create(?ContainerInterface $container = null): App
    {
        $container ??= self::buildContainer();

        SlimAppFactory::setContainer($container);
        $app = SlimAppFactory::create();

        $app->addBodyParsingMiddleware();
        $app->add(new DomainExceptionMiddleware());
        $app->addRoutingMiddleware();

        /** @var array{debug_enabled: bool} $config */
        $config = $container->get('config');
        $app->addErrorMiddleware($config['debug_enabled'], true, true);

        self::registerRoutes($app, $container);

        return $app;
    }

    public static function buildContainer(): ContainerInterface
    {
        $containerBuilder = new ContainerBuilder();
        $containerBuilder->addDefinitions(ServiceDefinitions::all());

        return $containerBuilder->build();
    }

    private static function registerRoutes(App $app, ContainerInterface $container): void
    {
        $healthController = $container->get(HealthController::class);
        $shipmentController = $container->get(ShipmentController::class);
        $debugController = $container->get(DebugController::class);

        $app->get('/', [$healthController, 'index']);
        $app->get('/health', [$healthController, 'health']);
        $app->get('/ready', [$healthController, 'ready']);

        $app->get('/shipments', [$shipmentController, 'index']);
        $app->get('/shipments/{shipmentId}', [$shipmentController, 'show']);
        $app->post('/shipments/{shipmentId}/advance-status', [$shipmentController, 'advanceStatus']);
        $app->post('/shipments/{shipmentId}/mark-delivered', [$shipmentController, 'markDelivered']);
        $app->post('/shipments/{shipmentId}/mark-failed', [$shipmentController, 'markFailed']);
        $app->post('/shipments/{shipmentId}/cancel', [$shipmentController, 'cancel']);

        $app->post('/debug/reset', [$debugController, 'reset']);
        $app->get('/debug/failure-mode', [$debugController, 'showFailureMode']);
        $app->post('/debug/failure-mode', [$debugController, 'setFailureMode']);
    }
}
