# PHP Guardian Rules

You are working on a PHP + MySQL codebase with strict quality standards. Follow these rules:

## File Structure
- Max 500 lines per file. Split if larger.
- Max 50 lines per function. Extract helpers if larger.
- One class per file, named after the class.

## Security - NEVER do these:

### SQL Injection
- NEVER use `mysql_*` functions. Use PDO with prepared statements.
- NEVER concatenate variables into SQL queries. Always use prepared statements.

### XSS
- NEVER echo/print variables without `htmlspecialchars()` or `e()`.

### Command Injection
- NEVER use `eval()`, `exec()`, `shell_exec()`, `system()`, `passthru()`.

### Input Validation
- NEVER use `$_GET`, `$_POST`, `$_REQUEST` directly. Validate first.

### Password Storage
- NEVER use md5() or sha1() for passwords. Use `password_hash()` and `password_verify()`.

### Deserialization
- NEVER use `unserialize()` on user data. Use `json_decode()` instead.

### File Inclusion
- NEVER use `include`/`require` with user-controlled variables.

### SSRF
- NEVER use `file_get_contents()` with user-controlled URLs. Validate URLs first.

### Path Traversal
- NEVER use file operations with user input without sanitizing `../` sequences.

### Open Redirect
- NEVER redirect to user-controlled URLs. Whitelist allowed destinations.

### Prompt Injection (for LLM apps)
- NEVER concatenate user input directly into LLM prompts.
- ALWAYS sanitize and validate user input before including in prompts.
- Use structured input formats, not string concatenation.

### Secrets
- NEVER hardcode passwords, API keys, or tokens. Use environment variables.
- NEVER commit `.env` files or secrets to git.

### Weak Cryptography
- NEVER use `rand()` or `mt_rand()` for security. Use `random_bytes()` or `random_int()`.
- NEVER use `extract()` on user input.

## Code Quality - NEVER do these:
- NEVER leave `var_dump()`, `print_r()`, `dump()`, `dd()` in production code.
- NEVER use `die()` or `exit()` for flow control.
- NEVER leave TODO, FIXME, or incomplete code.
- NEVER use mock data, fake emails, placeholder values in production.
- NEVER use `@` error suppression operator.
- NEVER use `extract()` on user input.

## Database - Always:
- Use PDO with `PDO::ERRMODE_EXCEPTION`.
- Use prepared statements with named parameters: `$stmt->execute([':id' => $id])`.
- Close connections in finally blocks or use dependency injection.
- Use transactions for multi-query operations.

```php
// WRONG - SQL injection
$query = "SELECT * FROM users WHERE id = " . $_GET['id'];

// RIGHT - Prepared statement
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id");
$stmt->execute([':id' => $id]);
```

## Output - Always escape:
```php
// WRONG - XSS vulnerability
echo $user['name'];
echo "<a href='$url'>Link</a>";

// RIGHT - Escaped
echo htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8');
echo '<a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '">Link</a>';

// Or use a framework helper
echo e($user['name']);
```

## Type Safety:
- Always use `declare(strict_types=1);` at the top of every file.
- Add return types to all functions.
- Add parameter types to all functions.
- Use `?Type` for nullable, not `Type|null`.

## Error Handling:
- Throw specific exceptions, not generic `Exception`.
- Catch specific exceptions, not bare `catch (Exception $e)`.
- Log errors with context, don't just swallow them.
- Never show raw error messages to users.

## Patterns to use:
- Constructor property promotion: `public function __construct(private Db $db)`
- Null coalescing: `$value = $input ?? 'default';`
- Named arguments for clarity: `new User(name: $name, email: $email)`
- Match expressions over switch for returns.
