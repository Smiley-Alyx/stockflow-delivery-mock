<?php

declare(strict_types=1);

namespace App\Domain\Delivery\Models;

final readonly class DeliveryAddress
{
    public string $recipientName;

    public string $countryCode;

    public string $city;

    public string $postalCode;

    public string $streetLine1;

    public ?string $streetLine2;

    public ?string $region;

    public ?string $phone;

    public function __construct(
        string $recipientName,
        string $countryCode,
        string $city,
        string $postalCode,
        string $streetLine1,
        ?string $streetLine2 = null,
        ?string $region = null,
        ?string $phone = null,
    ) {
        $this->recipientName = trim($recipientName);
        $this->countryCode = strtoupper(trim($countryCode));
        $this->city = trim($city);
        $this->postalCode = trim($postalCode);
        $this->streetLine1 = trim($streetLine1);
        $this->streetLine2 = $streetLine2 !== null ? trim($streetLine2) : null;
        $this->region = $region !== null ? trim($region) : null;
        $this->phone = $phone !== null ? trim($phone) : null;

        if ($this->recipientName === '') {
            throw new \InvalidArgumentException('Recipient name must not be empty.');
        }

        if (! preg_match('/^[A-Z]{2}$/', $this->countryCode)) {
            throw new \InvalidArgumentException('Country code must be a two-letter ISO code.');
        }

        if ($this->city === '') {
            throw new \InvalidArgumentException('City must not be empty.');
        }

        if ($this->postalCode === '') {
            throw new \InvalidArgumentException('Postal code must not be empty.');
        }

        if ($this->streetLine1 === '') {
            throw new \InvalidArgumentException('Street line 1 must not be empty.');
        }
    }

    /**
     * @param array{
     *     recipient_name: string,
     *     country_code: string,
     *     city: string,
     *     postal_code: string,
     *     street_line1: string,
     *     street_line2?: string|null,
     *     region?: string|null,
     *     phone?: string|null
     * } $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            recipientName: $data['recipient_name'],
            countryCode: $data['country_code'],
            city: $data['city'],
            postalCode: $data['postal_code'],
            streetLine1: $data['street_line1'],
            streetLine2: $data['street_line2'] ?? null,
            region: $data['region'] ?? null,
            phone: $data['phone'] ?? null,
        );
    }

    /**
     * @return array{
     *     recipient_name: string,
     *     country_code: string,
     *     city: string,
     *     postal_code: string,
     *     street_line1: string,
     *     street_line2: string|null,
     *     region: string|null,
     *     phone: string|null
     * }
     */
    public function toArray(): array
    {
        return [
            'recipient_name' => $this->recipientName,
            'country_code' => $this->countryCode,
            'city' => $this->city,
            'postal_code' => $this->postalCode,
            'street_line1' => $this->streetLine1,
            'street_line2' => $this->streetLine2,
            'region' => $this->region,
            'phone' => $this->phone,
        ];
    }
}
