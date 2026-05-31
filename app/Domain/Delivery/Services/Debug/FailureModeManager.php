<?php

declare(strict_types=1);

namespace App\Domain\Delivery\Services\Debug;

use App\Domain\Delivery\Enums\FailureMode;

final class FailureModeManager
{
    public function __construct(
        private readonly string $stateFilePath,
    ) {
    }

    public function current(): FailureMode
    {
        if (! is_file($this->stateFilePath)) {
            return FailureMode::Normal;
        }

        $contents = file_get_contents($this->stateFilePath);

        if ($contents === false || trim($contents) === '') {
            return FailureMode::Normal;
        }

        /** @var array{mode?: string}|null $decoded */
        $decoded = json_decode($contents, true);

        return FailureMode::tryFrom((string) ($decoded['mode'] ?? '')) ?? FailureMode::Normal;
    }

    public function set(FailureMode $mode): FailureMode
    {
        $this->writeState($mode);

        return $mode;
    }

    public function reset(): FailureMode
    {
        if (is_file($this->stateFilePath)) {
            unlink($this->stateFilePath);
        }

        return FailureMode::Normal;
    }

    private function writeState(FailureMode $mode): void
    {
        $directory = dirname($this->stateFilePath);

        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new \RuntimeException(sprintf('Unable to create failure mode state directory: %s', $directory));
        }

        $encoded = json_encode(['mode' => $mode->value], JSON_THROW_ON_ERROR);

        if (file_put_contents($this->stateFilePath, $encoded) === false) {
            throw new \RuntimeException(sprintf('Unable to write failure mode state file: %s', $this->stateFilePath));
        }
    }
}
