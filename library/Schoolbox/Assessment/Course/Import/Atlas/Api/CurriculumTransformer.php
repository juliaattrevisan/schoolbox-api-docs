<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Course\Import\Atlas\Api;

use Alaress_Date_Format;
use Karriere\JsonDecoder\Bindings\AliasBinding;
use Karriere\JsonDecoder\Bindings\ArrayBinding;
use Karriere\JsonDecoder\Bindings\CallbackBinding;
use Karriere\JsonDecoder\ClassBindings;
use Karriere\JsonDecoder\Transformer;

/**
 * Transformer definition for JSON curriculum responses to PHP objects.
 */
class CurriculumTransformer implements Transformer
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
        $classBindings->register(new AliasBinding('mapType', 'map_type'));

        /** @noinspection PhpUnhandledExceptionInspection */
        $classBindings->register(
            new CallbackBinding(
                'lastUpdated',
                function ($row) {
                    return new Alaress_Date_Format($row['last_updated'], Alaress_Date_Format::DATABASE_FORMAT_MS);
                }
            )
        );

        /** @noinspection PhpUnhandledExceptionInspection */
        $classBindings->register(new ArrayBinding('units', 'units', Unit::class));

        /** @noinspection PhpUnhandledExceptionInspection */
        $classBindings->register(new ArrayBinding('teachers', 'teachers', Teacher::class));
    }

    /**
     * @return string
     */
    public function transforms(): string
    {
        return Curriculum::class;
    }
}
