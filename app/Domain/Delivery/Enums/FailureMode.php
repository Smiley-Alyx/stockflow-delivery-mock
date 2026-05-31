<?php

declare(strict_types=1);

namespace App\Domain\Delivery\Enums;

enum FailureMode: string
{
    case Normal = 'normal';
    case AlwaysRejectCreation = 'always_reject_creation';
    case RandomRejectCreation = 'random_reject_creation';
    case InvalidAddress = 'invalid_address';
    case ProcessingDelay = 'processing_delay';
    case ProviderUnavailable = 'provider_unavailable';
    case Timeout = 'timeout';
    case CancelFailure = 'cancel_failure';
    case DuplicateResponse = 'duplicate_response';
    case PublishFailure = 'publish_failure';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $mode): string => $mode->value,
            self::cases(),
        );
    }
}
