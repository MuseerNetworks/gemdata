<?php

declare(strict_types=1);

namespace GemData\Classes;

use PDO;
use PDOException;
use RuntimeException;

class Database
{
    private PDO $pdo;
    private ?AppLogger $logger;

    public function __construct(array $config, ?AppLogger $logger = null)
    {
        $this->logger = $logger;
        foreach (['host', 'port', 'dbname', 'username', 'password', 'charset'] as $key) {
            if (!array_key_exists($key, $config)) {
                throw new RuntimeException('Database configuration is incomplete.');
            }
        }

        $charset = (string) $config['charset'];
        if ($charset === '') {
            throw new RuntimeException('Database configuration is incomplete.');
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            $config['host'],
            $config['port'],
            $config['dbname'],
            $charset
        );

        try {
            $this->pdo = new PDO($dsn, (string) $config['username'], (string) $config['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_STRINGIFY_FETCHES => false,
            ]);
        } catch (PDOException $exception) {
            $this->logger?->error('Database connection failed.', [
                'host' => (string) $config['host'],
                'port' => (string) $config['port'],
                'dbname' => (string) $config['dbname'],
                'exception' => $exception->getMessage(),
            ]);
            throw new RuntimeException('Database connection failed.', 0, $exception);
        }
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    public function query(string $sql, array $params = []): array
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
        return $statement->fetchAll();
    }

    public function first(string $sql, array $params = []): ?array
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
        $row = $statement->fetch();
        return $row === false ? null : $row;
    }

    public function execute(string $sql, array $params = []): bool
    {
        $statement = $this->pdo->prepare($sql);
        return $statement->execute($params);
    }

    public function lastInsertId(): int
    {
        return (int) $this->pdo->lastInsertId();
    }

    public function beginTransaction(): void
    {
        if (!$this->pdo->inTransaction()) {
            $this->pdo->beginTransaction();
        }
    }

    public function commit(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->commit();
        }
    }

    public function rollBack(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }

    public function inTransaction(): bool
    {
        return $this->pdo->inTransaction();
    }

    /**
     * Check if a table exists in the current database.
     */
    public function tableExists(string $tableName): bool
    {
        try {
            $row = $this->first(
                'SELECT COUNT(*) AS cnt FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name',
                ['table_name' => $tableName]
            );
            return ((int) ($row['cnt'] ?? 0)) > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function columnExists(string $tableName, string $columnName): bool
    {
        try {
            $row = $this->first(
                'SELECT COUNT(*) AS cnt
                 FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = :table_name
                   AND COLUMN_NAME = :column_name',
                ['table_name' => $tableName, 'column_name' => $columnName]
            );
            return ((int) ($row['cnt'] ?? 0)) > 0;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Run a query but return an empty array on table-not-found errors instead of crashing.
     */
    public function safeQuery(string $sql, array $params = []): array
    {
        try {
            return $this->query($sql, $params);
        } catch (PDOException $e) {
            if (str_contains($e->getMessage(), '1146') || str_contains($e->getMessage(), 'Base table or view not found')) {
                $this->logger?->error('Safe query caught missing table.', ['sql' => substr($sql, 0, 120), 'error' => $e->getMessage()]);
                return [];
            }
            throw $e;
        }
    }

    /**
     * Run a first() query but return null on table-not-found errors instead of crashing.
     */
    public function safeFirst(string $sql, array $params = []): ?array
    {
        try {
            return $this->first($sql, $params);
        } catch (PDOException $e) {
            if (str_contains($e->getMessage(), '1146') || str_contains($e->getMessage(), 'Base table or view not found')) {
                $this->logger?->error('Safe first caught missing table.', ['sql' => substr($sql, 0, 120), 'error' => $e->getMessage()]);
                return null;
            }
            throw $e;
        }
    }

    /**
     * Run execute() but silently log on table-not-found errors.
     */
    public function safeExecute(string $sql, array $params = []): bool
    {
        try {
            return $this->execute($sql, $params);
        } catch (PDOException $e) {
            if (str_contains($e->getMessage(), '1146') || str_contains($e->getMessage(), 'Base table or view not found')) {
                $this->logger?->error('Safe execute caught missing table.', ['sql' => substr($sql, 0, 120), 'error' => $e->getMessage()]);
                return false;
            }
            throw $e;
        }
    }
}
