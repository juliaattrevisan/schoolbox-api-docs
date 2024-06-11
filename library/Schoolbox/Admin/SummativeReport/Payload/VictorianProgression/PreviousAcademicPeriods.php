<?php

/**
 * @copyright   Copyright 2024, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\SummativeReport\Payload\VictorianProgression;

use Schoolbox\Assessment\Term\Model;
use Schoolbox\GenericCollection;

class PreviousAcademicPeriods
{
    /**
     * How far back into the past do we care about? Default is 2 academic periods
     * that occurred before the current academic period.
     */
    private const NUM_PREVIOUS_ACADEMIC_PERIODS = 2;


    /**
     * Constructor
     */
    public function __construct()
    {
    }


    /**
     * Find the next two oldest academic periods that start after $currentAcademicPeriod
     *
     * @param Model $currentAcademicPeriod
     * @param GenericCollection $previousAcademicPeriods
     * @return array
     */
    public function generate(Model $currentAcademicPeriod, GenericCollection $previousAcademicPeriods): array
    {
        /* Accumulate two previous academic periods' dates, if they exist,
         * to specify a new accumulated range.
         */
        $n = 0;
        $start = null;
        $end = null;

        /* $previousAcademicPeriods is assumed to be an ordered set,
         * ordered by start, desc
         */
        foreach ($previousAcademicPeriods as $previousAcademicPeriod) {
            /* Skip archived terms
             */
            if ($previousAcademicPeriod->getArchived()) {
                continue;
            }

            /* Skip past academic periods more recent than $currentAcademicPeriod
             */
            if ($previousAcademicPeriod->getStart()->gte($currentAcademicPeriod->getStart())) {
                continue;
            }

            /* We care about these ones
             */
            if ($n < self::NUM_PREVIOUS_ACADEMIC_PERIODS) {
                /* Initial conditions
                 */
                if (null === $start) {
                    $start = $previousAcademicPeriod->getStart();
                }

                if (null === $end) {
                    $end = $previousAcademicPeriod->getDisplayFinish();
                }

                /* Update $end
                 */
                if ($end->gt($previousAcademicPeriod->getDisplayFinish())) {
                    $end = $previousAcademicPeriod->getDisplayFinish();
                }

                $n++;
            }
        }

        /* We are traversing in reverse chronological order, so $start will be more
         * recent than $end. Both datetimes encapsulate the time period we care about
         * but we have to flip them to be programmatically meaningful.
         */

        return [
            'start' => $end,
            'end' => $start
        ];
    }
}
