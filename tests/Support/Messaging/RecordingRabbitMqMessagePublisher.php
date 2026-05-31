<?php

declare(strict_types=1);

namespace Tests\Support\Messaging;

use App\Infrastructure\Messaging\RabbitMq\PublishedDeliveryEvent;
use App\Infrastructure\Messaging\RabbitMq\RabbitMqMessagePublisher;

final class RecordingRabbitMqMessagePublisher extends RabbitMqMessagePublisher
{
    /** @var list<PublishedDeliveryEvent> */
    public array $published = [];

    public function __construct()
    {
    }

    public function publish(PublishedDeliveryEvent $event): void
    {
        $this->published[] = $event;
    }

    public function close(): void
    {
    }
}
