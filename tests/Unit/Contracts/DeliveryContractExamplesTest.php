<?php

declare(strict_types=1);

namespace Tests\Unit\Contracts;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DeliveryContractExamplesTest extends TestCase
{
    private static function contractsRoot(): string
    {
        return dirname(__DIR__, 3).'/contracts';
    }

    /**
     * @return list<array{string}>
     */
    public static function exampleFilesProvider(): array
    {
        $files = glob(self::contractsRoot().'/examples/delivery.*.v1.json') ?: [];

        return array_map(
            static fn (string $file): array => [basename($file)],
            $files,
        );
    }

    /**
     * @return list<array{string}>
     */
    public static function schemaFilesProvider(): array
    {
        $files = glob(self::contractsRoot().'/messages/delivery.*.v1.json') ?: [];

        return array_map(
            static fn (string $file): array => [basename($file)],
            $files,
        );
    }

    #[DataProvider('exampleFilesProvider')]
    public function test_example_files_have_required_headers_and_payload(string $filename): void
    {
        $contents = json_decode(
            (string) file_get_contents(self::contractsRoot().'/examples/'.$filename),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        $this->assertArrayHasKey('headers', $contents);
        $this->assertArrayHasKey('payload', $contents);

        foreach ([
            'message_id',
            'correlation_id',
            'causation_id',
            'idempotency_key',
            'schema_version',
            'occurred_at',
            'producer',
        ] as $header) {
            $this->assertArrayHasKey($header, $contents['headers'], $filename);
        }

        $this->assertSame('v1', $contents['headers']['schema_version']);
    }

    #[DataProvider('schemaFilesProvider')]
    public function test_schema_files_are_valid_json_schema_documents(string $filename): void
    {
        $contents = json_decode(
            (string) file_get_contents(self::contractsRoot().'/messages/'.$filename),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        $this->assertSame('https://json-schema.org/draft/2020-12/schema', $contents['$schema']);
        $this->assertArrayHasKey('title', $contents);
        $this->assertArrayHasKey('type', $contents);
    }

    public function test_asyncapi_contract_exists_and_lists_all_messages(): void
    {
        $yaml = file_get_contents(self::contractsRoot().'/asyncapi.yaml');

        $this->assertNotFalse($yaml);

        foreach ([
            'delivery.shipment.requested.v1',
            'delivery.shipment.cancel_requested.v1',
            'delivery.shipment.created.v1',
            'delivery.shipment.creation_failed.v1',
            'delivery.shipment.status_changed.v1',
            'delivery.shipment.cancelled.v1',
            'delivery.shipment.cancel_failed.v1',
        ] as $message) {
            $this->assertStringContainsString($message, (string) $yaml);
        }
    }

    public function test_common_header_schema_lists_required_delivery_headers(): void
    {
        $contents = json_decode(
            (string) file_get_contents(self::contractsRoot().'/messages/common/message-headers.json'),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        $this->assertSame([
            'message_id',
            'correlation_id',
            'causation_id',
            'idempotency_key',
            'schema_version',
            'occurred_at',
            'producer',
        ], $contents['required']);
    }
}
