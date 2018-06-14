<?php
/**
 * @copyright Copyright 2018, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Course\Import\Atlas\Api;

use Karriere\JsonDecoder\Bindings\CallbackBinding;
use Karriere\JsonDecoder\ClassBindings;
use Karriere\JsonDecoder\Transformer;

/**
 * Transformer definition for JSON standard responses to PHP objects.
 */
class StandardTransformer implements Transformer
{
    /**
     * Register bindings for this class.
     *
     * @noinspection PhpDocMissingThrowsInspection
     *
     * @param ClassBindings $classBindings
     */
    public function register(ClassBindings $classBindings): void
    {
        /** @noinspection PhpUnhandledExceptionInspection */
        $classBindings->register(
            new CallbackBinding(
                'title',
                function ($row) {
                    // $row... isn't; it's just a string
                    // Extract title out
                    return $this->parseStandard($row)['title'];
                }
            )
        );

        /** @noinspection PhpUnhandledExceptionInspection */
        $classBindings->register(
            new CallbackBinding(
                'code',
                function ($row) {
                    // Unfortunately I must parse code twice,
                    // I can't map two attributes in one binding
                    return $this->parseStandard($row)['code'];
                }
            )
        );
    }

    /**
     * @return string
     */
    public function transforms(): string
    {
        return Standard::class;
    }

    /**
     * Parses a string standard into something renderable.
     *
     * This is required because Atlas stores curriculum codes as part of a
     * string value.
     *
     * @param string $standard
     * @return array
     */
    private function parseStandard(string $standard): array
    {
        // ACARA strategy: find a link to Scootle, use it
        if (preg_match('[>(.*?)</a>]i', $standard, $matches)) {
            [, $code] = $matches;
            $standard = strip_tags($standard);
        } else {
            $code = '';
        }

        return [
            'title' => $standard,
            'code'  => $code,
        ];
    }
}
