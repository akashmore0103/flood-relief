<?php
/**
 * Core Database Wrapper (Singleton Pattern with PDO)
 * Supports PostgreSQL 15+, 16+, 17+ (Local & Render.com Production) and MySQL
 * Flood Risk Prediction & Relief Camp Locator
 */

class Database {
    private static ?Database $instance = null;
    private ?PDO $pdo = null;
    private bool $isConnected = false;
    private ?string $lastError = null;
    private string $driver = 'pgsql';

    /**
     * Private constructor to prevent direct object creation
     */
    private function __construct() {
        $configFile = dirname(__DIR__) . '/config/database.php';
        if (!file_exists($configFile)) {
            throw new Exception("Database configuration file not found at: {$configFile}");
        }

        $config = require $configFile;
        $this->driver = $config['driver'] ?? 'pgsql';

        if ($this->driver === 'pgsql') {
            $dsnParts = [
                "host={$config['host']}",
                "port={$config['port']}",
                "dbname={$config['database']}"
            ];
            if (!empty($config['sslmode'])) {
                $dsnParts[] = "sslmode={$config['sslmode']}";
            }
            $dsn = "pgsql:" . implode(';', $dsnParts);
        } else {
            $dsn = sprintf(
                "mysql:host=%s;port=%s;dbname=%s;charset=%s",
                $config['host'],
                $config['port'],
                $config['database'],
                $config['charset'] ?? 'utf8mb4'
            );
        }

        try {
            $this->pdo = new PDO($dsn, $config['username'], $config['password'], $config['options']);
            $this->isConnected = true;
        } catch (PDOException $e) {
            $this->isConnected = false;
            $this->lastError = $e->getMessage();
            error_log("Database Connection Error ({$this->driver}): " . $e->getMessage());
        }
    }

    /**
     * Prevent cloning of singleton instance
     */
    private function __clone() {}

    /**
     * Prevent unserializing of singleton instance
     */
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }

    /**
     * Get the singleton instance of Database
     */
    public static function getInstance(): Database {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Get the underlying PDO connection
     */
    public function getConnection(): ?PDO {
        if (!$this->isConnected && $this->lastError) {
            throw new Exception("Database is not connected: " . $this->lastError);
        }
        return $this->pdo;
    }

    /**
     * Check if currently connected to Database
     */
    public function isConnected(): bool {
        return $this->isConnected;
    }

    /**
     * Get database driver name ('pgsql' or 'mysql')
     */
    public function getDriverName(): string {
        return $this->driver;
    }

    /**
     * Get last connection error message if any
     */
    public function getLastError(): ?string {
        return $this->lastError;
    }

    /**
     * Execute a parameterized query and return the PDOStatement.
     * Automatically strips MySQL backticks for standard PostgreSQL compliance.
     */
    public function query(string $sql, array $params = []): PDOStatement {
        // Strip MySQL backticks for ANSI / PostgreSQL compatibility
        if (str_contains($sql, '`')) {
            $sql = str_replace('`', '', $sql);
        }

        $pdo = $this->getConnection();
        $stmt = $pdo->prepare($sql);

        // Explicitly bind parameters to preserve boolean types for PostgreSQL
        foreach ($params as $paramKey => $paramVal) {
            $dataType = PDO::PARAM_STR;
            if (is_bool($paramVal)) {
                $dataType = PDO::PARAM_BOOL;
            } elseif (is_int($paramVal)) {
                $dataType = PDO::PARAM_INT;
            } elseif (is_null($paramVal)) {
                $dataType = PDO::PARAM_NULL;
            }
            $stmt->bindValue($paramKey, $paramVal, $dataType);
        }

        $stmt->execute();
        return $stmt;
    }

    /**
     * Fetch a single row as associative array
     */
    public function fetchOne(string $sql, array $params = []): ?array {
        $stmt = $this->query($sql, $params);
        $result = $stmt->fetch();
        return $result !== false ? $result : null;
    }

    /**
     * Fetch all matching rows as array of associative arrays
     */
    public function fetchAll(string $sql, array $params = []): array {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }

    /**
     * Fetch a single scalar value (e.g. COUNT(*))
     */
    public function fetchValue(string $sql, array $params = []): mixed {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchColumn();
    }

    /**
     * Insert a record into a table
     * In PostgreSQL, leverages 'RETURNING id' for fast, reliable key retrieval.
     * Returns the last inserted ID.
     */
    public function insert(string $table, array $data, string $primaryKey = 'id'): int|string {
        $cleanTable = str_replace('`', '', $table);
        $columns = array_keys($data);
        $cleanColumns = array_map(fn($col) => str_replace('`', '', $col), $columns);
        $placeholders = array_map(fn($col) => ":{$col}", $columns);

        $params = [];
        foreach ($data as $col => $val) {
            $params[":{$col}"] = $val;
        }

        if ($this->driver === 'pgsql') {
            // PostgreSQL native RETURNING clause
            $sql = sprintf(
                "INSERT INTO %s (%s) VALUES (%s) RETURNING %s",
                $cleanTable,
                implode(', ', $cleanColumns),
                implode(', ', $placeholders),
                $primaryKey
            );

            try {
                $stmt = $this->query($sql, $params);
                $insertedId = $stmt->fetchColumn();
                if ($insertedId !== false && $insertedId !== null) {
                    return is_numeric($insertedId) ? (int)$insertedId : $insertedId;
                }
            } catch (PDOException $e) {
                // If RETURNING failed (e.g., column doesn't exist), retry without RETURNING
                $fallbackSql = sprintf(
                    "INSERT INTO %s (%s) VALUES (%s)",
                    $cleanTable,
                    implode(', ', $cleanColumns),
                    implode(', ', $placeholders)
                );
                $this->query($fallbackSql, $params);
                try {
                    $lastId = $this->getConnection()->lastInsertId();
                    return is_numeric($lastId) ? (int)$lastId : $lastId;
                } catch (Throwable) {
                    return 0;
                }
            }
        } else {
            // MySQL standard insert
            $sql = sprintf(
                "INSERT INTO `%s` (`%s`) VALUES (%s)",
                $cleanTable,
                implode('`, `', $cleanColumns),
                implode(', ', $placeholders)
            );
            $this->query($sql, $params);
            return (int)$this->getConnection()->lastInsertId();
        }

        return 0;
    }

    /**
     * Update records in a table
     * Returns the count of affected rows
     */
    public function update(string $table, array $data, string $where, array $whereParams = []): int {
        $cleanTable = str_replace('`', '', $table);
        $cleanWhere = str_replace('`', '', $where);
        $assignments = [];
        $params = [];

        foreach ($data as $col => $val) {
            $cleanCol = str_replace('`', '', $col);
            $paramName = ":set_{$cleanCol}";
            $assignments[] = "{$cleanCol} = {$paramName}";
            $params[$paramName] = $val;
        }

        $sql = sprintf(
            "UPDATE %s SET %s WHERE %s",
            $cleanTable,
            implode(', ', $assignments),
            $cleanWhere
        );

        $allParams = array_merge($params, $whereParams);
        $stmt = $this->query($sql, $allParams);
        return $stmt->rowCount();
    }

    /**
     * Delete records from a table
     * Returns affected rows count
     */
    public function delete(string $table, string $where, array $params = []): int {
        $cleanTable = str_replace('`', '', $table);
        $cleanWhere = str_replace('`', '', $where);
        $sql = sprintf("DELETE FROM %s WHERE %s", $cleanTable, $cleanWhere);
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }

    /**
     * Begin a database transaction
     */
    public function beginTransaction(): bool {
        return $this->getConnection()->beginTransaction();
    }

    /**
     * Commit the active transaction
     */
    public function commit(): bool {
        return $this->getConnection()->commit();
    }

    /**
     * Rollback the active transaction
     */
    public function rollBack(): bool {
        return $this->getConnection()->rollBack();
    }

    /**
     * Get the last inserted ID
     */
    public function lastInsertId(?string $name = null): string {
        return $this->getConnection()->lastInsertId($name);
    }

    /**
     * Execute a statement and return boolean success
     */
    public function execute(string $sql, array $params = []): bool {
        $this->query($sql, $params);
        return true;
    }
}
