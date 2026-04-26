<?php

declare(strict_types=1);

/**
 * SQL/Database Tracking Example
 *
 * This example demonstrates how to track database queries with custom SQL attributes.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Vesvasi\Vesvasi;
use Vesvasi\Config\Config;

Config::reset();

Vesvasi::configure([
    'api_key' => 'your-api-key',
    'endpoint' => 'https://otlp.example.com:4318',
    'service' => [
        'name' => 'example-sql',
        'version' => '1.0.0',
        'environment' => 'development',
    ],
]);

echo "SQL Tracking Example\n";
echo "===================\n";

$vesvasi = Vesvasi::getInstance();
$sqlIntegration = $vesvasi->instrumentation()->getSqlIntegration();

$simulatedDb = new class {
    public function query(string $sql): array {
        echo "  Executing: " . substr($sql, 0, 50) . "...\n";
        return [['id' => 1, 'name' => 'John']];
    }

    public function beginTransaction(): void {
        echo "  BEGIN TRANSACTION\n";
    }

    public function commit(): void {
        echo "  COMMIT\n";
    }

    public function rollback(): void {
        echo "  ROLLBACK\n";
    }
};

$db = $simulatedDb;

echo "\n1. Tracing a SELECT query:\n";
$result = $sqlIntegration->traceQuery(
    'SELECT id, name FROM users WHERE status = ? LIMIT ?',
    'production_db',
    'mysql',
    ['active', 10],
    fn() => $db->query('SELECT id, name FROM users WHERE status = \'active\' LIMIT 10')
);
echo "  Result: " . count($result) . " users found\n";

echo "\n2. Tracing an INSERT query:\n";
$sqlIntegration->traceExecute(
    'INSERT INTO logs (level, message, created_at) VALUES (?, ?, NOW())',
    ['info', 'User action logged', 'info'],
    'production_db',
    'mysql',
    fn() => $db->query('INSERT INTO logs ...')
);
echo "  Record inserted\n";

echo "\n3. Tracing a transaction (success):\n";
$sqlIntegration->traceTransaction(
    function () use ($db) {
        $db->beginTransaction();
        $db->query('UPDATE accounts SET balance = balance - 100 WHERE id = 1');
        $db->query('UPDATE accounts SET balance = balance + 100 WHERE id = 2');
        $db->commit();
    },
    'production_db',
    'mysql'
);
echo "  Transaction committed\n";

echo "\n4. Tracing a transaction (with rollback simulation):\n";
try {
    $sqlIntegration->traceTransaction(
        function () use ($db) {
            $db->beginTransaction();
            $db->query('DELETE FROM orders WHERE id = 999');
            throw new RuntimeException('Order not found - rolling back');
        },
        'production_db',
        'mysql'
    );
} catch (RuntimeException $e) {
    echo "  Caught expected exception: " . $e->getMessage() . "\n";
}

echo "\n5. Creating a manual SQL span:\n";
$span = $sqlIntegration->createSqlSpan(
    'UPDATE',
    'UPDATE users SET last_login = NOW() WHERE id = ?',
    'production_db',
    'postgresql',
    [123]
);
echo "  Created span for UPDATE query\n";
$span->end();

echo "\nAll SQL operations tracked with vesvasi.sql=true attribute!\n";

$vesvasi->shutdown();