<?php

declare(strict_types=1);

namespace App\Infrastructure\Messaging\RabbitMq;

use App\Infrastructure\Messaging\RabbitMq\Exceptions\InvalidMessageException;
use PhpAmqpLib\Message\AMQPMessage;

final readonly class IncomingMessage
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        public string $routingKey,
        public MessageHeaders $headers,
        public array $payload,
        public string $body,
    ) {
    }

    public static function fromAmqpMessage(AMQPMessage $message, MessageHeaderValidator $validator): self
    {
        $routingKey = (string) ($message->getRoutingKey() ?? '');

        if ($routingKey === '') {
            throw new InvalidMessageException('Missing routing key.');
        }

        $body = (string) $message->getBody();

        if ($body === '') {
            throw new InvalidMessageException('Message body must not be empty.');
        }

        $payload = json_decode($body, true);

        if (! is_array($payload)) {
            throw new InvalidMessageException('Message body must be valid JSON object.');
        }

        $headers = $validator->validate($message->get_properties());

        return new self(
            routingKey: $routingKey,
            headers: $headers,
            payload: $payload,
            body: $body,
        );
    }
}
