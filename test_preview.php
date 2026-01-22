<?php
require 'vendor/autoload.php';

// Load Laravel
$app = require_once 'bootstrap/app.php';

// Make HTTP request
$url = 'http://localhost:8000/stock-withdrawal/preview-cart';
$data = [
    'items' => [
        [
            'part_number' => '71053641',
            'pcs_quantity' => 100
        ]
    ]
];

// Use Guzzle HTTP or cURL
$client = new \GuzzleHttp\Client();

try {
    $response = $client->post($url, [
        'form_params' => $data,
        'headers' => [
            'X-Requested-With' => 'XMLHttpRequest',
            'Content-Type' => 'application/x-www-form-urlencoded'
        ]
    ]);
    
    echo "Response Status: " . $response->getStatusCode() . "\n";
    echo "Response Body:\n";
    echo $response->getBody();
    
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
