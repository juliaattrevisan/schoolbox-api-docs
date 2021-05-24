<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Course\Import\Atlas\Api;

use Karriere\JsonDecoder\Bindings\ArrayBinding;
use Karriere\JsonDecoder\ClassBindings;
use Karriere\JsonDecoder\Transformer;

/**
 * Transformer definition for JSON assessment responses to PHP objects.
 */
class AssessmentTransformer implements Transformer
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
        $classBindings->register(new ArrayBinding('standards', 'standards', Standard::class));
    }

    /**
     * @return string
     */
    public function transforms(): string
    {
        return Assessment::class;
    }
}
