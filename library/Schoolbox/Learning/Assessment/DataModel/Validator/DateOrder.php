<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\DataModel\Validator;

use Alaress_Date_Format;
use InvalidArgumentException;
use Schoolbox\Learning\Assessment\DataModel\Model;

/**
 * Validator for date ordering in an assessment.
 */
class DateOrder
{
    /**
     * Validate that the given dates are in order, if they are set:
     * * publish
     * * open
     * * due
     * * close
     *
     * @param Model $assessment
     * @return void
     * @throws InvalidArgumentException if dates are out of order
     */
    public static function validate(Model $assessment): void
    {
        /** @var array<array{0: string, 1: Alaress_Date_Format|null}> $dates */
        $dates = [
            ['publish', $assessment->getPublishDate()],
            ['open', $assessment->getOpenDate()],
            ['due', $assessment->getDueDate()],
            ['close', $assessment->getCloseDate()],
        ];
        /** @var array{0: string, 1: Alaress_Date_Format|null}|null $previousDateTuple */
        $previousDateTuple = null;
        foreach ($dates as $dateTuple) {
            [$type, $date] = $dateTuple;
            if (!$date) {
                continue;
            }
            if ($previousDateTuple) {
                [$previousType, $previousDate] = $previousDateTuple;
            }
            if (isset($previousType, $previousDate) && $previousDate->gt($date)) {
                throw new InvalidArgumentException(
                    sprintf(
                        'Assessment dates are out of order: %s date must be before or equal to %s date',
                        $previousType,
                        $type,
                    )
                );
            }
            $previousDateTuple = $dateTuple;
        }
    }
}
