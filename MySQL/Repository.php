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

use PDOException;

/**
 * Class Repository.
 *
 * An extended repository class for MySQL/MariaDB that allows:
 *  - Basic CRUD operations (create, find, update, delete)
 *  - `findOrFail()` which throws an exception if not found
 *  - Fetching all records or building flexible queries via `where()`, `orWhere()`, `orderBy()`, etc.
 *  - Counting and existence checks
 *  - Simple pagination with limit and offset
 *
 * Example usage:
 *
 *   // Assume $db is an instance of MySQLDatabase
 *   $repo = new Repository($db, 'users', 'id');
 *
 *   // Create:
 *   $newUserId = $repo->create([
 *     'username' => 'alice',
 *     'email'    => 'alice@example.com',
 *     'status'   => 'active',
 *   ]);
 *
 *   // Read one record:
 *   $user = $repo->find($newUserId);
 *
 *   // Update:
 *   $rowsUpdated = $repo->update($newUserId, ['status' => 'inactive']);
 *
 *   // Delete:
 *   $repo->delete($newUserId);
 *
 *   // Advanced query:
 *   $activeUsers = $repo
 *       ->where('status', '=', 'active')
 *       ->orderBy('created_at', 'DESC')
 *       ->limit(10)
 *       ->offset(0)
 *       ->get();
 */
class Repository
{
    /**
     * @var MySQLDatabase
     */
    protected MySQLDatabase $db;

    /**
     * @var string The table on which this repository operates.
     */
    protected string $table;

    /**
     * @var string The primary key column name.
     */
    protected string $primaryKey;

    /**
     * @var array Query conditions for building WHERE clauses.
     */
    protected array $conditions = [];

    /**
     * @var array Sort orders for building ORDER BY clauses.
     */
    protected array $orderBy = [];

    /**
     * @var null|int Limit for the query.
     */
    protected ?int $limit = null;

    /**
     * @var null|int Offset for the query.
     */
    protected ?int $offset = null;

    /**
     * Repository constructor.
     *
     * @param MySQLDatabase $db         The MySQLDatabase wrapper instance.
     * @param string        $table      The database table name (e.g., 'users').
     * @param string        $primaryKey The primary key column (e.g., 'id').
     */
    public function __construct(MySQLDatabase $db, string $table, string $primaryKey = 'id')
    {
        $this->db         = $db;
        $this->table      = $table;
        $this->primaryKey = $primaryKey;
    }

    /**
     * CREATE: Insert a new record into the table.
     *
     * @param array $data Associative array [column => value].
     *
     * @throws PDOException If $data is empty or the query fails.
     *
     * @return int The newly inserted row's primary key as an integer.
     */
    public function create(array $data): int
    {
        if (empty($data)) {
            throw new PDOException('Cannot create a record with an empty data array.');
        }

        $columns      = array_keys($data);
        $placeholders = array_map(
            fn ($col) => ':' . $col,
            $columns
        );

        $sql = \sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $this->quoteIdentifier($this->table),
            implode(', ', array_map([$this, 'quoteIdentifier'], $columns)),
            implode(', ', $placeholders)
        );

        $this->db->execute($sql, $data);

        return (int) $this->db->getLastInsertId();
    }

    /**
     * READ: Fetch a single record by primary key.
     *
     * @param mixed $id The primary key value.
     *
     * @throws PDOException If the query fails.
     *
     * @return null|array The found record as an associative array, or null if not found.
     */
    public function find(mixed $id): ?array
    {
        $sql = \sprintf(
            'SELECT * FROM %s WHERE %s = :id LIMIT 1',
            $this->quoteIdentifier($this->table),
            $this->quoteIdentifier($this->primaryKey)
        );

        return $this->db->fetchOne($sql, ['id' => $id]);
    }

    /**
     * READ: Fetch a single record by primary key or throw an exception if not found.
     *
     * @param mixed $id The primary key value.
     *
     * @throws PDOException If record not found or query fails.
     *
     * @return array The found record.
     */
    public function findOrFail(mixed $id): array
    {
        $record = $this->find($id);
        if (null === $record) {
            throw new PDOException(\sprintf(
                'No record found in [%s] where [%s = %s].',
                $this->table,
                $this->primaryKey,
                (string) $id
            ));
        }

        return $record;
    }

    /**
     * READ: Fetch all records from the table (or apply the current query builder conditions).
     *
     * @throws PDOException If the query fails.
     *
     * @return array An array of associative arrays.
     */
    public function all(): array
    {
        // If no conditions/order/limit/offset, it's a simple "SELECT * FROM table"
        if (empty($this->conditions) && empty($this->orderBy) && null === $this->limit && null === $this->offset) {
            $sql = \sprintf('SELECT * FROM %s', $this->quoteIdentifier($this->table));

            return $this->db->fetchAll($sql);
        }

        // Otherwise, build a custom query
        return $this->get();
    }

    /**
     * UPDATE: Update an existing record by primary key.
     *
     * @param mixed $id   The primary key value.
     * @param array $data Associative array [column => newValue].
     *
     * @throws PDOException If $data is empty or the query fails.
     *
     * @return int Number of affected rows (usually 1 if successful).
     */
    public function update(mixed $id, array $data): int
    {
        if (empty($data)) {
            throw new PDOException('Cannot update with an empty data array.');
        }

        $setClauses = [];
        foreach ($data as $col => $val) {
            $quotedCol    = $this->quoteIdentifier($col);
            $setClauses[] = "{$quotedCol} = :{$col}";
        }

        $sql = \sprintf(
            'UPDATE %s SET %s WHERE %s = :id',
            $this->quoteIdentifier($this->table),
            implode(', ', $setClauses),
            $this->quoteIdentifier($this->primaryKey)
        );

        $params        = $data;
        $params['id']  = $id;

        return $this->db->execute($sql, $params);
    }

    /**
     * DELETE: Remove a record by primary key.
     *
     * @param mixed $id The primary key to delete.
     *
     * @throws PDOException If the query fails.
     *
     * @return int Number of affected rows (0 if no match).
     */
    public function delete(mixed $id): int
    {
        $sql = \sprintf(
            'DELETE FROM %s WHERE %s = :id',
            $this->quoteIdentifier($this->table),
            $this->quoteIdentifier($this->primaryKey)
        );

        return $this->db->execute($sql, ['id' => $id]);
    }

    /**
     * Count the number of records in the table or matching the current query conditions.
     *
     * @throws PDOException If the query fails.
     *
     * @return int The total count of rows.
     */
    public function count(): int
    {
        $sql    = $this->buildSelectQuery('COUNT(*) AS total');
        $params = $this->buildQueryParams();

        $row = $this->db->fetchOne($sql, $params);

        return $row ? (int) $row['total'] : 0;
    }

    /**
     * Check if any records exist matching the current query conditions.
     *
     * @throws PDOException If the query fails.
     *
     * @return bool True if at least one record matches, otherwise false.
     */
    public function exists(): bool
    {
        return $this->count() > 0;
    }

    /**
     * Get all results based on the current query builder state.
     * (conditions, order, limit, offset).
     *
     * @throws PDOException If the query fails.
     *
     * @return array
     */
    public function get(): array
    {
        $sql    = $this->buildSelectQuery('*');
        $params = $this->buildQueryParams();
        $rows   = $this->db->fetchAll($sql, $params);

        // Reset builder state after running query
        $this->resetQueryBuilder();

        return $rows;
    }

    /**
     * Add a "WHERE" condition to the query builder.
     *
     * @param string $column   Column name.
     * @param string $operator SQL operator (e.g., '=', '>', '<', 'LIKE', etc.).
     * @param mixed  $value    The value to compare against.
     *
     * @return $this
     */
    public function where(string $column, string $operator, mixed $value): static
    {
        $this->conditions[] = [
            'type'     => 'AND',
            'column'   => $column,
            'operator' => $operator,
            'value'    => $value,
        ];

        return $this;
    }

    /**
     * Add an "OR WHERE" condition to the query builder.
     *
     * @param string $column   Column name.
     * @param string $operator SQL operator.
     * @param mixed  $value    The value to compare against.
     *
     * @return $this
     */
    public function orWhere(string $column, string $operator, mixed $value): static
    {
        $this->conditions[] = [
            'type'     => 'OR',
            'column'   => $column,
            'operator' => $operator,
            'value'    => $value,
        ];

        return $this;
    }

    /**
     * Add an ORDER BY clause.
     *
     * @param string $column    Column to sort by.
     * @param string $direction Direction ('ASC' or 'DESC').
     *
     * @return $this
     */
    public function orderBy(string $column, string $direction = 'ASC'): static
    {
        $this->orderBy[] = [
            'column'    => $column,
            'direction' => 'DESC' === strtoupper($direction) ? 'DESC' : 'ASC',
        ];

        return $this;
    }

    /**
     * Limit the query to a certain number of rows.
     *
     * @param int $value Number of rows to retrieve.
     *
     * @return $this
     */
    public function limit(int $value): static
    {
        $this->limit = $value;

        return $this;
    }

    /**
     * Offset the query by a certain number of rows.
     *
     * @param int $value Number of rows to skip.
     *
     * @return $this
     */
    public function offset(int $value): static
    {
        $this->offset = $value;

        return $this;
    }

    /**
     * Quotes identifiers for MySQL (using backticks).
     *
     * @param string $identifier Table or column name.
     *
     * @return string
     */
    protected function quoteIdentifier(string $identifier): string
    {
        // Replace any backticks inside the identifier with double backticks
        // to avoid breaking out of the identifier.
        $identifier = str_replace('`', '``', $identifier);

        return "`{$identifier}`";
    }

    /**
     * Build the SELECT query string from current builder state.
     *
     * @param string $columns Columns to select (e.g. '*', 'COUNT(*)', etc.).
     *
     * @return string The generated SQL string.
     */
    protected function buildSelectQuery(string $columns = '*'): string
    {
        $table = $this->quoteIdentifier($this->table);

        $sql = "SELECT {$columns} FROM {$table}";

        if (!empty($this->conditions)) {
            $clauses = [];
            foreach ($this->conditions as $index => $condition) {
                $conjunction = 0 === $index
                    ? 'WHERE'
                    : $condition['type']; // 'AND' or 'OR'
                $col   = $this->quoteIdentifier($condition['column']);
                $op    = $condition['operator'];
                $param = $this->buildConditionPlaceholder($condition['column'], $index);

                $clauses[] = \sprintf("%s %s %s %s", $conjunction, $col, $op, $param);
            }
            $sql .= ' ' . implode(' ', $clauses);
        }

        if (!empty($this->orderBy)) {
            $orderClauses = [];
            foreach ($this->orderBy as $order) {
                $col = $this->quoteIdentifier($order['column']);
                $dir = $order['direction'];
                $orderClauses[] = "{$col} {$dir}";
            }
            $sql .= ' ORDER BY ' . implode(', ', $orderClauses);
        }

        if (null !== $this->limit) {
            $sql .= ' LIMIT :__limit';
            if (null !== $this->offset) {
                $sql .= ' OFFSET :__offset';
            }
        }

        return $sql;
    }

    /**
     * Build query parameters (for WHERE conditions, limit, and offset).
     *
     * @return array
     */
    protected function buildQueryParams(): array
    {
        $params = [];

        // Conditions
        foreach ($this->conditions as $index => $condition) {
            $placeholder             = $this->buildConditionPlaceholder($condition['column'], $index, false);
            $params[$placeholder]    = $condition['value'];
        }

        // Limit / offset
        if (null !== $this->limit) {
            $params['__limit'] = $this->limit;
        }
        if (null !== $this->offset) {
            $params['__offset'] = $this->offset;
        }

        return $params;
    }

    /**
     * Build a placeholder name for a condition based on column name and index.
     * Example: :status_0, :status_1, etc.
     *
     * @param string $column
     * @param int    $index
     * @param bool   $withColon Whether to include a leading ':'.
     *
     * @return string
     */
    protected function buildConditionPlaceholder(string $column, int $index, bool $withColon = true): string
    {
        // Remove special characters from column to keep placeholders safe
        $safeColumn = preg_replace('/[^a-zA-Z0-9_]/', '', $column);
        $placeholder = "{$safeColumn}_{$index}";

        return $withColon ? ":{$placeholder}" : $placeholder;
    }

    /**
     * Reset the query builder state (conditions, order, limit, offset).
     */
    protected function resetQueryBuilder(): void
    {
        $this->conditions = [];
        $this->orderBy    = [];
        $this->limit      = null;
        $this->offset     = null;
    }
}
