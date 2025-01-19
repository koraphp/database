<?php

/*
 * This file is part of the Kora package.
 *
 * (c) Uriel Wilson <uriel@koraphp.com>
 *
 * The full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Kora\Database\MySQL\MySQLDatabase;
use Kora\Database\MySQL\Repository;

// instance of MySQLDatabase
$db = new MySQLDatabase(
    '127.0.0.1',
    'my_db',
    'my_user',
    'my_password',
    3306
);

// Assume $db is an instance of MySQLDatabase
$userRepo = new Repository($db, 'users', 'id');

// 1. Create a new user:
$userId = $userRepo->create([
    'username' => 'alice',
    'email'    => 'alice@example.com',
    'status'   => 'active',
]);
echo "User created with ID: {$userId}\n";

// 2. Find an existing user:
$user = $userRepo->find($userId);
if ($user) {
    echo "Found user: " . $user['username'] . "\n";
} else {
    echo "User not found.\n";
}

// 3. Update user:
$rowsUpdated = $userRepo->update($userId, ['status' => 'inactive']);
echo "Rows updated: {$rowsUpdated}\n";

// 4. Delete user:
$rowsDeleted = $userRepo->delete($userId);
echo "Rows deleted: {$rowsDeleted}\n";

// 5. Advanced query example:
$rows = $userRepo
    ->where('status', '=', 'active')
    ->orderBy('created_at', 'DESC')
    ->limit(5)
    ->offset(0)
    ->get();

echo "Active users:\n";
foreach ($rows as $row) {
    echo $row['username'] . "\n";
}

// 6. Using count() or exists():
$activeCount = $userRepo->where('status', '=', 'active')->count();
echo "Number of active users: {$activeCount}\n";

if ($userRepo->where('username', '=', 'alice')->exists()) {
    echo "Alice exists in the database!\n";
}
