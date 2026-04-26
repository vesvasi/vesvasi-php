<?php

declare(strict_types=1);

/**
 * HTTP Request Tracking Example
 *
 * This example demonstrates how to track HTTP requests with custom HTTP attributes.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Vesvasi\Vesvasi;
use Vesvasi\Config\Config;

Config::reset();

Vesvasi::configure([
    'api_key' => 'your-api-key',
    'endpoint' => 'https://otlp.example.com:4318',
    'service' => [
        'name' => 'example-http',
        'version' => '1.0.0',
        'environment' => 'development',
    ],
]);

echo "HTTP Request Tracking Example\n";
echo "============================\n";

$vesvasi = Vesvasi::getInstance();
$httpIntegration = $vesvasi->instrumentation()->getHttpIntegration();

$simulatedClient = new class {
    public function get(string $url, array $headers = []): array {
        echo "  GET {$url}\n";
        return ['status' => 200, 'body' => '{"id": 1, "name": "John"}'];
    }

    public function post(string $url, array $data, array $headers = []): array {
        echo "  POST {$url}\n";
        return ['status' => 201, 'body' => '{"id": 2, "created": true}'];
    }

    public function put(string $url, array $data, array $headers = []): array {
        echo "  PUT {$url}\n";
        return ['status' => 200, 'body' => '{"updated": true}'];
    }

    public function delete(string $url, array $headers = []): array {
        echo "  DELETE {$url}\n";
        return ['status' => 204, 'body' => ''];
    }
};

$client = $simulatedClient;

echo "\n1. GET request (success):\n";
$result = $httpIntegration->traceRequest(
    'GET',
    'https://api.example.com/users/123',
    ['Accept' => 'application/json', 'Authorization' => 'Bearer token'],
    null,
    fn() => $client->get('https://api.example.com/users/123', ['Accept' => 'application/json'])
);
echo "  Response: " . $result['body'] . "\n";

echo "\n2. POST request (created):\n";
$result = $httpIntegration->traceRequest(
    'POST',
    'https://api.example.com/users',
    ['Content-Type' => 'application/json'],
    '{"name":"Jane","email":"jane@example.com"}',
    fn() => $client->post(
        'https://api.example.com/users',
        ['name' => 'Jane', 'email' => 'jane@example.com']
    )
);
echo "  Response: " . $result['body'] . "\n";

echo "\n3. PUT request (success):\n";
$result = $httpIntegration->traceRequest(
    'PUT',
    'https://api.example.com/users/123',
    ['Content-Type' => 'application/json'],
    '{"name":"John Updated"}',
    fn() => $client->put(
        'https://api.example.com/users/123',
        ['name' => 'John Updated']
    )
);
echo "  Response: " . $result['body'] . "\n";

echo "\n4. DELETE request (success):\n";
$result = $httpIntegration->traceRequest(
    'DELETE',
    'https://api.example.com/users/123',
    [],
    null,
    fn() => $client->delete('https://api.example.com/users/123')
);
echo "  Response: HTTP " . $result['status'] . "\n";

echo "\n5. Request with error response:\n";
$httpIntegration->traceRequest(
    'GET',
    'https://api.example.com/users/999',
    [],
    null,
    function () {
        echo "  Simulating 404 response\n";
        return ['status' => 404, 'body' => '{"error": "User not found"}'];
    }
);

echo "\n6. Server span (incoming request simulation):\n";
$serverSpan = $httpIntegration->createServerSpan(
    'POST',
    'https://api.example.com/webhooks/stripe',
    [
        'Content-Type' => 'application/json',
        'Stripe-Signature' => 'sig_xxx',
        'User-Agent' => 'Stripe/1.0',
    ],
    200
);
echo "  Created server span for webhook\n";

echo "\nAll HTTP requests tracked with vesvasi.http=true attribute!\n";

$serverSpan->end();
$vesvasi->shutdown();