<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Course\Import\Atlas\Api;

use Alaress_Date_Format;
use Karriere\JsonDecoder\Bindings\AliasBinding;
use Karriere\JsonDecoder\Bindings\CallbackBinding;
use Karriere\JsonDecoder\ClassBindings;
use Karriere\JsonDecoder\Transformer;

/**
 * Transformer definition for JSON unit responses to PHP objects.
 */
class UnitTransformer implements Transformer
{
    /**
     * Register bindings for this class.
     *
     * @noinspection PhpDocMissingThrowsInspection
     *
     * @param ClassBindings $classBindings
     */
    public function register(ClassBindings $classBindings)
    {
        /** @noinspection PhpUnhandledExceptionInspection */
        $classBindings->register(new AliasBinding('timeFrame', 'time_frame'));

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
        $classBindings->register(
            new CallbackBinding(
                'startDate',
                function ($row) {
                    return new Alaress_Date_Format($row['start_date'], Alaress_Date_Format::DATABASE_DATE_FORMAT);
                }
            )
        );

        /** @noinspection PhpUnhandledExceptionInspection */
        $classBindings->register(
            new CallbackBinding(
                'endDate',
                function ($row) {
                    return new Alaress_Date_Format($row['end_date'], Alaress_Date_Format::DATABASE_DATE_FORMAT);
                }
            )
        );
    }

    /**
     * @return string
     */
    public function transforms(): string
    {
        return Unit::class;
    }
}
