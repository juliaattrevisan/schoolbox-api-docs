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
     * $replacements is a set of key-value tuples just like phplib:
     *
     * [
     *     '[firstname]' => $var1,
     *     '[lastname]' => $var2,
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
        $body = $this->stripMentionAttribute($body);
        $sanitisedReplacements = array_map(static function ($val) {
            return \Scrub::text($val);
        }, array_values($replacements));

        return str_replace(array_keys($replacements), $sanitisedReplacements, $body);
    }


    /**
     * Replace using Scrub::html, to return replaced html
     * @param string $body
     * @param array  $replacements
     * @return string
     */
    public function replaceHtml(string $body, array $replacements): string
    {
        $body = $this->stripMentionAttribute($body);
        $sanitisedReplacements = array_map(static function ($val) {
            return \Scrub::html($val);
        }, array_values($replacements));

        return str_replace(array_keys($replacements), $sanitisedReplacements, $body);
    }


    /**
     * Diagnostic for seeing which tokens are available for substitutions
     *
     * @param string $body
     * @return array
     */
    public function manifest(string $body): array
    {

        /* Find all the tokens
         */
        preg_match_all('/\[[^]]+\]/m', $body, $matches);

        /* Remove dupes and reindex
         */
        return array_values(array_unique($matches[0]));
    }


    /**
     * Attempt to remove attributes from the target element
     *
     * @param string $content
     * @return string
     */
    public function stripMentionAttribute(
        string $content,
    ): string {
        $regex = '/<span class="mention" data-mention="(.*)">\1<\/span>/';
        return preg_replace($regex, '$1', $content);
    }
}
