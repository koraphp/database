# Repository Class for MySQL/MariaDB

This is the repository for MySQL/MariaDB that builds on the `MySQLDatabase` wrapper. It streamlines typical CRUD (Create, Read, Update, Delete) and provides a lightweight query-building mechanism for advanced database interactions—without the overhead of a full ORM.

## Table of Contents

1. [Overview](#overview)  
2. [Requirements](#requirements)  
3. [Installation & Setup](#installation--setup)  
4. [Usage Examples](#usage-examples)  
    - [Basic CRUD Operations](#basic-crud-operations)  
    - [Advanced Query Builder Methods](#advanced-query-builder-methods)  
    - [Combining Methods](#combining-methods)  
5. [Detailed API Reference](#detailed-api-reference)  
    - [Constructor](#constructor)  
    - [create()](#create)  
    - [find()](#find)  
    - [findOrFail()](#findorfail)  
    - [all()](#all)  
    - [update()](#update)  
    - [delete()](#delete)  
    - [count()](#count)  
    - [exists()](#exists)  
    - [get()](#get)  
    - [where()](#where)  
    - [orWhere()](#orwhere)  
    - [orderBy()](#orderby)  
    - [limit()](#limit)  
    - [offset()](#offset)  
6. [Query Builder Mechanics](#query-builder-mechanics)  
7. [Security Considerations](#security-considerations)  
8. [Troubleshooting & Common Pitfalls](#troubleshooting--common-pitfalls)  
9. [Contributing](#contributing)  
10. [License](#license)



## Overview

This repository class simplifies database interactions by providing:

- **Basic CRUD**: `create()`, `find()`, `update()`, `delete()`.  
- **Extended Querying**: Methods like `where()`, `orWhere()`, `orderBy()`, `limit()`, and `offset()`.  
- **Aggregates & Checks**: `count()` and `exists()` help identify the number of matching records or if any record exists.  
- **Lazy Query Building**: Construct queries by chaining conditions, then finalize with `get()`.  
- **Parameter Binding**: All input is securely parameter-bound to prevent SQL injection.  
- **Identifier Quoting**: Table and column names are wrapped in backticks to avoid collisions with reserved words or special characters.


## Requirements

- PHP **7.4+** (PHP 8.x recommended)
- A working MySQL or MariaDB server
- [PDO MySQL Extension](https://www.php.net/manual/en/ref.pdo-mysql.php)
- The `MySQLDatabase` class from the same library (or an equivalent that offers the same interface)


## Installation & Setup

**Instantiate both classes**:
   - Create a `MySQLDatabase` instance, providing the necessary constructor parameters (host, dbName, user, etc.).
   - Pass the `MySQLDatabase` instance to the `Repository` constructor along with the table name and primary key column.

Example:

```php
use Kora\Database\MySQL\MySQLDatabase;
use Kora\Database\MySQL\Repository;


// Create a MySQL database connection instance
$db = new MySQLDatabase(
    host: '127.0.0.1',
    dbName: 'your_database',
    user: 'db_user',
    password: 'db_password'
);

// Create a Repository for a specific table, e.g., "users"
$userRepo = new Repository($db, 'users', 'id');
```

## Usage Examples

### Basic CRUD Operations

#### Create
```php
$userId = $userRepo->create([
    'username' => 'alice',
    'email'    => 'alice@example.com',
    'status'   => 'active'
]);
```

#### Read
```php
$userRecord = $userRepo->find($userId);
if ($userRecord) {
    echo "User found: " . $userRecord['username'];
} else {
    echo "User not found.";
}
```
Or use `findOrFail()`:
```php
try {
    $user = $userRepo->findOrFail($userId);
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
```

#### Update
```php
$rowsUpdated = $userRepo->update($userId, [
    'status' => 'inactive'
]);
```

#### Delete
```php
$rowsDeleted = $userRepo->delete($userId);
```

### Advanced Query Builder Methods

**where()** and **orWhere()**:  
```php
$records = $userRepo
    ->where('status', '=', 'active')
    ->orWhere('role', '=', 'admin')
    ->get();
```

**orderBy()**, **limit()**, **offset()**:
```php
$records = $userRepo
    ->where('status', '=', 'active')
    ->orderBy('created_at', 'DESC')
    ->limit(10)
    ->offset(0)
    ->get();
```

**count()** and **exists()**:
```php
$totalActive = $userRepo->where('status', '=', 'active')->count();
if ($userRepo->where('username', '=', 'alice')->exists()) {
    echo "Alice exists in the database!";
}
```

### Combining Methods

You can build complex queries by chaining multiple methods:

```php
$users = $userRepo
    ->where('status', '=', 'active')
    ->orWhere('role', '=', 'moderator')
    ->orderBy('username', 'ASC')
    ->limit(5)
    ->offset(5)
    ->get();
```

The `get()` method finalizes the query, executes it, returns the results, and **resets** all builder clauses (where, orderBy, limit, offset).



## Detailed API Reference

### Constructor
```php
public function __construct(
    MySQLDatabase $db,
    string $table,
    string $primaryKey = 'id'
)
```
- **$db**: An instance of `MySQLDatabase` used for all query execution and connection management.  
- **$table**: The database table this repository operates on.  
- **$primaryKey**: The column name used as the table's primary key (default: `'id'`).

### create()

```php
public function create(array $data): int
```
- Inserts a new row into the underlying table.
- Returns the last inserted primary key (as an integer).
- Throws `PDOException` if the data array is empty or on any DB error.

### find()

```php
public function find(mixed $id): ?array
```
- Retrieves a single row by primary key.
- Returns an associative array or `null` if not found.

### findOrFail()

```php
public function findOrFail(mixed $id): array
```
- Similar to `find()` but throws a `PDOException` if the record is not found.

### all()

```php
public function all(): array
```
- Returns all rows in the table if no conditions have been set, or builds and executes the current query if conditions are set.
- Helpful as a quick method to retrieve all data or to finalize a condition-based query.

### update()

```php
public function update(mixed $id, array $data): int
```
- Updates a row by primary key with the given `$data`.
- Returns the number of rows affected (usually `1` on success).
- Throws `PDOException` if `$data` is empty or on DB errors.

### delete()

```php
public function delete(mixed $id): int
```
- Deletes a row by primary key.
- Returns the number of rows affected (often `1`, or `0` if no match).

### count()

```php
public function count(): int
```
- Returns the number of rows in the table matching the current conditions.
- If no conditions are applied, returns the total row count for the table.

### exists()

```php
public function exists(): bool
```
- Returns `true` if at least one record matches the current conditions, `false` otherwise.

### get()

```php
public function get(): array
```
- Finalizes a query built via `where()`, `orderBy()`, `limit()`, and `offset()`, then executes it.
- Returns an array of matching rows.
- Resets the internal query builder state afterward.

### where()

```php
public function where(string $column, string $operator, mixed $value): static
```
- Adds a `WHERE` clause with an `AND` conjunction if it’s not the first condition.
- Accepts an operator like `=`, `<>`, `>`, `<`, `LIKE`, etc.
- Returns `$this` for chaining.

### orWhere()

```php
public function orWhere(string $column, string $operator, mixed $value): static
```
- Adds a `WHERE` clause with an `OR` conjunction if it’s not the first condition.
- Returns `$this` for chaining.

### orderBy()

```php
public function orderBy(string $column, string $direction = 'ASC'): static
```
- Adds an `ORDER BY` clause for the specified column.
- Accepts `ASC` (default) or `DESC`.
- Returns `$this` for chaining.

### limit()

```php
public function limit(int $value): static
```
- Sets a `LIMIT` on the number of rows returned.
- Returns `$this` for chaining.

### offset()

```php
public function offset(int $value): static
```
- Sets an `OFFSET`, indicating how many rows to skip.
- Returns `$this` for chaining.



## Query Builder Mechanics

1. **Chaining**: You can chain multiple `where()`, `orderBy()`, `limit()`, and `offset()` calls to build up a query.  
2. **Execution**: A query is executed when you call `get()`, `all()`, `count()`, `exists()`, or certain CRUD methods that generate their own queries (like `update()`, `delete()`).  
3. **Reset**: After calling `get()`, the repository resets its internal arrays for conditions and sorting. You can safely chain a new set of constraints for the next query.



## Security Considerations

- **Prepared Statements**: All user inputs are bound to placeholders, mitigating SQL injection risks.
- **Minimum Privileges**: Use a MySQL user account with the least privileges needed (avoid `root`).
- **Sanitize or Validate**: Confirm that user inputs match expected types/formats before passing them to the repository.
- **SSL**: In your `MySQLDatabase` class, consider enabling SSL if your environment allows it for secure transmission over untrusted networks.
- **Character Encoding**: By default, `MySQLDatabase` sets UTF8MB4 for broader Unicode support. Ensure your table columns use `utf8mb4` for consistency.



## Troubleshooting & Common Pitfalls

1. **Empty Data Arrays**: Methods like `create()` or `update()` throw exceptions if you pass an empty array. Ensure you’re providing at least one column-value pair.
2. **Missing Table or Columns**: If the table or columns do not exist, MySQL throws an error. Double-check schema correctness.
3. **Forgot to Call `get()`**: If you build conditions (`where()`, `orderBy()`, etc.) but never call `get()`, you won’t retrieve any data. Make sure to finalize with a method like `get()`, `all()`, `count()`, or `exists()`.
4. **Resetting Builder State**: Remember that `get()` and `all()` reset the internal conditions after execution. If you want to reuse conditions, store them or reapply them.



## Contributing

1. Fork the repository in your source control platform.
2. Create a feature branch: `git checkout -b feature/amazing-feature`
3. Commit your changes: `git commit -m 'Add new feature'`
4. Push the branch: `git push origin feature/amazing-feature`
5. Open a Pull Request to the main repository.

We welcome feature requests, bug fixes, and documentation improvements.
