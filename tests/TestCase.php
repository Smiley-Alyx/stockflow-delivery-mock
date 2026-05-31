<?php

declare(strict_types=1);

namespace Tests;

use App\Bootstrap\AppFactory;
use App\Domain\Delivery\Services\ShipmentLifecycleService;
use PHPUnit\Framework\TestCase as BaseTestCase;
use Psr\Container\ContainerInterface;
use Slim\App;

abstract class TestCase extends BaseTestCase
{
    public App $app;

    public ContainerInterface $container;

    public ShipmentLifecycleService $shipments;

    protected function setUp(): void
    {
        parent::setUp();

        putenv('DELIVERY_MOCK_DEBUG_ENABLED=true');

        $this->container = AppFactory::buildContainer();
        $this->app = AppFactory::create($this->container);
        $this->shipments = $this->container->get(ShipmentLifecycleService::class);
    }
}
