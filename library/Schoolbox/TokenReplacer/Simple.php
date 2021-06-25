<?php
/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\TokenReplacer;

/**
 * Class Simple
 *
 * Does a simple 1:1 string replace from one array to another
 *
 * @package Schoolbox\TokenReplacer
 */
class Simple
{

    /**
     * Token format
     */
    private const TOKEN_FORMAT = '/[^]]/';


    /**
     * $replacements is a set of key-value tuples:
     *
     * [
     *     'firstname' => $var1,
     *     'lastname' => $var2,
     *     ...
     * ]
     *
     * $body gets its tokens detected and replaced accordingly,
     * returning a version of $body with all the substitutions
     *
     * Tokens that exist, but aren't in $replacements, will remain
     * untouched by the substitutions.
     *
     * $replacement values that exist, but aren't represented by
     * tokens in $body, will remain unused.
     *
     * @param string $body
     * @param array  $replacements
     * @return string
     */
    public function replace(string $body, array $replacements): string
    {
        $newBody = '';

        return $newBody;
    }


    /**
     * Diagnostic for seeing which tokens are available for substitutions
     *
     * @param string $body
     */
    public function manifest(string $body): array
    {
    }
}