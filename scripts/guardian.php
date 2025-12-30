#!/usr/bin/env php
<?php
/**
 * Guardian: Catches real PHP bugs without blocking your flow.
 *
 * Configure via guardian.config.json.
 */

declare(strict_types=1);

const CONFIG_FILE = 'guardian.config.json';

// ---------------------------------------------------------------------------
// Config
// ---------------------------------------------------------------------------

function getDefaultConfig(): array
{
    return [
        'srcDirs' => ['src', 'app'],
        'exclude' => ['vendor', 'tests', 'storage', 'cache'],
        'limits' => [
            'maxFileLines' => 500,
            'maxFunctionLines' => 50,
        ],
        'quality' => [
            'banVarDump' => true,
            'banPrintR' => true,
            'banDie' => true,
            'banExit' => true,
            'banTodo' => true,
            'banMockData' => true,
            'mockPatterns' => [
                'mock_', '_mock', 'fake_', '_fake', 'dummy_',
                'test_user', 'test_email', 'placeholder',
                'example@', '@example.com', '@test.com',
                'lorem ipsum', 'asdf', 'xxx',
            ],
        ],
        'security' => [
            'banEval' => true,
            'banExec' => true,
            'banShellExec' => true,
            'banSystem' => true,
            'banPassthru' => true,
            'banRawSuperGlobals' => true,
            'banDeprecatedMysql' => true,
            'checkSqlInjection' => true,
            'checkXss' => true,
        ],
        'database' => [
            'requirePreparedStatements' => true,
            'allowedDrivers' => ['pdo'],
        ],
    ];
}

function loadConfig(): array
{
    $configPath = getcwd() . '/' . CONFIG_FILE;
    $default = getDefaultConfig();

    if (!file_exists($configPath)) {
        return $default;
    }

    $json = file_get_contents($configPath);
    $userConfig = json_decode($json, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        fwrite(STDERR, "guardian: Failed to parse config: " . json_last_error_msg() . "\n");
        return $default;
    }

    return array_replace_recursive($default, $userConfig);
}

// ---------------------------------------------------------------------------
// File utilities
// ---------------------------------------------------------------------------

function shouldExclude(string $path, array $excludePatterns): bool
{
    foreach ($excludePatterns as $pattern) {
        if (str_contains($path, $pattern)) {
            return true;
        }
    }
    return false;
}

function iterPhpFiles(string $dir, array $exclude): Generator
{
    if (!is_dir($dir)) {
        return;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $path = $file->getPathname();
            if (!shouldExclude($path, $exclude)) {
                yield $path;
            }
        }
    }
}

// ---------------------------------------------------------------------------
// Checks
// ---------------------------------------------------------------------------

function checkFileSize(string $path, array $lines, array $config): array
{
    $max = $config['limits']['maxFileLines'];
    $count = count($lines);

    if ($count > $max) {
        return ["$path: $count lines (max $max). Split it up."];
    }
    return [];
}

function checkFunctionSize(string $path, string $content, array $config): array
{
    $max = $config['limits']['maxFunctionLines'];
    $violations = [];

    // Match function declarations
    preg_match_all(
        '/(?:public|private|protected|static|\s)*function\s+(\w+)\s*\([^)]*\)/m',
        $content,
        $matches,
        PREG_OFFSET_CAPTURE
    );

    foreach ($matches[1] as $match) {
        $name = $match[0];
        $offset = $match[1];
        $lineNum = substr_count(substr($content, 0, $offset), "\n") + 1;

        // Find function body
        $bracePos = strpos($content, '{', $offset);
        if ($bracePos === false) continue;

        $braceCount = 0;
        $endPos = $bracePos;
        for ($i = $bracePos; $i < strlen($content); $i++) {
            if ($content[$i] === '{') $braceCount++;
            if ($content[$i] === '}') $braceCount--;
            if ($braceCount === 0) {
                $endPos = $i;
                break;
            }
        }

        $endLine = substr_count(substr($content, 0, $endPos), "\n") + 1;
        $size = $endLine - $lineNum + 1;

        if ($size > $max) {
            $violations[] = "$path:$lineNum: '$name()' is $size lines (max $max).";
        }
    }

    return $violations;
}

function checkDebugStatements(string $path, array $lines, array $config): array
{
    $checks = [
        'banVarDump' => ['var_dump(', 'dump('],
        'banPrintR' => ['print_r('],
        'banDie' => ['die(', 'die;'],
        'banExit' => ['exit(', 'exit;'],
    ];

    foreach ($checks as $setting => $patterns) {
        if (!($config['quality'][$setting] ?? false)) continue;

        foreach ($lines as $i => $line) {
            // Skip comments
            $trimmed = trim($line);
            if (str_starts_with($trimmed, '//') || str_starts_with($trimmed, '*')) continue;

            foreach ($patterns as $pattern) {
                if (str_contains(strtolower($line), strtolower($pattern))) {
                    $name = rtrim($pattern, '(;');
                    return ["$path:" . ($i + 1) . ": '$name' in production. Remove debug code."];
                }
            }
        }
    }

    return [];
}

function checkTodo(string $path, array $lines, array $config): array
{
    if (!($config['quality']['banTodo'] ?? false)) return [];

    foreach ($lines as $i => $line) {
        $lower = strtolower($line);
        if (str_contains($lower, 'todo') || str_contains($lower, 'fixme')) {
            return ["$path:" . ($i + 1) . ": TODO/FIXME. Finish before committing."];
        }
    }

    return [];
}

function checkMockData(string $path, array $lines, array $config): array
{
    if (!($config['quality']['banMockData'] ?? false)) return [];

    $patterns = $config['quality']['mockPatterns'] ?? [];

    foreach ($patterns as $pattern) {
        $lower = strtolower($pattern);
        foreach ($lines as $i => $line) {
            $trimmed = trim($line);
            if (str_starts_with($trimmed, '//') || str_starts_with($trimmed, '*')) continue;

            if (str_contains(strtolower($line), $lower)) {
                return ["$path:" . ($i + 1) . ": Mock/fake data '$pattern'. Replace with real implementation."];
            }
        }
    }

    return [];
}

function checkDangerousFunctions(string $path, array $lines, array $config): array
{
    $checks = [
        'banEval' => 'eval(',
        'banExec' => 'exec(',
        'banShellExec' => 'shell_exec(',
        'banSystem' => 'system(',
        'banPassthru' => 'passthru(',
    ];

    $security = $config['security'] ?? [];

    foreach ($checks as $setting => $pattern) {
        if (!($security[$setting] ?? false)) continue;

        foreach ($lines as $i => $line) {
            $trimmed = trim($line);
            if (str_starts_with($trimmed, '//') || str_starts_with($trimmed, '*')) continue;

            if (str_contains($line, $pattern)) {
                $name = rtrim($pattern, '(');
                return ["$path:" . ($i + 1) . ": '$name()' is dangerous. Use safer alternatives."];
            }
        }
    }

    return [];
}

function checkDeprecatedMysql(string $path, string $content, array $config): array
{
    if (!($config['security']['banDeprecatedMysql'] ?? false)) return [];

    $deprecated = ['mysql_connect', 'mysql_query', 'mysql_fetch', 'mysql_select_db', 'mysql_close'];

    foreach ($deprecated as $func) {
        if (preg_match('/\b' . preg_quote($func) . '\s*\(/i', $content, $match, PREG_OFFSET_CAPTURE)) {
            $lineNum = substr_count(substr($content, 0, $match[0][1]), "\n") + 1;
            return ["$path:$lineNum: Deprecated '$func()'. Use PDO instead."];
        }
    }

    return [];
}

function checkRawSuperGlobals(string $path, array $lines, array $config): array
{
    if (!($config['security']['banRawSuperGlobals'] ?? false)) return [];

    // Direct use without validation
    $patterns = [
        '/\$_GET\s*\[\s*[\'"][^\'"]+[\'"]\s*\](?!\s*\?\?)/',
        '/\$_POST\s*\[\s*[\'"][^\'"]+[\'"]\s*\](?!\s*\?\?)/',
        '/\$_REQUEST\s*\[\s*[\'"][^\'"]+[\'"]\s*\](?!\s*\?\?)/',
    ];

    foreach ($lines as $i => $line) {
        $trimmed = trim($line);
        if (str_starts_with($trimmed, '//') || str_starts_with($trimmed, '*')) continue;

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $line)) {
                return ["$path:" . ($i + 1) . ": Raw \$_GET/\$_POST. Validate input first."];
            }
        }
    }

    return [];
}

function checkSqlInjection(string $path, array $lines, array $config): array
{
    if (!($config['security']['checkSqlInjection'] ?? false)) return [];

    // Look for string concatenation in queries
    $sqlPatterns = [
        '/(?:query|execute|exec)\s*\(\s*["\'].*\.\s*\$/',  // query("SELECT" . $var
        '/(?:query|execute|exec)\s*\(\s*\$\w+\s*\.\s*/',   // query($sql . ...
        '/["\'](?:SELECT|INSERT|UPDATE|DELETE).*["\']\s*\.\s*\$/',  // "SELECT..." . $var
    ];

    foreach ($lines as $i => $line) {
        $trimmed = trim($line);
        if (str_starts_with($trimmed, '//') || str_starts_with($trimmed, '*')) continue;

        foreach ($sqlPatterns as $pattern) {
            if (preg_match($pattern, $line)) {
                return ["$path:" . ($i + 1) . ": SQL injection risk. Use prepared statements."];
            }
        }
    }

    return [];
}

function checkXss(string $path, array $lines, array $config): array
{
    if (!($config['security']['checkXss'] ?? false)) return [];

    // echo/print with variables without escaping
    $patterns = [
        '/\becho\s+\$(?!this)/',           // echo $var (not $this)
        '/\bprint\s+\$/',                   // print $var
        '/<\?=\s*\$(?!this)/',              // <?= $var
    ];

    foreach ($lines as $i => $line) {
        $trimmed = trim($line);
        if (str_starts_with($trimmed, '//') || str_starts_with($trimmed, '*')) continue;

        // Skip if htmlspecialchars or similar is used
        if (str_contains($line, 'htmlspecialchars') || str_contains($line, 'htmlentities')) {
            continue;
        }
        if (str_contains($line, 'e(') || str_contains($line, 'escape(')) {
            continue;
        }

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $line)) {
                return ["$path:" . ($i + 1) . ": XSS risk. Escape output with htmlspecialchars()."];
            }
        }
    }

    return [];
}

function checkUnserialize(string $path, array $lines, array $config): array
{
    if (!($config['security']['banUnserialize'] ?? false)) return [];

    foreach ($lines as $i => $line) {
        $trimmed = trim($line);
        if (str_starts_with($trimmed, '//') || str_starts_with($trimmed, '*')) continue;

        if (preg_match('/\bunserialize\s*\(/', $line)) {
            return ["$path:" . ($i + 1) . ": unserialize() is dangerous. Use json_decode() instead."];
        }
    }
    return [];
}

function checkIncludeVariable(string $path, array $lines, array $config): array
{
    if (!($config['security']['banIncludeVariable'] ?? false)) return [];

    $patterns = [
        '/\b(?:include|include_once|require|require_once)\s*\(\s*\$/',  // include($var)
        '/\b(?:include|include_once|require|require_once)\s+\$/',       // include $var
    ];

    foreach ($lines as $i => $line) {
        $trimmed = trim($line);
        if (str_starts_with($trimmed, '//') || str_starts_with($trimmed, '*')) continue;

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $line)) {
                return ["$path:" . ($i + 1) . ": include/require with variable. Local file inclusion risk."];
            }
        }
    }
    return [];
}

function checkFileGetContentsUrl(string $path, array $lines, array $config): array
{
    if (!($config['security']['banFileGetContentsUrl'] ?? false)) return [];

    foreach ($lines as $i => $line) {
        $trimmed = trim($line);
        if (str_starts_with($trimmed, '//') || str_starts_with($trimmed, '*')) continue;

        // file_get_contents with variable (potential SSRF)
        if (preg_match('/\bfile_get_contents\s*\(\s*\$/', $line)) {
            return ["$path:" . ($i + 1) . ": file_get_contents() with variable URL. SSRF risk. Use curl with validation."];
        }
    }
    return [];
}

function checkExtract(string $path, array $lines, array $config): array
{
    if (!($config['security']['banExtract'] ?? false)) return [];

    foreach ($lines as $i => $line) {
        $trimmed = trim($line);
        if (str_starts_with($trimmed, '//') || str_starts_with($trimmed, '*')) continue;

        if (preg_match('/\bextract\s*\(/', $line)) {
            return ["$path:" . ($i + 1) . ": extract() overwrites variables. Use explicit assignment."];
        }
    }
    return [];
}

function checkAssert(string $path, array $lines, array $config): array
{
    if (!($config['security']['banAssert'] ?? false)) return [];

    foreach ($lines as $i => $line) {
        $trimmed = trim($line);
        if (str_starts_with($trimmed, '//') || str_starts_with($trimmed, '*')) continue;

        // assert with string is code execution
        if (preg_match('/\bassert\s*\(\s*[\'"]/', $line) || preg_match('/\bassert\s*\(\s*\$/', $line)) {
            return ["$path:" . ($i + 1) . ": assert() with string/variable. Code execution risk."];
        }
    }
    return [];
}

function checkWeakRandom(string $path, array $lines, array $config): array
{
    if (!($config['security']['banWeakRandom'] ?? false)) return [];

    $weak = ['rand(', 'mt_rand(', 'shuffle(', 'str_shuffle('];

    foreach ($lines as $i => $line) {
        $trimmed = trim($line);
        if (str_starts_with($trimmed, '//') || str_starts_with($trimmed, '*')) continue;

        foreach ($weak as $func) {
            if (str_contains($line, $func)) {
                $name = rtrim($func, '(');
                return ["$path:" . ($i + 1) . ": Weak random '$name'. Use random_bytes() or random_int()."];
            }
        }
    }
    return [];
}

function checkMd5Password(string $path, array $lines, array $config): array
{
    if (!($config['security']['banMd5Password'] ?? false)) return [];

    foreach ($lines as $i => $line) {
        $trimmed = trim($line);
        if (str_starts_with($trimmed, '//') || str_starts_with($trimmed, '*')) continue;

        $lower = strtolower($line);
        if ((str_contains($lower, 'password') || str_contains($lower, 'passwd'))
            && (str_contains($line, 'md5(') || str_contains($line, 'sha1('))) {
            return ["$path:" . ($i + 1) . ": md5/sha1 for password. Use password_hash()."];
        }
    }
    return [];
}

function checkPathTraversal(string $path, array $lines, array $config): array
{
    if (!($config['security']['checkPathTraversal'] ?? false)) return [];

    // File operations with user input
    $fileOps = ['fopen', 'file_get_contents', 'file_put_contents', 'readfile', 'unlink', 'copy', 'rename'];

    foreach ($lines as $i => $line) {
        $trimmed = trim($line);
        if (str_starts_with($trimmed, '//') || str_starts_with($trimmed, '*')) continue;

        foreach ($fileOps as $op) {
            // file op with $_GET, $_POST, $_REQUEST
            if (str_contains($line, $op) && preg_match('/\$_(GET|POST|REQUEST)/', $line)) {
                return ["$path:" . ($i + 1) . ": File operation with user input. Path traversal risk."];
            }
        }
    }
    return [];
}

function checkOpenRedirect(string $path, array $lines, array $config): array
{
    if (!($config['security']['checkOpenRedirect'] ?? false)) return [];

    foreach ($lines as $i => $line) {
        $trimmed = trim($line);
        if (str_starts_with($trimmed, '//') || str_starts_with($trimmed, '*')) continue;

        // header("Location: with variable
        if (preg_match('/header\s*\(\s*[\'"]Location:\s*.*\$/', $line)) {
            return ["$path:" . ($i + 1) . ": Redirect with variable. Open redirect risk. Validate URL."];
        }
        // redirect() with variable
        if (preg_match('/\bredirect\s*\(\s*\$/', $line)) {
            return ["$path:" . ($i + 1) . ": Redirect with variable. Open redirect risk. Validate URL."];
        }
    }
    return [];
}

function checkHardcodedSecrets(string $path, array $lines, array $config): array
{
    if (!($config['security']['checkHardcodedSecrets'] ?? false)) return [];

    $patterns = [
        '/(?:password|passwd|pwd)\s*=\s*[\'"][^\'"]{4,}[\'"]/',
        '/(?:api_key|apikey|api_secret)\s*=\s*[\'"][^\'"]{8,}[\'"]/',
        '/(?:secret|token)\s*=\s*[\'"][^\'"]{8,}[\'"]/',
        '/(?:auth|bearer)\s*=\s*[\'"][^\'"]{8,}[\'"]/',
    ];

    foreach ($lines as $i => $line) {
        $trimmed = trim($line);
        if (str_starts_with($trimmed, '//') || str_starts_with($trimmed, '*')) continue;

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, strtolower($line))) {
                return ["$path:" . ($i + 1) . ": Hardcoded secret. Use environment variables."];
            }
        }
    }
    return [];
}

function checkPromptInjection(string $path, array $lines, array $config): array
{
    if (!($config['security']['checkPromptInjection'] ?? false)) return [];

    // LLM API calls with unsanitized user input
    $llmPatterns = [
        '/(?:openai|anthropic|claude|gpt|llm|chat|completion).*\$_(GET|POST|REQUEST)/',
        '/[\'"](?:messages|prompt|content)[\'"]\s*=>\s*\$/',
        '/->(?:chat|complete|generate)\s*\([^)]*\$_(GET|POST|REQUEST)/',
    ];

    foreach ($lines as $i => $line) {
        $trimmed = trim($line);
        if (str_starts_with($trimmed, '//') || str_starts_with($trimmed, '*')) continue;

        foreach ($llmPatterns as $pattern) {
            if (preg_match($pattern, $line, $matches)) {
                return ["$path:" . ($i + 1) . ": User input in LLM prompt. Prompt injection risk. Sanitize input."];
            }
        }
    }

    // Also check for string concatenation in prompts
    foreach ($lines as $i => $line) {
        $lower = strtolower($line);
        if ((str_contains($lower, 'prompt') || str_contains($lower, 'message'))
            && str_contains($line, ' . $')) {
            return ["$path:" . ($i + 1) . ": Variable concatenation in prompt. Prompt injection risk."];
        }
    }

    return [];
}

function checkBase64Secrets(string $path, array $lines, array $config): array
{
    if (!($config['security']['banBase64Secrets'] ?? false)) return [];

    foreach ($lines as $i => $line) {
        $trimmed = trim($line);
        if (str_starts_with($trimmed, '//') || str_starts_with($trimmed, '*')) continue;

        // base64 encoded strings that look like secrets (long, assigned to secret-like vars)
        if (preg_match('/(?:key|secret|password|token)\s*=\s*[\'"][A-Za-z0-9+\/=]{20,}[\'"]/', strtolower($line))) {
            if (preg_match('/[A-Za-z0-9+\/]{20,}={0,2}/', $line)) {
                return ["$path:" . ($i + 1) . ": Possible base64 encoded secret. Use environment variables."];
            }
        }
    }
    return [];
}

function checkLooseTokenComparison(string $path, array $lines, array $config): array
{
    if (!($config['security']['banCompareTokensLoose'] ?? false)) return [];

    foreach ($lines as $i => $line) {
        $trimmed = trim($line);
        if (str_starts_with($trimmed, '//') || str_starts_with($trimmed, '*')) continue;

        $lower = strtolower($line);
        // Token/hash comparison with == instead of ===
        if ((str_contains($lower, 'token') || str_contains($lower, 'hash') || str_contains($lower, 'csrf'))
            && preg_match('/[^=!]==[^=]/', $line)) {
            return ["$path:" . ($i + 1) . ": Loose comparison (==) on token/hash. Use hash_equals() or ===."];
        }
    }
    return [];
}

function checkSessionRegenerate(string $path, array $lines, array $config): array
{
    if (!($config['security']['banSessionWithoutRegenerate'] ?? false)) return [];

    $hasLogin = false;
    $hasRegenerate = false;

    foreach ($lines as $line) {
        $lower = strtolower($line);
        if (str_contains($lower, 'login') || str_contains($lower, 'authenticate')) {
            $hasLogin = true;
        }
        if (str_contains($line, 'session_regenerate_id')) {
            $hasRegenerate = true;
        }
    }

    // If there's login logic but no session regeneration, warn
    if ($hasLogin && !$hasRegenerate && str_contains(strtolower(implode('', $lines)), 'session')) {
        return ["$path: Login without session_regenerate_id(). Session fixation risk."];
    }

    return [];
}

function checkFileUpload(string $path, array $lines, array $config): array
{
    if (!($config['security']['checkFileUpload'] ?? false)) return [];

    $hasUpload = false;
    $hasMimeCheck = false;
    $hasExtensionCheck = false;

    foreach ($lines as $i => $line) {
        if (str_contains($line, 'move_uploaded_file') || str_contains($line, '$_FILES')) {
            $hasUpload = true;
        }
        if (str_contains($line, 'mime_content_type') || str_contains($line, 'finfo')) {
            $hasMimeCheck = true;
        }
        if (preg_match('/\.(php|phtml|phar|php\d)/', $line) || str_contains($line, 'getClientOriginalExtension')) {
            $hasExtensionCheck = true;
        }
    }

    if ($hasUpload && !$hasMimeCheck) {
        return ["$path: File upload without MIME type validation. Check file type with finfo."];
    }

    return [];
}

function checkErrorDisplay(string $path, array $lines, array $config): array
{
    if (!($config['security']['banErrorDisplay'] ?? false)) return [];

    foreach ($lines as $i => $line) {
        $trimmed = trim($line);
        if (str_starts_with($trimmed, '//') || str_starts_with($trimmed, '*')) continue;

        // display_errors = 1 or On
        if (preg_match('/display_errors\s*[=,]\s*[\'"]?(1|on|true)[\'"]?/i', $line)) {
            return ["$path:" . ($i + 1) . ": display_errors enabled. Hide errors in production."];
        }
        // error_reporting(E_ALL) without display_errors off is suspicious
        if (str_contains($line, 'ini_set') && str_contains($line, 'display_errors')
            && preg_match('/[\'"]1[\'"]|true/i', $line)) {
            return ["$path:" . ($i + 1) . ": display_errors enabled. Hide errors in production."];
        }
    }
    return [];
}

function checkDebugMode(string $path, array $lines, array $config): array
{
    if (!($config['security']['banDebugMode'] ?? false)) return [];

    foreach ($lines as $i => $line) {
        $trimmed = trim($line);
        if (str_starts_with($trimmed, '//') || str_starts_with($trimmed, '*')) continue;

        // APP_DEBUG = true, DEBUG = true, etc.
        if (preg_match('/[\'"]?(APP_)?DEBUG[\'"]?\s*[=:]\s*[\'"]?true[\'"]?/i', $line)) {
            return ["$path:" . ($i + 1) . ": Debug mode enabled. Disable in production."];
        }
    }
    return [];
}

function checkFile(string $path, array $config): array
{
    $content = file_get_contents($path);
    $lines = explode("\n", $content);

    $violations = [];

    $violations = array_merge($violations, checkFileSize($path, $lines, $config));
    $violations = array_merge($violations, checkFunctionSize($path, $content, $config));
    $violations = array_merge($violations, checkDebugStatements($path, $lines, $config));
    $violations = array_merge($violations, checkTodo($path, $lines, $config));
    $violations = array_merge($violations, checkMockData($path, $lines, $config));
    $violations = array_merge($violations, checkDangerousFunctions($path, $lines, $config));
    $violations = array_merge($violations, checkDeprecatedMysql($path, $content, $config));
    $violations = array_merge($violations, checkRawSuperGlobals($path, $lines, $config));
    $violations = array_merge($violations, checkSqlInjection($path, $lines, $config));
    $violations = array_merge($violations, checkXss($path, $lines, $config));

    // Additional security checks
    $violations = array_merge($violations, checkUnserialize($path, $lines, $config));
    $violations = array_merge($violations, checkIncludeVariable($path, $lines, $config));
    $violations = array_merge($violations, checkFileGetContentsUrl($path, $lines, $config));
    $violations = array_merge($violations, checkExtract($path, $lines, $config));
    $violations = array_merge($violations, checkAssert($path, $lines, $config));
    $violations = array_merge($violations, checkWeakRandom($path, $lines, $config));
    $violations = array_merge($violations, checkMd5Password($path, $lines, $config));
    $violations = array_merge($violations, checkPathTraversal($path, $lines, $config));
    $violations = array_merge($violations, checkOpenRedirect($path, $lines, $config));
    $violations = array_merge($violations, checkHardcodedSecrets($path, $lines, $config));
    $violations = array_merge($violations, checkPromptInjection($path, $lines, $config));
    $violations = array_merge($violations, checkBase64Secrets($path, $lines, $config));
    $violations = array_merge($violations, checkLooseTokenComparison($path, $lines, $config));
    $violations = array_merge($violations, checkSessionRegenerate($path, $lines, $config));
    $violations = array_merge($violations, checkFileUpload($path, $lines, $config));
    $violations = array_merge($violations, checkErrorDisplay($path, $lines, $config));
    $violations = array_merge($violations, checkDebugMode($path, $lines, $config));

    return $violations;
}

// ---------------------------------------------------------------------------
// Main
// ---------------------------------------------------------------------------

function main(): int
{
    $config = loadConfig();
    $violations = [];
    $fileCount = 0;

    foreach ($config['srcDirs'] as $srcDir) {
        foreach (iterPhpFiles($srcDir, $config['exclude']) as $path) {
            $fileCount++;
            $violations = array_merge($violations, checkFile($path, $config));
        }
    }

    if ($fileCount === 0) {
        echo "guardian: No files found in " . implode(', ', $config['srcDirs']) . "\n";
        return 0;
    }

    if (count($violations) > 0) {
        echo str_repeat('=', 60) . "\n";
        echo "GUARDIAN\n";
        echo str_repeat('=', 60) . "\n";

        $unique = array_unique($violations);
        sort($unique);
        foreach ($unique as $v) {
            echo "  $v\n";
        }

        echo str_repeat('=', 60) . "\n";
        echo count($unique) . " issue(s)\n";
        return 1;
    }

    echo "guardian: $fileCount files OK\n";
    return 0;
}

exit(main());
