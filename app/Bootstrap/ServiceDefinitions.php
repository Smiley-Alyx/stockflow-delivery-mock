<?php

declare(strict_types=1);

namespace App\Bootstrap;

use App\Application\Handlers\ShipmentCancelRequestedHandler;
use App\Application\Handlers\ShipmentMessageDispatcher;
use App\Application\Handlers\ShipmentRequestedHandler;
use App\Application\Mappers\ShipmentMessageMapper;
use App\Domain\Delivery\Repositories\ShipmentRepository;
use App\Domain\Delivery\Services\DemoResetService;
use App\Domain\Delivery\Services\ShipmentLifecycleService;
use App\Http\Controllers\DebugController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\ShipmentController;
use App\Infrastructure\Messaging\RabbitMq\DeliveryRequestConsumer;
use App\Infrastructure\Messaging\RabbitMq\DeliveryRequestFailureHandler;
use App\Infrastructure\Messaging\RabbitMq\MessageHeaderValidator;
use App\Infrastructure\Messaging\RabbitMq\RabbitMqConfig;
use App\Infrastructure\Messaging\RabbitMq\RabbitMqConnectionFactory;
use App\Infrastructure\Messaging\RabbitMq\RabbitMqTopologyManager;
use App\Infrastructure\Persistence\InMemoryShipmentRepository;

final class ServiceDefinitions
{
    /**
     * @return array<string, mixed>
     */
    public static function all(): array
    {
        return array_merge(
            self::core(),
            self::messaging(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private static function core(): array
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

    /**
     * @return array<string, mixed>
     */
    private static function messaging(): array
    {
        return [
            RabbitMqConfig::class => static function ($container): RabbitMqConfig {
                /** @var array{rabbitmq: array<string, mixed>} $config */
                $config = $container->get('config');

                return RabbitMqConfig::fromArray($config['rabbitmq']);
            },
            RabbitMqConnectionFactory::class => static fn ($container): RabbitMqConnectionFactory => new RabbitMqConnectionFactory(
                $container->get(RabbitMqConfig::class),
            ),
            RabbitMqTopologyManager::class => static fn ($container): RabbitMqTopologyManager => new RabbitMqTopologyManager(
                $container->get(RabbitMqConfig::class),
            ),
            MessageHeaderValidator::class => static fn (): MessageHeaderValidator => new MessageHeaderValidator(),
            ShipmentMessageMapper::class => static fn (): ShipmentMessageMapper => new ShipmentMessageMapper(),
            ShipmentRequestedHandler::class => static fn ($container): ShipmentRequestedHandler => new ShipmentRequestedHandler(
                $container->get(ShipmentMessageMapper::class),
                $container->get(ShipmentLifecycleService::class),
            ),
            ShipmentCancelRequestedHandler::class => static fn ($container): ShipmentCancelRequestedHandler => new ShipmentCancelRequestedHandler(
                $container->get(ShipmentMessageMapper::class),
                $container->get(ShipmentLifecycleService::class),
            ),
            ShipmentMessageDispatcher::class => static fn ($container): ShipmentMessageDispatcher => new ShipmentMessageDispatcher(
                $container->get(ShipmentRequestedHandler::class),
                $container->get(ShipmentCancelRequestedHandler::class),
            ),
            DeliveryRequestFailureHandler::class => static fn (): DeliveryRequestFailureHandler => new DeliveryRequestFailureHandler(),
            DeliveryRequestConsumer::class => static fn ($container): DeliveryRequestConsumer => new DeliveryRequestConsumer(
                $container->get(RabbitMqConfig::class),
                $container->get(RabbitMqConnectionFactory::class),
                $container->get(RabbitMqTopologyManager::class),
                $container->get(MessageHeaderValidator::class),
                $container->get(ShipmentMessageDispatcher::class),
                $container->get(DeliveryRequestFailureHandler::class),
            ),
        ];
    }
}
