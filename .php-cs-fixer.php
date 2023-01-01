<?php

/*
 * SPDX-License-Identifier: MIT or Apache-2.0
 */

declare(strict_types=1);

$header = <<<'EOF'
SPDX-License-Identifier: MIT or Apache-2.0
EOF;

$finder = PhpCsFixer\Finder::create()
    ->ignoreDotFiles(true)
    ->ignoreVCSIgnored(true)
    ->exclude('vendor')
    ->in(__DIR__)
    ->append([__FILE__])
;

return (new PhpCsFixer\Config())
    ->setFinder($finder)
    ->setRiskyAllowed(true)
    ->setRules([
        '@PER' => true,
        '@PER:risky' => true,
        'header_comment' => ['header' => $header, 'location' => 'after_open'],
        'no_extra_blank_lines' => [
            'tokens' => [
                'attribute', 'case', 'continue', 'curly_brace_block', 'default',
                'extra', 'parenthesis_brace_block', 'square_brace_block',
                'switch', 'throw', 'use',
            ]
        ],
        'no_spaces_around_offset' => true,
        'ordered_imports' => ['sort_algorithm' => 'alpha'],
        'function_typehint_space' => true,
        'single_space_after_construct' => true,
        'array_syntax' => ['syntax' => 'short'],
        'no_whitespace_before_comma_in_array' => ['after_heredoc' => true],
        'no_singleline_whitespace_before_semicolons' => true,
        'array_indentation' => true,
        'method_chaining_indentation' => true,
        'types_spaces' => true,
        'no_trailing_comma_in_singleline' => true,
        'no_multiline_whitespace_around_double_arrow' => true,
        'trim_array_spaces' => true,
        'normalize_index_brace' => true,
        'class_reference_name_casing' => true,
        'ordered_class_elements' => [
            'order' => [
                'use_trait', 'case',
                'constant_public', 'constant_protected', 'constant_private',
                'property_public', 'property_protected', 'property_private',
                'construct',
                'method_public', 'method_protected', 'method_private',
                'destruct'
            ]
        ],
    ])
;
