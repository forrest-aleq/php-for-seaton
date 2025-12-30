<?php

declare(strict_types=1);

$finder = PhpCsFixer\Finder::create()
    ->in(['src', 'app'])
    ->exclude(['vendor', 'storage', 'cache'])
    ->name('*.php')
    ->notName('*.blade.php');

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@PSR12' => true,
        '@PHP82Migration' => true,

        // Real improvements, not just style
        'declare_strict_types' => true,
        'strict_param' => true,
        'strict_comparison' => true,

        // Type safety
        'void_return' => true,
        'return_type_declaration' => true,

        // Array syntax
        'array_syntax' => ['syntax' => 'short'],
        'no_whitespace_before_comma_in_array' => true,
        'trim_array_spaces' => true,

        // Clean code
        'no_unused_imports' => true,
        'ordered_imports' => ['sort_algorithm' => 'alpha'],
        'single_quote' => true,
        'no_empty_statement' => true,
        'no_useless_else' => true,
        'no_useless_return' => true,

        // Don't over-format
        'concat_space' => ['spacing' => 'one'],
        'binary_operator_spaces' => true,
    ])
    ->setFinder($finder);
