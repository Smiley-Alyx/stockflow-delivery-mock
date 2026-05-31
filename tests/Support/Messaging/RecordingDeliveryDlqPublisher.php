<?php

declare(strict_types=1);

namespace Tests\Support\Messaging;

use App\Infrastructure\Messaging\RabbitMq\DeliveryDlqPublisher;
use App\Infrastructure\Messaging\RabbitMq\RabbitMqConfig;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;
use Throwable;

final class RecordingDeliveryDlqPublisher extends DeliveryDlqPublisher
{
    /** @var list<array{channel: AMQPChannel, message: AMQPMessage, exception: Throwable}> */
    public array $published = [];

    public function __construct()
    {
        parent::__construct(RabbitMqTestConfig::make());
    }

    public function publish(AMQPChannel $channel, AMQPMessage $message, Throwable $exception): void
    {
        $this->published[] = [
            'channel' => $channel,
            'message' => $message,
            'exception' => $exception,
        ];
    }
}
