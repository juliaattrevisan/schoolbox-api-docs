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
                'rawText',
                function ($row) {
                    // $row... isn't; it's just a string
                    return $row;
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
}
