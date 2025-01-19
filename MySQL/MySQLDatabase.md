# MySQLDatabase Class

A robust, object-oriented PHP class for securely interacting with a MySQL/MariaDB database using PDO. This class focuses on security, performance, and convenience to ensure applications are straightforward to develop and maintain.


## Table of Contents

1. [Overview](#overview)  
2. [Installation](#installation)  
3. [Requirements](#requirements)  
4. [Setup & Configuration](#setup--configuration)  
    - [Constructor Parameters](#constructor-parameters)  
    - [Using SSL/TLS](#using-ssltls-optional)  
5. [Basic Usage](#basic-usage)  
    - [Establishing the Connection](#establishing-the-connection)  
    - [Closing the Connection](#closing-the-connection)  
6. [Performing Queries](#performing-queries)  
    - [Executing Non-SELECT Queries](#executing-non-select-queries)  
    - [Fetching One Row](#fetching-one-row)  
    - [Fetching Multiple Rows](#fetching-multiple-rows)  
    - [Fetching a Single Column](#fetching-a-single-column)  
7. [Transactions](#transactions)  
8. [Other Useful Methods](#other-useful-methods)  
    - [Checking if a Table Exists](#checking-if-a-table-exists)  
    - [Getting the Last Inserted ID](#getting-the-last-inserted-id)  
9. [Logging](#logging)  
10. [Error Handling](#error-handling)  
11. [Practical Examples](#practical-examples)  
12. [Security Considerations](#security-considerations)  
13. [Contributing](#contributing)  
14. [License](#license)



## Overview

This class provides a simple yet powerful interface for interacting with MySQL/MariaDB databases. It uses [PDO (PHP Data Objects)](https://www.php.net/manual/en/book.pdo.php) under the hood, which is a consistent interface for accessing multiple databases in PHP. Key features include:

- **Secure Prepared Statements**: Prevent SQL injection by binding parameters safely.  
- **Lazy Loading**: Connect to the database only when needed.  
- **Transaction Support**: Convenient methods for beginning, committing, and rolling back transactions.  
- **PSR-3 Logger Support**: Optional logging for queries and errors via dependency injection.  
- **SSL Support**: Optionally use SSL/TLS for secure connections.  
- **Helper Methods**: For common operations like `tableExists()`, `fetchOne()`, `fetchAll()`, and more.



## Installation

1. **Composer** (recommended):  
   - Add the class to your project via Composer or place it in your project’s `src/` directory and require it via your autoloader.  
   - If you have a `composer.json`, you can include this class by pointing it to your repository or by copying the file into your codebase and adjusting autoload settings.

2. **Manual**:  
   - Download/copy `MySQLDatabase.php` into your project.  
   - `require 'path/to/MySQLDatabase.php';`

Make sure [PDO](https://www.php.net/manual/en/book.pdo.php) is enabled and compiled with MySQL support in your PHP environment.



## Requirements

- **PHP 7.4** or higher (recommended to use PHP 8+).
- PDO extension with MySQL support enabled (`pdo_mysql`).
- (Optional) [PSR-3 Logger](https://www.php-fig.org/psr/psr-3/) implementation if you want to use logging features.
- (Optional) SSL certificates/keys if you plan on using SSL/TLS connections.



## Setup & Configuration

### Constructor Parameters

The constructor supports the following parameters:

```php
public function __construct(
    string $host,
    string $dbName,
    string $user,
    string $password,
    ?int $port = 3306,
    ?LoggerInterface $logger = null,
    bool $useSSL = false,
    array $sslOptions = []
)
```

1. **$host**: The hostname or IP address of your MySQL/MariaDB server (e.g., `localhost` or `127.0.0.1`).  
2. **$dbName**: The name of the specific database you plan to use (e.g., `app_database`).  
3. **$user**: The MySQL username (with necessary privileges).  
4. **$password**: The MySQL user’s password.  
5. **$port** (optional): The TCP port for MySQL/MariaDB. Default is 3306.  
6. **$logger** (optional): Any PSR-3 compatible logger instance. If provided, this class will log queries, errors, and info messages.  
7. **$useSSL** (optional): Set to `true` if you want to connect using SSL/TLS.  
8. **$sslOptions** (optional): An associative array containing SSL certificate paths or other SSL-related options if `$useSSL` is `true`. For example:
   ```php
   [
       'ca'   => '/path/to/ca-cert.pem',
       'cert' => '/path/to/client-cert.pem',
       'key'  => '/path/to/client-key.pem'
   ]
   ```

### Using SSL/TLS (Optional)

If your server supports secure connections, you can enable SSL by passing `$useSSL = true` and providing relevant file paths in `$sslOptions`:

```php

use Kora\Database\MySQL\MySQLDatabase;

$sslOptions = [
    'ca'   => '/etc/ssl/ca-cert.pem',
    'cert' => '/etc/ssl/client-cert.pem',
    'key'  => '/etc/ssl/client-key.pem',
];

$db = new MySQLDatabase(
    host: 'db.example.com',
    dbName: 'my_database',
    user: 'secure_user',
    password: 'secret_pass',
    port: 3306,
    logger: $myLogger,
    useSSL: true,
    sslOptions: $sslOptions
);
```

With SSL configured, traffic between your application and the database server will be encrypted.



## Basic Usage

### Establishing the Connection

The connection is established lazily, meaning the initial `new MySQLDatabase(...)` call does **not** immediately connect. Instead, the class connects when you perform the first database operation (e.g., `execute()`, `fetchOne()`, etc.).

```php
<?php

use use Kora\Database\MySQL\MySQLDatabase;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

require 'vendor/autoload.php'; // if using Composer autoloader

// Optional: Set up a PSR-3 compatible logger for debug/error messages
$logger = new Logger('mysql_logger');
$logger->pushHandler(new StreamHandler('path/to/logfile.log', Logger::DEBUG));

// Create an instance of MySQLDatabase
$db = new MySQLDatabase(
    host: '127.0.0.1',
    dbName: 'my_db',
    user: 'my_user',
    password: 'my_password',
    port: 3306,
    logger: $logger
);
```

### Closing the Connection

Although PHP will close the connection automatically at the end of the script, you can explicitly close it by calling:

```php
$db->close();
```

This sets the internal PDO connection to `null`. Re-using methods on the class after calling `close()` will re-initiate a new connection on-demand.



## Performing Queries

### Executing Non-SELECT Queries

Use the `execute()` method for `INSERT`, `UPDATE`, `DELETE`, or any other SQL statement that **does not** return a result set. It returns the number of affected rows.

```php
// Example: Insert a new user
$affectedRows = $db->execute(
    "INSERT INTO users (username, email, status) VALUES (:username, :email, :status)",
    [
        'username' => 'alice',
        'email'    => 'alice@example.com',
        'status'   => 'active'
    ]
);

echo "Inserted {$affectedRows} row(s).";
```

### Fetching One Row

Use `fetchOne()` to retrieve exactly one row from a `SELECT` query as an associative array. If no rows match, it returns `null`.

```php
$user = $db->fetchOne(
    "SELECT * FROM users WHERE username = :username",
    ['username' => 'alice']
);

if ($user) {
    echo "User Email: " . $user['email'];
} else {
    echo "No matching user found.";
}
```

### Fetching Multiple Rows

Use `fetchAll()` to retrieve all matching rows from a `SELECT` query.

```php
$rows = $db->fetchAll("SELECT * FROM users WHERE status = :status", ['status' => 'active']);

foreach ($rows as $row) {
    echo "User: " . $row['username'] . PHP_EOL;
}
```

### Fetching a Single Column

Use `fetchColumn()` to quickly retrieve a single column value from the first row of a query. If no rows are found, it returns `null`.

```php
$userCount = $db->fetchColumn(
    "SELECT COUNT(*) FROM users WHERE status = :status",
    ['status' => 'active']
);

echo "Number of active users: " . $userCount;
```



## Transactions

Transactions allow you to group multiple SQL statements so they either all succeed or all fail:

1. **Manually control**:  
   ```php
   $db->beginTransaction();
   try {
       $db->execute("INSERT INTO orders (product_id, user_id) VALUES (1, 2)");
       $db->execute("UPDATE inventory SET stock = stock - 1 WHERE product_id = 1");
       $db->commit();
   } catch (Throwable $e) {
       $db->rollBack();
       // handle or log the error
   }
   ```

2. **Use `transaction(Closure $callback)`**:
   ```php
   $db->transaction(function(MySQLDatabase $db) {
       $db->execute("INSERT INTO orders (product_id, user_id) VALUES (1, 2)");
       $db->execute("UPDATE inventory SET stock = stock - 1 WHERE product_id = 1");
   });
   ```
   - This will automatically roll back if any exception is thrown within the callback, ensuring your database remains consistent.



## Other Useful Methods

### Checking if a Table Exists

```php
if ($db->tableExists('users')) {
    echo "The 'users' table exists!";
} else {
    echo "The 'users' table does not exist.";
}
```

### Getting the Last Inserted ID

If you’ve just performed an `INSERT`, you can retrieve the auto-incremented ID:

```php
$db->execute("INSERT INTO products (name, price) VALUES (:name, :price)", [
    'name'  => 'Widget',
    'price' => 19.99
]);

$lastId = $db->getLastInsertId();
echo "Newly inserted product ID: " . $lastId;
```



## Logging

If you pass a PSR-3 compatible logger (like [Monolog](https://github.com/Seldaek/monolog)) into the constructor, this class logs:

- **Debug** messages: Prepared queries, bound parameters, and other diagnostic details.
- **Info** messages: Successful connections and closure of connections.
- **Error** messages: Failures during query execution or connection attempts.

Example with Monolog:

```php
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

$logger = new Logger('mysql_logger');
$logger->pushHandler(new StreamHandler('/path/to/sql.log', Logger::DEBUG));

$db = new MySQLDatabase(
    host: 'localhost',
    dbName: 'my_db',
    user: 'my_user',
    password: 'my_password',
    logger: $logger
);
```

All relevant actions will now appear in `/path/to/sql.log`.



## Error Handling

All database exceptions are thrown as `PDOException` instances. The `transaction()` method can also throw any exceptions that occur inside the callback. Catch these exceptions using a `try/catch` block wherever you want to handle errors:

```php
try {
    $db->execute("INVALID SQL SYNTAX");
} catch (PDOException $e) {
    // Log or handle the error
    echo "Database error: " . $e->getMessage();
}
```

If a logger is provided, the error details will also appear in your logs.



## Practical Examples

1. **Creating a New User**  
   ```php
   $affectedRows = $db->execute(
       "INSERT INTO users (username, email, status) VALUES (:username, :email, :status)",
       [
           'username' => 'charlie',
           'email'    => 'charlie@example.org',
           'status'   => 'pending',
       ]
   );
   echo "Rows inserted: {$affectedRows}";
   ```

2. **Updating a Record**  
   ```php
   $affectedRows = $db->execute(
       "UPDATE users SET status = :status WHERE username = :username",
       [
           'status'   => 'active',
           'username' => 'charlie',
       ]
   );
   echo "Rows updated: {$affectedRows}";
   ```

3. **Using a Transaction**  
   ```php
   $db->transaction(function(MySQLDatabase $db) {
       $db->execute("UPDATE accounts SET balance = balance - 100 WHERE user_id = 1");
       $db->execute("UPDATE accounts SET balance = balance + 100 WHERE user_id = 2");
   });
   ```

4. **Fetching Data**  
   ```php
   $userInfo = $db->fetchOne("SELECT * FROM users WHERE username = :username", ['username' => 'charlie']);
   if ($userInfo) {
       echo "Email: " . $userInfo['email'];
   }
   ```



## Security Considerations

- **Use Strong Database Credentials**: Ensure the MySQL user has the least privileges required for your application. Avoid using `root`.
- **Prepared Statements**: All queries in this class are parameterized to avoid SQL injection. Always pass variables via the `$params` array.
- **Use SSL/TLS**: If the database is accessed over the internet or a non-trusted network, enable SSL to encrypt data in transit.
- **Keep PHP and MySQL Versions Updated**: Apply security patches and updates regularly.
- **Use Proper Character Sets**: This class sets `utf8mb4` to support a full range of UTF-8 characters. Ensure your tables are also created with `utf8mb4`.



## Contributing

1. Fork the repository (if using a Git-based workflow).
2. Create a new branch: `git checkout -b feature/new-feature`
3. Commit your changes: `git commit -m 'Add some feature'`
4. Push to the branch: `git push origin feature/new-feature`
5. Open a Pull Request.

We welcome improvements and suggestions on how to make this class more robust.
