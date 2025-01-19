# Kora\Database – Quickstart

A simple and efficient way to interact with MySQL/MariaDB databases.

- **MySQLDatabase**: A secure PDO-based database wrapper for MySQL/MariaDB.
- **Repository**: A convenient CRUD and basic query-building layer on top of MySQLDatabase.

## Installation

Install via [Composer](https://getcomposer.org/):

```bash
composer require kora/database
```

## Quickstart

### 1. Autoload

After installing, ensure you include Composer’s autoloader:

```php
require_once __DIR__ . '/vendor/autoload.php';
```

### 2. Create a MySQLDatabase instance

```php
use Kora\Database\MySQL\MySQLDatabase;

$db = new MySQLDatabase(
    host: '127.0.0.1',
    dbName: 'your_database',
    user: 'your_user',
    password: 'your_password',
    port: 3306 // default is 3306, can omit if desired
);
```

- **SSL Support**: If your environment requires a secure connection, pass `useSSL: true` and provide SSL options.
- **Logging**: Optionally pass a PSR-3 compatible logger for debug/error logging.

### 3. Perform Basic Queries

```php
// Example: Insert a row
$rows = $db->execute(
    "INSERT INTO users (username, email) VALUES (:username, :email)",
    ['username' => 'alice', 'email' => 'alice@example.com']
);
echo "Rows inserted: {$rows}\n";

// Fetch a single row
$user = $db->fetchOne(
    "SELECT * FROM users WHERE username = :username",
    ['username' => 'alice']
);
var_dump($user);

// Fetch multiple rows
$users = $db->fetchAll("SELECT * FROM users");
foreach ($users as $user) {
    echo $user['username'], PHP_EOL;
}
```

### 4. Using the Repository Class

The `Repository` offers a high-level CRUD interface plus a mini query builder.

```php
use Kora\Database\MySQL\Repository;

// Create a repository for the "users" table, primary key is "id" by default
$userRepo = new Repository($db, 'users', 'id');

// Create a new user
$newUserId = $userRepo->create([
    'username' => 'bob',
    'email'    => 'bob@example.com',
    'status'   => 'active',
]);

// Read a user
$bob = $userRepo->find($newUserId);
echo "Found user: " . $bob['username'] . "\n";

// Update the user
$userRepo->update($newUserId, ['status' => 'inactive']);

// Delete the user
$userRepo->delete($newUserId);
```

#### Query Builder Examples

```php
// Get all active users, ordered by creation time
$activeUsers = $userRepo
    ->where('status', '=', 'active')
    ->orderBy('created_at', 'DESC')
    ->get();

// Count how many users have a certain status
$inactiveCount = $userRepo
    ->where('status', '=', 'inactive')
    ->count();

// Check if any records exist with username "alice"
$aliceExists = $userRepo
    ->where('username', '=', 'alice')
    ->exists();
```

### 5. Transactions

Use the underlying `MySQLDatabase` transactions for multi-step operations:

```php
$db->transaction(function (MySQLDatabase $db) {
    // Perform multiple queries that succeed or fail together
    $db->execute("UPDATE accounts SET balance = balance - 100 WHERE user_id = 1");
    $db->execute("UPDATE accounts SET balance = balance + 100 WHERE user_id = 2");
});
```

## Next Steps

- **Explore** the rich methods in both `MySQLDatabase` and `Repository` (like `fetchColumn`, `tableExists`, etc.).

That’s it! You now have a simple convenient way to manage MySQL/MariaDB data.
