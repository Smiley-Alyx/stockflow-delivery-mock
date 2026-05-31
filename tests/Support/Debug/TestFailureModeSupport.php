<?php

declare(strict_types=1);

namespace Tests\Support\Debug;

use App\Domain\Delivery\Services\Debug\DeliveryDegradationSimulator;
use App\Domain\Delivery\Services\Debug\FailureModeManager;

final class TestFailureModeSupport
{
    public static function manager(?string $stateFilePath = null): FailureModeManager
    {
        return new FailureModeManager($stateFilePath ?? self::tempStateFile());
    }

    public static function simulator(?FailureModeManager $manager = null): DeliveryDegradationSimulator
    {
        return new DeliveryDegradationSimulator($manager ?? self::manager(), 0);
    }

    public static function tempStateFile(): string
    {
        return sys_get_temp_dir() . '/stockflow-delivery-mock-failure-mode-test.json';
    }

    public static function resetStateFile(): void
    {
        $path = self::tempStateFile();

        if (is_file($path)) {
            unlink($path);
        }
    }
}
