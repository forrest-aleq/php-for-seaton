# PHP Project Rules

This project uses strict PHP standards. Follow these rules or the pre-commit hooks will reject your code.

## Quick Rules

1. **50 lines max per function** - Extract helpers if longer
2. **500 lines max per file** - Split into modules if longer
3. **Always use prepared statements** - Never concatenate SQL
4. **Always escape output** - Use `htmlspecialchars()` or `e()`
5. **No debug code** - No `var_dump`, `print_r`, `die`, `dd`
6. **No mock data** - No `test@example.com`, `fake_`, `mock_`
7. **No TODO/FIXME** - Finish it before committing

## Database

```php
// ALWAYS use prepared statements
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id");
$stmt->execute([':id' => $id]);
$user = $stmt->fetch();

// NEVER do this - SQL injection
$pdo->query("SELECT * FROM users WHERE id = $id");
```

## Output

```php
// ALWAYS escape
echo htmlspecialchars($data, ENT_QUOTES, 'UTF-8');

// In Laravel/blade
{{ $data }}  // Auto-escaped
{!! $html !!}  // Only for trusted HTML

// NEVER do this - XSS
echo $data;
echo "<div>$userInput</div>";
```

## Input Validation

```php
// ALWAYS validate
$email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
if ($email === false) {
    throw new ValidationException('Invalid email');
}

// NEVER do this
$email = $_POST['email'];
```

## Type Safety

Every file must start with:
```php
<?php

declare(strict_types=1);
```

Every function must have types:
```php
public function findUser(int $id): ?User
{
    // ...
}
```

## Error Handling

```php
// Throw specific exceptions
throw new UserNotFoundException($id);

// Catch specific exceptions
try {
    $user = $this->findUser($id);
} catch (UserNotFoundException $e) {
    return $this->notFound();
}

// NEVER do this
die("error");
exit(1);
```

## Before Committing

Run these:
```bash
composer lint    # Guardian + PHPStan + CS Fixer
composer test    # PHPUnit
```

Or just commit - pre-commit hooks will catch issues.
