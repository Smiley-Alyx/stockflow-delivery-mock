<?php

declare(strict_types=1);

return [
    'service_name' => getenv('DELIVERY_MOCK_SERVICE_NAME') ?: 'stockflow-delivery-mock',
    'http_port' => (int) (getenv('DELIVERY_MOCK_HTTP_PORT') ?: 8080),
    'debug_enabled' => filter_var(getenv('DELIVERY_MOCK_DEBUG_ENABLED') ?: 'false', FILTER_VALIDATE_BOOL),
];
