<?php

declare(strict_types=1);

$finder = PhpCsFixer\Finder::create()
    ->exclude([
        'vendor',
        'var',
        'config',
    ])
    ->notPath('public/index.php')
    ->notPath('tests/bootstrap.php')
    ->in(__DIR__)
    ->name('*.php')
    ->ignoreDotFiles(true);

$config = new PhpCsFixer\Config();

return $config
    ->setFinder($finder)
    ->setRules([
        'declare_strict_types' => true,
        '@PhpCsFixer' => true,
        '@PHP8x5Migration' => true,
        'php_unit_method_casing' => ['case' => 'snake_case'],
        'phpdoc_summary' => false,
        'yoda_style' => [
            'equal' => false,
            'identical' => false,
            'less_and_greater' => false,
        ]
    ]);
