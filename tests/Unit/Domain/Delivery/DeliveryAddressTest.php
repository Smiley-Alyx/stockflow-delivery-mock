<?php

declare(strict_types=1);

use App\Domain\Delivery\Models\DeliveryAddress;

test('delivery address normalizes country code and trims fields', function (): void {
    $address = new DeliveryAddress(
        recipientName: '  Jane Doe  ',
        countryCode: ' ru ',
        city: ' Moscow ',
        postalCode: ' 101000 ',
        streetLine1: ' Red Square 1 ',
        streetLine2: ' Apt 2 ',
        region: ' Moscow Oblast ',
        phone: ' +79990001122 ',
    );

    expect($address->recipientName)->toBe('Jane Doe')
        ->and($address->countryCode)->toBe('RU')
        ->and($address->city)->toBe('Moscow')
        ->and($address->postalCode)->toBe('101000')
        ->and($address->streetLine1)->toBe('Red Square 1')
        ->and($address->streetLine2)->toBe('Apt 2')
        ->and($address->region)->toBe('Moscow Oblast')
        ->and($address->phone)->toBe('+79990001122');
});

test('delivery address round trips through array representation', function (): void {
    $address = DeliveryAddress::fromArray([
        'recipient_name' => 'Jane Doe',
        'country_code' => 'RU',
        'city' => 'Moscow',
        'postal_code' => '101000',
        'street_line1' => 'Red Square 1',
        'street_line2' => 'Apt 2',
        'region' => 'Moscow Oblast',
        'phone' => '+79990001122',
    ]);

    expect($address->toArray())->toBe([
        'recipient_name' => 'Jane Doe',
        'country_code' => 'RU',
        'city' => 'Moscow',
        'postal_code' => '101000',
        'street_line1' => 'Red Square 1',
        'street_line2' => 'Apt 2',
        'region' => 'Moscow Oblast',
        'phone' => '+79990001122',
    ]);
});

test('delivery address rejects invalid country code', function (): void {
    new DeliveryAddress(
        recipientName: 'Jane Doe',
        countryCode: 'RUS',
        city: 'Moscow',
        postalCode: '101000',
        streetLine1: 'Red Square 1',
    );
})->throws(InvalidArgumentException::class, 'Country code must be a two-letter ISO code.');

test('delivery address rejects empty recipient name', function (): void {
    new DeliveryAddress(
        recipientName: '   ',
        countryCode: 'RU',
        city: 'Moscow',
        postalCode: '101000',
        streetLine1: 'Red Square 1',
    );
})->throws(InvalidArgumentException::class, 'Recipient name must not be empty.');
