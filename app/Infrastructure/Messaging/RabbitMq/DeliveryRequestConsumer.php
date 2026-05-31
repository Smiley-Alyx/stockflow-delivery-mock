<?php

declare(strict_types=1);

namespace App\Infrastructure\Messaging\RabbitMq;

use App\Application\Handlers\ShipmentMessageDispatcher;
use App\Infrastructure\Messaging\RabbitMq\Exceptions\InvalidMessageException;
use App\Support\DeliveryStructuredLogger;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;
use Throwable;

final class DeliveryRequestConsumer
{
    public const SUCCESS = 0;

    private bool $shouldStop = false;

    public function __construct(
        private readonly RabbitMqConfig $config,
        private readonly RabbitMqConnectionFactory $connectionFactory,
        private readonly RabbitMqTopologyManager $topologyManager,
        private readonly MessageHeaderValidator $headerValidator,
        private readonly ShipmentMessageDispatcher $dispatcher,
        private readonly DeliveryRequestFailureHandler $failureHandler,
        private readonly RabbitMqMessagePublisher $messagePublisher,
    ) {
    }

    public function consume(): int
    {
        $this->registerSignalHandlers();

        $connection = $this->connectionFactory->create();
        $channel = $connection->channel();

        try {
            if ($this->config->setupTopology) {
                $this->topologyManager->declare($channel);
            }

            $channel->basic_qos(0, $this->config->prefetchCount, false);

            $channel->basic_consume(
                queue: $this->config->requestsQueue,
                consumer_tag: '',
                no_local: false,
                no_ack: false,
                exclusive: false,
                nowait: false,
                callback: function (AMQPMessage $message) use ($channel): void {
                    $this->handleRequestMessage($channel, $message);
                },
            );

            DeliveryStructuredLogger::info('delivery request consumer started', [
                'queue' => $this->config->requestsQueue,
                'exchange' => $this->config->exchange,
            ]);

            while ($channel->is_consuming() && ! $this->shouldStop) {
                $channel->wait(null, false, $this->config->consumerTimeoutSeconds);
            }

            DeliveryStructuredLogger::info('delivery request consumer stopped gracefully');

            return self::SUCCESS;
        } finally {
            $this->messagePublisher->close();
            $channel->close();
            $connection->close();
        }
    }

    public function requestStop(): void
    {
        $this->shouldStop = true;
    }

    public function shouldStop(): bool
    {
        return $this->shouldStop;
    }

    private function handleRequestMessage(AMQPChannel $channel, AMQPMessage $message): void
    {
        try {
            $incoming = IncomingMessage::fromAmqpMessage($message, $this->headerValidator);

            DeliveryStructuredLogger::info('delivery message received', [
                'routing_key' => $incoming->routingKey,
                'correlation_id' => $incoming->headers->correlationId,
                'message_id' => $incoming->headers->messageId,
            ]);

            $this->dispatcher->dispatch($incoming);

            $channel->basic_ack($message->getDeliveryTag());
        } catch (InvalidMessageException $exception) {
            $this->failureHandler->handleInvalidMessage($channel, $message, $exception);
        } catch (Throwable $exception) {
            $this->failureHandler->handleProcessingFailure($channel, $message, $exception);
        }
    }

    private function registerSignalHandlers(): void
    {
        if (! extension_loaded('pcntl')) {
            return;
        }

        pcntl_async_signals(true);

        $handler = function (int $signal): void {
            DeliveryStructuredLogger::info('delivery request consumer received shutdown signal', [
                'signal' => $signal,
            ]);

            $this->requestStop();
        };

        pcntl_signal(SIGTERM, $handler);
        pcntl_signal(SIGINT, $handler);
    }
}
