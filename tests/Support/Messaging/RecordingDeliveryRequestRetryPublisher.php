<?php

declare(strict_types=1);

namespace Tests\Support\Messaging;

use App\Infrastructure\Messaging\RabbitMq\DeliveryRequestRetryPublisher;
use App\Infrastructure\Messaging\RabbitMq\RabbitMqConfig;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;

final class RecordingDeliveryRequestRetryPublisher extends DeliveryRequestRetryPublisher
{
    /** @var list<array{channel: AMQPChannel, message: AMQPMessage, retryCount: int}> */
    public array $published = [];

    public function __construct()
    {
        parent::__construct(RabbitMqTestConfig::make());
    }

    public function publish(AMQPChannel $channel, AMQPMessage $message, int $retryCount): void
    {
        $this->published[] = [
            'channel' => $channel,
            'message' => $message,
            'retryCount' => $retryCount,
        ];
    }
}
