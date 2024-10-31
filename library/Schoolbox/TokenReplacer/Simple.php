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
     * Open character for a token.
     */
    private const TOKEN_OPEN = '[';

    /**
     * Close character for a token.
     */
    private const TOKEN_CLOSE = ']';

    /**
     * Regular expression for finding tokens within a body, used when building
     * its manifest.
     */
    private const MANIFEST_REGEXP = '/\[[^]]+\]/m';

    /**
     * Regular express for finding mention attributes to be stripped from the
     * body.
     */
    private const MENTION_REGEXP  = '/<span class="mention" data-mention="(.*)">\1<\/span>/';

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
     * Due to the non-structured nature of text, this is not foolproof:
     * if `$body` contains two adjacent tokens (e.g. "[token1][token2]"), this
     * cannot tell which part of `$replacedBody` makes up which token.
     *
     * @param string $body
     * @param string $replacedBody
     * @return array|false A set of tokens, or false if `$body` could not
     *                     possibly generate `$replacedBody`.
     */
    public function extract(string $body, string $replacedBody): array|false
    {
        // Strip out mention attribute, just as replace does
        $body = $this->stripMentionAttribute($body);

        // Split $body into token and non-token parts
        $bodyParts = [];
        while ($body !== '') {
            // Find next token open/close pair
            $tokenOpenPos = strpos($body, self::TOKEN_OPEN);
            if ($tokenOpenPos !== false) {
                // There's a token:
                // split the body into the characters before, within and after
                // the token, then find the next token in the body.
                $tokenClosePos = strpos($body, self::TOKEN_CLOSE, $tokenOpenPos);
                $before        = substr($body, 0, $tokenOpenPos);
                $token         = substr($body, $tokenOpenPos, $tokenClosePos - $tokenOpenPos + 1);
                $after         = substr($body, $tokenClosePos + 1);
                if ($before !== '') {
                    $bodyParts[] = $before;
                }
                $bodyParts[] = $token;
                $body = $after;
            } else {
                // There are no more tokens:
                // put the rest of the body in the list of parts.
                $bodyParts[] = $body;
                break;
            }
        }

        // Now, find tokens and their replacements.
        $replacements = [];
        while (!empty($bodyParts)) {
            $bodyPart = array_shift($bodyParts);
            if (str_starts_with($bodyPart, self::TOKEN_OPEN)) {
                // This is a token:
                // find the next non-token body part, and find its position in
                // `$replacedBody`.
                // Anything before that position must be the value which was
                // passed into `replace`.
                $replaceKey   = $bodyPart;
                $nextNonToken = null;
                foreach ($bodyParts as $nextBodyPart) {
                    if (!str_starts_with($nextBodyPart, self::TOKEN_OPEN)) {
                        $nextNonToken = $nextBodyPart;
                        break;
                    }
                }

                $replacements[$replaceKey] = '';
                if (!isset($nextNonToken)) {
                    // No more non-tokens:
                    // the entire remainder of `$replacedBody` must be the
                    // token value.
                    $replacements[$replaceKey] = $replacedBody;
                    $replacedBody = '';
                } else {
                    // There is a non-token upcoming:
                    // find where in `$replacedBody` it starts.
                    $nextNonTokenPos = strpos($replacedBody, $nextNonToken);
                    if ($nextNonTokenPos !== false) {
                        // The replacement must be whatever is before the start
                        // of the next non-token:
                        // process the rest of `$replacedBody`.
                        $replacements[$replaceKey] = substr($replacedBody, 0, $nextNonTokenPos);
                        $replacedBody = substr($replacedBody, $nextNonTokenPos);
                    } else {
                        // If next non-token isn't in `$replacedBody` at all,
                        // this can't possibly match.
                        return false;
                    }
                }
            } else {
                // This is not a token:
                // remove its character count from `$replacedBody`, and continue
                $replacedBody = substr($replacedBody, strlen($bodyPart));
            }
        }

        // Tokens will have been escaped in `replace`: unescape them
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
        preg_match_all(self::MANIFEST_REGEXP, $body, $matches);

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
        return preg_replace(self::MENTION_REGEXP, '$1', $content);
    }
}
