<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse;

const BASE = __DIR__.DIRECTORY_SEPARATOR;

$paths = [
    __FILE__,
    BASE."arsse.php",
    BASE."RoboFile.php",
    BASE."lib",
    BASE."tests",
];
$rules = [
    // PSR standard to apply
    '@PSR12'                                    => true,
    // house rules where PSR series is silent
    'align_multiline_comment'                   => ['comment_type' => "phpdocs_only"],
    'array_syntax'                              => ['syntax' => "short"],
    'binary_operator_spaces'                    => [
        'default'   => "single_space",
        'operators' => ['=>' => "align_single_space"],
    ],
    'cast_spaces'                               => ['space' => "single"],
    'concat_space'                              => ['spacing' => "none"],
    'list_syntax'                               => ['syntax' => "short"],
    'magic_constant_casing'                     => true,
    'magic_method_casing'                       => true,
    'modernize_types_casting'                   => true,
    'native_function_casing'                    => true,
    'native_function_type_declaration_casing'   => true,
    'no_binary_string'                          => true,
    'no_blank_lines_after_phpdoc'               => true,
    'no_empty_comment'                          => true,
    'no_empty_phpdoc'                           => true,
    'no_extra_blank_lines'                      => true, // this could probably use more configuration
    'no_mixed_echo_print'                       => ['use' => "echo"],
    'no_short_bool_cast'                        => true,
    'no_trailing_comma_in_singleline_array'     => true,
    'no_unneeded_control_parentheses'           => true,
    'no_unneeded_curly_braces'                  => true,
    'no_unused_imports'                         => true,
    'no_whitespace_before_comma_in_array'       => true,
    'normalize_index_brace'                     => true,
    'object_operator_without_whitespace'        => true,
    'pow_to_exponentiation'                     => true,
    'set_type_to_cast'                          => true,
    'standardize_not_equals'                    => true,
    'trailing_comma_in_multiline'               => true,
    'unary_operator_spaces'                     => true,
    'yoda_style'                                => false,
    // house exceptions to PSR rules
    'braces'                                    => ['position_after_functions_and_oop_constructs' => "same"],
    'function_declaration'                      => ['closure_function_spacing' => "none"],
    'new_with_braces'                           => [
        'anonymous_class' => false,
        'named_class'     => false,
    ],
    'single_blank_line_before_namespace'        => false,
    'blank_line_after_opening_tag'              => false,
    ];

$finder = \PhpCsFixer\Finder::create();
foreach ($paths as $path) {
    if (is_file($path)) {
        $finder = $finder->append([$path]);
    } else {
        $finder = $finder->in($path);
    }
}
return (new \PhpCsFixer\Config)->setRiskyAllowed(true)->setRules($rules)->setFinder($finder);
