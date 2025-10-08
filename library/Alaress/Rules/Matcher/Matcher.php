<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Alaress\Rules\Matcher;

use PHLAK\Splat\Pattern;

/**
 * Matcher for a glob-like pattern against a string.
 */
class Matcher
{
    /**
     * Internal regular expression, compiled once when `$this->match` is called.
     */
    private ?string $regexp = null;

    /**
     * Constructor
     *
     * @param string $pattern The pattern to match against.
     *                        This pattern may contain wildcards (@see https://github.com/PHLAK/Splat):
     *                        * "*" matches any character sequence
     *                        * "?" matches any single character
     *                        * "[abc]" matches any character in the set
     *                        * "[a-c]" matches any character in the range
     *                        * "[^abc]" (or "[!abc]") matches any character not in the set
     *                        * "[^a-c]" (or "[!a-c]") matches any character not in the range
     *                        * "{foo,bar,baz}" matches if any of the comma-separated sub-patterns also match
     *                        * "(=foo)" matches if the string also contains the pattern
     *                        * "(!foo)" matches if the string doesn't contain the pattern
     *                        * "\" escapes any wildcard character
     */
    public function __construct(private string $pattern)
    {
    }

    /**
     * Does `$input` match `$this->pattern`?
     *
     * @param string $input
     * @return bool
     */
    public function match(string $input): bool
    {
        $this->regexp ??= $this->buildRegexp($this->pattern);
        $isMatch = @preg_match($this->regexp, $input);
        if (preg_last_error() !== PREG_NO_ERROR) {
            return false;
        }
        return (bool)$isMatch;
    }

    /**
     * Helper method for building the regular expression for matching against.
     *
     * @param string $pattern
     * @return string
     */
    private function buildRegexp(string $pattern): string
    {
        $pattern = preg_replace_callback(
            '/\\\\*\\[\^/',
            function ($matches) {
                // Is there an even number of escape chars (inc. 0)?
                // Replace if so: otherwise do not
                [$match] = $matches;
                if (strlen($match) % 2 === 0) {
                    return str_replace('[^', '[!', $match);
                }
                return $match;
            },
            $pattern,
        );

        /* Special case to accommodate user experience.
         * Double-asterisk ** matches data where there are slashes, such as in the example:
         *
         * Chemistry/Physics Teacher & Assistant Director of Academic Operations 7-12 (SS)
         *
         * Having to educate users on the nuance between * and ** is probably too annoying so
         * we'll do it on their behalf, saving them the thinking. This vendor library is a
         * directory parser and we're using it a bit obliquely here as we don't experience
         * folder path problems in the UPS space.
         */
        $pattern = str_replace('*', '**', $pattern);

        return (new Pattern($pattern))->toRegex() . 'i';
    }
}
