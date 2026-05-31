<?php

declare(strict_types=1);

namespace App\Bootstrap;

use App\Domain\Delivery\Repositories\ShipmentRepository;
use App\Domain\Delivery\Services\DemoResetService;
use App\Domain\Delivery\Services\ShipmentLifecycleService;
use App\Http\Controllers\DebugController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\ShipmentController;
use App\Http\Middleware\DomainExceptionMiddleware;
use App\Infrastructure\Persistence\InMemoryShipmentRepository;
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
        $containerBuilder->addDefinitions(self::definitions());

        return $containerBuilder->build();
    }

    /**
     * @return array<string, mixed>
     */
    private static function definitions(): array
    {
        return [
            'config' => static fn (): array => require dirname(__DIR__, 2) . '/config/delivery_mock.php',
            InMemoryShipmentRepository::class => static fn (): InMemoryShipmentRepository => new InMemoryShipmentRepository(),
            ShipmentRepository::class => static fn ($container): ShipmentRepository => $container->get(InMemoryShipmentRepository::class),
            ShipmentLifecycleService::class => static fn ($container): ShipmentLifecycleService => new ShipmentLifecycleService(
                $container->get(ShipmentRepository::class),
            ),
            DemoResetService::class => static fn ($container): DemoResetService => new DemoResetService(
                $container->get(ShipmentRepository::class),
            ),
            HealthController::class => static function ($container): HealthController {
                /** @var array{service_name: string} $config */
                $config = $container->get('config');

                return new HealthController($config['service_name']);
            },
            ShipmentController::class => static fn ($container): ShipmentController => new ShipmentController(
                $container->get(ShipmentLifecycleService::class),
            ),
            DebugController::class => static function ($container): DebugController {
                /** @var array{debug_enabled: bool} $config */
                $config = $container->get('config');

                return new DebugController(
                    $container->get(DemoResetService::class),
                    $config['debug_enabled'],
                );
            },
        ];
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
    }
}
