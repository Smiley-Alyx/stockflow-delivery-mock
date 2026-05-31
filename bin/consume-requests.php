#!/usr/bin/env php
<?php

declare(strict_types=1);

use App\Bootstrap\AppFactory;
use App\Infrastructure\Messaging\RabbitMq\DeliveryRequestConsumer;
use Dotenv\Dotenv;

require __DIR__.'/../vendor/autoload.php';

$dotenv = Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad();

$container = AppFactory::buildContainer();
$consumer = $container->get(DeliveryRequestConsumer::class);

exit($consumer->consume());
