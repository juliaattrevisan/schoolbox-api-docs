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
     * Constructor
     *
     * @param EscapeStrategy\IEscapeStrategy $escapeStrategy An strategy to use to escape/unescape replaced tokens:
     *                                                       defaults to `EscapeStrategy\ScrubText` for scrubbing using
     *                                                       a `htmlspecialchars` based strategy.
     */
    public function __construct(private EscapeStrategy\IEscapeStrategy $escapeStrategy = new EscapeStrategy\ScrubText())
    {
    }

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
        $sanitisedReplacements = array_map(
            fn ($val) => $this->escapeStrategy->escape($val),
            array_values($replacements),
        );

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
        // Split $body into token and non-token parts
        while ($body !== '') {
            // Find next `[]` pair
            $tokenOpen = strpos($body, '[');
            if ($tokenOpen !== false) {
                $tokenClose = strpos($body, ']', $tokenOpen);
                $before = substr($body, 0, $tokenOpen);
                $token = substr($body, $tokenOpen, $tokenClose - $tokenOpen + 1);
                $after = substr($body, $tokenClose + 1);
                if ($before !== '') {
                    $tokens[] = $before;
                }
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
                    if (!str_starts_with($nextToken, '[')) {
                        $nextNonReplaceToken = $nextToken;
                        break;
                    }
                }

                if (isset($nextNonReplaceToken)) {
                    $strpos = strpos($replacedBody, $nextNonReplaceToken);
                } else {
                    $strpos = false;
                }
                if ($strpos !== false) {
                    $replacements[$replaceKey] = substr($replacedBody, 0, $strpos);
                    $replacedBody = substr($replacedBody, $strpos);
                } else {
                    $replacements[$replaceKey] = $replacedBody;
                    break;
                }

                // note that if there's two tokens together, it's not possible
                // to determine where one starts and the next ends, so need to
                // guess.
                // Guess that the first token takes up all of the length, and
                // the second none of it.
            } else {
                // it's not a replace: skip
                $replacedBody = substr($replacedBody, strlen($token));
            }
        }

        // Because tokens are scrubbed, unscrub them
        return array_map($this->escapeStrategy->unescape(...), $replacements);
    }


    /**
     * Replace using Scrub::html, to return replaced html
     *
     * @deprecated use escape strategy `IEscapeStrategy\ScrubHtml` instead
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
