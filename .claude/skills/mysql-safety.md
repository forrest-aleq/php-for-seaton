# MySQL Safety Rules

When writing database code, follow these patterns:

## Always Use PDO with Prepared Statements

```php
// Setup - do this once in your container/bootstrap
$pdo = new PDO(
    "mysql:host=localhost;dbname=app;charset=utf8mb4",
    $user,
    $pass,
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]
);
```

## Query Patterns

### SELECT
```php
$stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email");
$stmt->execute([':email' => $email]);
$user = $stmt->fetch();

// Multiple rows
$stmt = $pdo->prepare("SELECT * FROM orders WHERE user_id = :user_id");
$stmt->execute([':user_id' => $userId]);
$orders = $stmt->fetchAll();
```

### INSERT
```php
$stmt = $pdo->prepare("
    INSERT INTO users (email, password_hash, created_at)
    VALUES (:email, :password, NOW())
");
$stmt->execute([
    ':email' => $email,
    ':password' => password_hash($password, PASSWORD_DEFAULT),
]);
$userId = $pdo->lastInsertId();
```

### UPDATE
```php
$stmt = $pdo->prepare("
    UPDATE users
    SET email = :email, updated_at = NOW()
    WHERE id = :id
");
$stmt->execute([':email' => $email, ':id' => $id]);
$affected = $stmt->rowCount();
```

### DELETE
```php
$stmt = $pdo->prepare("DELETE FROM sessions WHERE user_id = :user_id");
$stmt->execute([':user_id' => $userId]);
```

## Transactions

```php
try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("UPDATE accounts SET balance = balance - :amount WHERE id = :from");
    $stmt->execute([':amount' => $amount, ':from' => $fromId]);

    $stmt = $pdo->prepare("UPDATE accounts SET balance = balance + :amount WHERE id = :to");
    $stmt->execute([':amount' => $amount, ':to' => $toId]);

    $pdo->commit();
} catch (PDOException $e) {
    $pdo->rollBack();
    throw $e;
}
```

## NEVER Do These

```php
// NEVER - SQL injection
$pdo->query("SELECT * FROM users WHERE id = $id");
$pdo->query("SELECT * FROM users WHERE id = " . $_GET['id']);
$pdo->query("SELECT * FROM users WHERE name = '$name'");

// NEVER - Old mysql_* functions
mysql_query("SELECT * FROM users");
mysqli_query($conn, "SELECT * FROM users WHERE id = $id");

// NEVER - Dynamic table/column names from user input
$pdo->query("SELECT * FROM $tableName");  // If $tableName comes from user
```

## Safe Dynamic Queries

If you need dynamic table/column names (rare), whitelist them:

```php
$allowed = ['users', 'posts', 'comments'];
if (!in_array($table, $allowed, true)) {
    throw new InvalidArgumentException('Invalid table');
}
$stmt = $pdo->prepare("SELECT * FROM $table WHERE id = :id");
```

## IN Clauses

```php
$ids = [1, 2, 3];
$placeholders = implode(',', array_fill(0, count($ids), '?'));
$stmt = $pdo->prepare("SELECT * FROM users WHERE id IN ($placeholders)");
$stmt->execute($ids);
```
