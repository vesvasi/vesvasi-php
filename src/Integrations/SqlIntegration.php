<?php

declare(strict_types=1);

namespace Vesvasi\Integrations;

use Vesvasi\Config\Config;
use Vesvasi\Trace\VesvasiTracer;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;

final class SqlIntegration
{
    private Config $config;
    private VesvasiTracer $tracer;
    private array $activeSpans = [];
    private array $pdoAttributes = [];
    private ?string $lastDbName = null;

    private const ATTR_SQL_COMMAND = 'db.statement';
    private const ATTR_SQL_DRIVER = 'db.driver';
    private const ATTR_SQL_OPERATION = 'db.operation';
    private const ATTR_SQL_BINDINGS = 'db.statement.bindings';
    private const ATTR_SQL_ROWS_AFFECTED = 'db.rows_affected';
    private const ATTR_SQL_TYPE = 'db.type';
    private const ATTR_VESVASI_SQL = 'vesvasi.sql';
    private const ATTR_VESVASI_DB_NAME = 'vesvasi.db.name';

    public function __construct(Config $config, VesvasiTracer $tracer)
    {
        $this->config = $config;
        $this->tracer = $tracer;
    }

    public function register(): void
    {
        if (!extension_loaded('PDO')) {
            return;
        }

        $this->pdoAttributes = $this->detectPdoDrivers();
    }

    public function traceQuery(
        string $query,
        ?string $dbName = null,
        ?string $driver = null,
        ?array $bindings = null,
        ?callable $callback = null
    ): mixed {
        $span = $this->createSqlSpan('query', $query, $dbName, $driver, $bindings);

        try {
            $result = $callback !== null ? $callback() : null;

            $this->endSpanSuccess($span);
            return $result;
        } catch (\Throwable $e) {
            $this->endSpanError($span, $e);
            throw $e;
        }
    }

    public function traceTransaction(
        callable $callback,
        ?string $dbName = null,
        ?string $driver = null
    ): mixed {
        $span = $this->createSqlSpan('transaction', 'BEGIN', $dbName, $driver);

        try {
            $result = $callback();

            $this->endSpanSuccess($span, 'COMMIT');
            return $result;
        } catch (\Throwable $e) {
            $this->endSpanError($span, $e, 'ROLLBACK');
            throw $e;
        }
    }

    public function traceExecute(
        string $statement,
        ?array $params = null,
        ?string $dbName = null,
        ?string $driver = null,
        ?callable $callback = null
    ): mixed {
        $span = $this->createSqlSpan('execute', $statement, $dbName, $driver, $params);

        try {
            $result = $callback !== null ? $callback() : null;

            if ($result !== null && is_int($result)) {
                $span->setAttribute(self::ATTR_SQL_ROWS_AFFECTED, $result);
            }

            $this->endSpanSuccess($span);
            return $result;
        } catch (\Throwable $e) {
            $this->endSpanError($span, $e);
            throw $e;
        }
    }

    public function createSqlSpan(
        string $operation,
        string $sql,
        ?string $dbName = null,
        ?string $driver = null,
        ?array $bindings = null
    ): SpanInterface {
        $spanName = "SQL {$operation}";

        if ($dbName !== null) {
            $spanName .= " [{$dbName}]";
        }

        $spanBuilder = $this->tracer->spanBuilder($spanName)
            ->setSpanKind(SpanKind::KIND_CLIENT)
            ->setAttribute(self::ATTR_VESVASI_SQL, true)
            ->setAttribute(self::ATTR_SQL_TYPE, 'sql')
            ->setAttribute(self::ATTR_SQL_OPERATION, strtoupper($operation))
            ->setAttribute(self::ATTR_SQL_COMMAND, $this->sanitizeSql($sql));

        if ($dbName !== null) {
            $spanBuilder->setAttribute('db.name', $dbName);
            $spanBuilder->setAttribute(self::ATTR_VESVASI_DB_NAME, $dbName);
            $this->lastDbName = $dbName;
        }

        if ($driver !== null) {
            $spanBuilder->setAttribute(self::ATTR_SQL_DRIVER, $driver);
            $spanBuilder->setAttribute('db.system', $this->mapDriverToSystem($driver));
        }

        if ($bindings !== null && !empty($bindings)) {
            $spanBuilder->setAttribute(
                self::ATTR_SQL_BINDINGS,
                $this->sanitizeBindings($bindings)
            );
        }

        return $spanBuilder->startSpan();
    }

    private function endSpanSuccess(SpanInterface $span, ?string $additionalCommand = null): void
    {
        if ($additionalCommand !== null) {
            $span->setAttribute(self::ATTR_SQL_COMMAND, $additionalCommand);
        }

        $span->setStatus(StatusCode::STATUS_OK);
        $span->end();
    }

    private function endSpanError(SpanInterface $span, \Throwable $exception, ?string $rollbackCommand = null): void
    {
        if ($rollbackCommand !== null) {
            $span->setAttribute(self::ATTR_SQL_COMMAND, 'ROLLBACK');
        }

        $span->recordException($exception);
        $span->setStatus(StatusCode::STATUS_ERROR, $exception->getMessage());
        $span->end();
    }

    private function sanitizeSql(string $sql): string
    {
        $sql = trim($sql);
        $maxLength = 10000;

        if (strlen($sql) > $maxLength) {
            return substr($sql, 0, $maxLength) . '... [truncated]';
        }

        return $sql;
    }

    private function sanitizeBindings(array $bindings): string
    {
        $sanitized = [];
        foreach ($bindings as $key => $value) {
            if (is_string($value)) {
                $sanitized[$key] = mb_strlen($value) > 100
                    ? mb_substr($value, 0, 100) . '...'
                    : $value;
            } else {
                $sanitized[$key] = $value;
            }
        }

        return json_encode($sanitized, JSON_UNESCAPED_UNICODE);
    }

    private function mapDriverToSystem(string $driver): string
    {
        $driver = strtolower($driver);

        return match (true) {
            str_contains($driver, 'mysql') => 'mysql',
            str_contains($driver, 'pgsql') => 'postgresql',
            str_contains($driver, 'sqlite') => 'sqlite',
            str_contains($driver, 'sqlsrv') => 'mssql',
            str_contains($driver, 'oracle') => 'oracle',
            str_contains($driver, 'firebird') => 'firebird',
            default => 'sql',
        };
    }

    private function detectPdoDrivers(): array
    {
        if (!extension_loaded('PDO')) {
            return [];
        }

        return \PDO::getAvailableDrivers();
    }

    public function getLastDbName(): ?string
    {
        return $this->lastDbName;
    }

    public function getActiveSpansCount(): int
    {
        return count($this->activeSpans);
    }

    public function shutdown(): void
    {
        foreach ($this->activeSpans as $span) {
            $span->end();
        }
        $this->activeSpans = [];
    }
}