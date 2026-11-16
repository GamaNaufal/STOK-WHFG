<?php

return [
    // This stops new ownership changes and exceptions, never enforcement of existing reservations.
    'writes_enabled' => env('EXPORT_CUSTOMER_STOCK_WRITES_ENABLED', true),
];
