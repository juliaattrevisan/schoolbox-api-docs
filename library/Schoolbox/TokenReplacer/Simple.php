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
     * Attempt to perform the reverse of `replace`: given a body before and
     * after its tokens were replaced, figure out which replacements were used.
     *
     * @param string $body
     * @param string $replacedBody
     * @return array
     */
    public function extract(string $body, string $replacedBody): array
    {
//        $manifest = $this->manifest($body);
//        foreach ($manifest as $token) {
//            // Find the first place in $replacedBody where $token would occur:
//            // then, extract its value
//            // May need to split $body by token.
//        }

        // Split $body into token and non-token parts
        $openChar = '[';
        $closeChar = ']';

        $regexp = '/(.*?)(\[[^]]+])(.*)/';
        while ($body !== '') {
            // Find next `[]` pair
            if (preg_match($regexp, $body, $matches)) {
                [, $before, $token, $after] = $matches;
                $tokens[] = $before;
                $tokens[] = $token;
                $body = $after;
            } else {
                $tokens[] = $body;
                break;
            }
        }

        // Now, address tokens
        $replacements = [];
        while (!empty($tokens)) {
            $token = array_shift($tokens);
            if (str_starts_with($token, '[')) {
                // it's a replace:
                // lookahead to the next non-token and extract its value
                $replaceKey          = $token;
                $nextNonReplaceToken = null;
                foreach ($tokens as $nextToken) {
                    if (!str_starts_with($token, '[')) {
                        $nextNonReplaceToken = $nextToken;
                        break;
                    }
                }

                if (isset($nextNonReplaceToken)) {
                    $strpos = strpos($nextNonReplaceToken, $body);
                    $replacements[$replaceKey] = substr($body, 0, $strpos);
                    $body = substr($body, $strpos);
                } else {
                    $replacements[$replaceKey] = $body;
                    $body = '';
                }

                // note that if there's two tokens together, it's not possible
                // to determine where one starts and the next ends, so need to
                // guess.
                // Guess that the first token takes up all of the length, and
                // the second none of it.
            } else {
                // it's not a replace: skip
                $body = substr($body, strlen($token));
            }
        }

        // Because tokens are scrubbed, unscrub them
        // TODO inject a replace strategy
        return $replacements;
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
