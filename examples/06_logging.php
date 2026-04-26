<?php

declare(strict_types=1);

/**
 * Structured Logging Example
 *
 * This example demonstrates how to use structured logging with Vesvasi.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Vesvasi\Vesvasi;
use Vesvasi\Config\Config;

Config::reset();

Vesvasi::configure([
    'api_key' => 'your-api-key',
    'endpoint' => 'https://otlp.example.com:4318',
    'service' => [
        'name' => 'example-logging',
        'version' => '1.0.0',
        'environment' => 'development',
    ],
    'logs' => [
        'enabled' => true,
        'levels' => ['debug', 'info', 'notice', 'warning', 'error', 'critical'],
        'include_context' => true,
        'include_stack_trace' => true,
        'redact_fields' => ['password', 'token', 'secret', 'api_key', 'credit_card'],
    ],
]);

echo "Structured Logging Example\n";
echo "=========================\n";

$vesvasi = Vesvasi::getInstance();
$logger = $vesvasi->logger();

echo "\n1. Basic logging levels:\n";
$logger->debug('Debug message', ['key' => 'value']);
echo "  [debug] Debug message\n";

$logger->info('Info message', ['request_id' => 'abc123']);
echo "  [info] Info message\n";

$logger->notice('Notice message');
echo "  [notice] Notice message\n";

$logger->warning('Warning message', ['deprecated' => true]);
echo "  [warning] Warning message\n";

$logger->error('Error message', ['error_code' => 'E001']);
echo "  [error] Error message\n";

$logger->critical('Critical message', ['system' => 'database']);
echo "  [critical] Critical message\n";

echo "\n2. Logging with sensitive data (redacted):\n";
$logger->info('User login attempt', [
    'user_id' => 123,
    'email' => 'user@example.com',
    'password' => 'secret123',  // Will be redacted
    'token' => 'jwt_token_xyz',   // Will be redacted
    'api_key' => 'sk_live_xxx',  // Will be redacted
]);
echo "  Logged with redacted sensitive fields\n";

echo "\n3. Logging with channel:\n";
$authLogger = $logger->withChannel('auth');
$authLogger->warning('Invalid token', ['user_id' => 456]);

$apiLogger = $logger->withChannel('api');
$apiLogger->info('API request received', ['endpoint' => '/api/users']);

echo "\n4. Logging with persistent context:\n";
$contextLogger = $logger->withContext([
    'request_id' => 'req_' . uniqid(),
    'trace_id' => 'trace_abc123',
    'user_id' => 789,
]);

$contextLogger->info('Request processing started');
$contextLogger->info('Request validation passed');
$contextLogger->info('Request processing completed');

echo "\n5. Using static log helper:\n";
Vesvasi::log('info', 'Application started', ['version' => '1.0.0']);
Vesvasi::log('warning', 'Deprecated endpoint called', ['endpoint' => '/api/v1/users']);
Vesvasi::log('error', 'Failed to connect to database', ['host' => 'db.example.com']);

echo "\n6. Error logging with exception:\n";
$logger->error('Operation failed', [
    'operation' => 'process_payment',
    'amount' => 99.99,
    'user_id' => 123,
], new RuntimeException('Payment gateway timeout'));

echo "\n7. Security-related logging:\n";
$logger->warning('Login attempt', [
    'email' => 'user@example.com',
    'ip' => '192.168.1.1',
    'user_agent' => 'Mozilla/5.0',
    'success' => false,
    'reason' => 'invalid_password',
]);

$logger->warning('Rate limit exceeded', [
    'ip' => '10.0.0.1',
    'endpoint' => '/api/auth/login',
    'attempts' => 5,
]);

echo "\nAll logs tracked with structured context!\n";

$vesvasi->logProcessor()->flush();
$vesvasi->shutdown();