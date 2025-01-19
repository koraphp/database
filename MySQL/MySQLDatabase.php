<?php

declare(strict_types=1);

/*
 * This file is part of the Kora package.
 *
 * (c) Uriel Wilson <uriel@koraphp.com>
 *
 * The full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Kora\Database\MySQL;

use Closure;
use PDO;
use PDOException;
use PDOStatement;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Class MySQLDatabase.
 *
 * A robust MySQL/MariaDB database wrapper that prioritizes security. Provides:
 *   - Lazy loading of the PDO connection
 *   - Secure prepared statements
 *   - Transaction helper methods
 *   - Convenience methods for fetching data
 *   - Optional logging via a PSR-3 compatible logger
 *
 * Usage example:
 *
 *   $db = new MySQLDatabase(
 *       host: '127.0.0.1',
 *       dbName: 'your_database',
 *       user: 'db_user',
 *       password: 'secret_password',
 *       port: 3306,
 *       logger: $logger // optional
 *   );
 *
 *   $rows = $db->fetchAll(
 *       "SELECT * FROM users WHERE status = :status",
 *       ['status' => 'active']
 *   );
 */
class MySQLDatabase
{
    /**
     * @var string
     */
    private string $host;

    /**
     * @var string
     */
    private string $dbName;

    /**
     * @var string
     */
    private string $user;

    /**
     * @var string
     */
    private string $password;

    /**
     * @var null|int
     */
    private ?int $port;

    /**
     * @var null|LoggerInterface
     */
    private ?LoggerInterface $logger;

    /**
     * @var null|PDO
     */
    private ?PDO $connection = null;

    /**
     * @var bool
     *
     * Whether to use SSL for the connection (if your server supports it).
     */
    private bool $useSSL;

    /**
     * @var array
     *
     * Additional SSL options, if $useSSL is set to true.
     */
    private array $sslOptions;

    /**
     * Constructor.
     *
     * @param string               $host       Database host address.
     * @param string               $dbName     Database name.
     * @param string               $user       Database username.
     * @param string               $password   Database password.
     * @param null|int             $port       Database port (3306 for MySQL/MariaDB by default).
     * @param null|LoggerInterface $logger     PSR-compatible logger (optional).
     * @param bool                 $useSSL     Whether to establish a secure (SSL) connection.
     * @param array                $sslOptions Associative array of SSL settings if $useSSL is true.
     */
    public function __construct(
        string $host,
        string $dbName,
        string $user,
        string $password,
        ?int $port = 3306,
        ?LoggerInterface $logger = null,
        bool $useSSL = false,
        array $sslOptions = []
    ) {
        $this->host      = $host;
        $this->dbName    = $dbName;
        $this->user      = $user;
        $this->password  = $password;
        $this->port      = $port;
        $this->logger    = $logger;
        $this->useSSL    = $useSSL;
        $this->sslOptions = $sslOptions;
    }

    /**
     * Executes a non-SELECT SQL statement (INSERT, UPDATE, DELETE, etc.) and returns affected rows.
     *
     * @param string $query  The SQL query string with placeholders.
     * @param array  $params Parameters to bind into the query.
     *
     * @throws PDOException
     *
     * @return int The number of rows affected.
     */
    public function execute(string $query, array $params = []): int
    {
        try {
            $stmt = $this->prepareStatement($query, $params);
            $stmt->execute();

            return $stmt->rowCount();
        } catch (PDOException $exception) {
            $this->logError(
                'MySQL execute query failed: ' . $exception->getMessage(),
                [
                    'query'     => $query,
                    'params'    => $params,
                    'exception' => $exception,
                ]
            );

            throw $exception;
        }
    }

    /**
     * Fetches a single row from a SELECT query as an associative array.
     *
     * @param string $query  The SQL query string with placeholders.
     * @param array  $params Parameters to bind into the query.
     *
     * @throws PDOException
     *
     * @return null|array An associative array for the row or null if no result.
     */
    public function fetchOne(string $query, array $params = []): ?array
    {
        try {
            $stmt = $this->prepareStatement($query, $params);
            $stmt->execute();
            $row = $stmt->fetch();

            return (false === $row) ? null : $row;
        } catch (PDOException $exception) {
            $this->logError(
                'MySQL fetchOne failed: ' . $exception->getMessage(),
                [
                    'query'     => $query,
                    'params'    => $params,
                    'exception' => $exception,
                ]
            );

            throw $exception;
        }
    }

    /**
     * Fetches all rows from a SELECT query as an array of associative arrays.
     *
     * @param string $query  The SQL query string with placeholders.
     * @param array  $params Parameters to bind into the query.
     *
     * @throws PDOException
     *
     * @return array Array of associative arrays representing each row.
     */
    public function fetchAll(string $query, array $params = []): array
    {
        try {
            $stmt = $this->prepareStatement($query, $params);
            $stmt->execute();

            return $stmt->fetchAll();
        } catch (PDOException $exception) {
            $this->logError(
                'MySQL fetchAll failed: ' . $exception->getMessage(),
                [
                    'query'     => $query,
                    'params'    => $params,
                    'exception' => $exception,
                ]
            );

            throw $exception;
        }
    }

    /**
     * Fetches a single column value from the first row of a SELECT query.
     *
     * @param string $query       The SQL query string with placeholders.
     * @param array  $params      Parameters to bind into the query.
     * @param int    $columnIndex Which column to return (0-indexed).
     *
     * @throws PDOException
     *
     * @return null|mixed The value of the column or null if no rows found.
     */
    public function fetchColumn(string $query, array $params = [], int $columnIndex = 0): mixed
    {
        try {
            $stmt = $this->prepareStatement($query, $params);
            $stmt->execute();
            $value = $stmt->fetchColumn($columnIndex);

            return (false === $value) ? null : $value;
        } catch (PDOException $exception) {
            $this->logError(
                'MySQL fetchColumn failed: ' . $exception->getMessage(),
                [
                    'query'     => $query,
                    'params'    => $params,
                    'exception' => $exception,
                ]
            );

            throw $exception;
        }
    }

    /**
     * Begins a transaction.
     *
     * @return bool True on success, false on failure.
     */
    public function beginTransaction(): bool
    {
        return $this->getConnection()->beginTransaction();
    }

    /**
     * Commits the current transaction.
     *
     * @return bool True on success, false on failure.
     */
    public function commit(): bool
    {
        return $this->getConnection()->commit();
    }

    /**
     * Rolls back the current transaction.
     *
     * @return bool True on success, false on failure.
     */
    public function rollBack(): bool
    {
        return $this->getConnection()->rollBack();
    }

    /**
     * Wraps a Closure in a database transaction. Rolls back on exception.
     *
     * Usage:
     *   $db->transaction(function(MySQLDatabase $db) {
     *       $db->execute("INSERT INTO table ...");
     *       $db->execute("UPDATE table ...");
     *   });
     *
     * @param Closure $callback A function that receives this MySQLDatabase instance.
     *
     * @throws Throwable If an exception is thrown within the callback, it is re-thrown after rollback.
     *
     * @return mixed The return value from your callback.
     */
    public function transaction(Closure $callback): mixed
    {
        $this->beginTransaction();

        try {
            $returnValue = $callback($this);
            $this->commit();

            return $returnValue;
        } catch (Throwable $exception) {
            $this->rollBack();
            $this->logError(
                'MySQL transaction failed: ' . $exception->getMessage(),
                ['exception' => $exception]
            );

            throw $exception;
        }
    }

    /**
     * Checks if a table exists in the current MySQL/MariaDB database.
     *
     * @param string $tableName The name of the table.
     *
     * @return bool True if the table exists, false otherwise.
     */
    public function tableExists(string $tableName): bool
    {
        $query = "SELECT 1
                  FROM information_schema.tables
                  WHERE table_schema = :schema
                    AND table_name = :table
                  LIMIT 1";

        $result = $this->fetchOne($query, [
            'schema' => $this->dbName,
            'table'  => $tableName,
        ]);

        return null !== $result;
    }

    /**
     * Retrieves the ID of the last inserted row.
     *
     * @return string Last inserted ID.
     */
    public function getLastInsertId(): string
    {
        return $this->getConnection()->lastInsertId();
    }

    /**
     * Closes the database connection if open.
     */
    public function close(): void
    {
        if (null !== $this->connection) {
            $this->connection = null;
            $this->logInfo('MySQL database connection closed.');
        }
    }

    /**
     * Retrieves the underlying PDO connection if available, or creates a new one.
     *
     * @throws PDOException
     *
     * @return PDO
     */
    private function getConnection(): PDO
    {
        if (null === $this->connection) {
            $dsn = "mysql:host={$this->host};dbname={$this->dbName}";
            if (null !== $this->port) {
                $dsn .= ";port={$this->port}";
            }

            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];

            // SSL options if required
            if ($this->useSSL) {
                // Example of setting up SSL attributes
                // Adjust keys to match your configuration, e.g., 'MYSQL_ATTR_SSL_CA'
                $sslMap = [
                    'ca'   => PDO::MYSQL_ATTR_SSL_CA,
                    'cert' => PDO::MYSQL_ATTR_SSL_CERT,
                    'key'  => PDO::MYSQL_ATTR_SSL_KEY,
                ];

                foreach ($sslMap as $key => $pdoConstant) {
                    if (! empty($this->sslOptions[$key])) {
                        $options[$pdoConstant] = $this->sslOptions[$key];
                    }
                }

                // If you need to verify the certificate chain, you might also set
                // PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => true (depends on environment).
            }

            try {
                $this->connection = new PDO($dsn, $this->user, $this->password, $options);

                // Ensure we use UTF8MB4 for full Unicode support.
                $this->connection->exec("SET NAMES 'utf8mb4'");
                $this->connection->exec("SET CHARACTER SET utf8mb4");

                $this->logInfo("Connected to MySQL/MariaDB database: {$this->dbName} at {$this->host}");
            } catch (PDOException $exception) {
                $this->logError(
                    'Failed to connect to MySQL/MariaDB database: ' . $exception->getMessage(),
                    ['exception' => $exception]
                );

                throw $exception;
            }
        }

        return $this->connection;
    }

    /**
     * Prepares a statement, binds parameters, and logs debug info if a logger is available.
     *
     * @param string $query  The SQL query with placeholders.
     * @param array  $params Parameters to bind into the query.
     *
     * @throws PDOException
     *
     * @return PDOStatement
     */
    private function prepareStatement(string $query, array $params = []): PDOStatement
    {
        $this->logDebug('Preparing query: ' . $query);
        $this->logDebug('With params: ' . json_encode($params));

        $stmt = $this->getConnection()->prepare($query);

        foreach ($params as $key => $value) {
            $type = match (true) {
                \is_int($value)  => PDO::PARAM_INT,
                \is_bool($value) => PDO::PARAM_BOOL,
                \is_null($value) => PDO::PARAM_NULL,
                default         => PDO::PARAM_STR,
            };

            // Handle numeric array indexes vs named placeholders
            $placeholder = \is_int($key) ? ($key + 1) : ':' . $key;
            $stmt->bindValue($placeholder, $value, $type);
        }

        return $stmt;
    }

    /**
     * Logs an informational message using the PSR-3 logger if available.
     *
     * @param string $message
     * @param array  $context
     */
    private function logInfo(string $message, array $context = []): void
    {
        if (null !== $this->logger) {
            $this->logger->info($message, $context);
        }
    }

    /**
     * Logs a debug-level message using the PSR-3 logger if available.
     *
     * @param string $message
     * @param array  $context
     */
    private function logDebug(string $message, array $context = []): void
    {
        if (null !== $this->logger) {
            $this->logger->debug($message, $context);
        }
    }

    /**
     * Logs an error-level message using the PSR-3 logger if available.
     *
     * @param string $message
     * @param array  $context
     */
    private function logError(string $message, array $context = []): void
    {
        if (null !== $this->logger) {
            $this->logger->error($message, $context);
        }
    }
}
