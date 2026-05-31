<?php

declare(strict_types=1);

namespace App\Domain\Delivery\Models;

final readonly class CarrierProfile
{
    public string $carrierCode;

    public string $serviceLevel;

    public function __construct(
        string $carrierCode,
        string $serviceLevel,
    ) {
        $this->carrierCode = trim($carrierCode);
        $this->serviceLevel = trim($serviceLevel);

        if ($this->carrierCode === '') {
            throw new \InvalidArgumentException('Carrier code must not be empty.');
        }

        if ($this->serviceLevel === '') {
            throw new \InvalidArgumentException('Service level must not be empty.');
        }
    }

    /**
     * @param array{carrier_code: string, service_level: string} $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            carrierCode: $data['carrier_code'],
            serviceLevel: $data['service_level'],
        );
    }

    /**
     * @return array{carrier_code: string, service_level: string}
     */
    public function toArray(): array
    {
        return [
            'carrier_code' => $this->carrierCode,
            'service_level' => $this->serviceLevel,
        ];
    }
}
