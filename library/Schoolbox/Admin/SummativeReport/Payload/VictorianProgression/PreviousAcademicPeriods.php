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
        $reportingStart = null;
        $reportingEnd = null;

        /* $previousAcademicPeriods is assumed to be an ordered set,
         * ordered by start, desc
         */
        foreach ($previousAcademicPeriods as $previousAcademicPeriod) {
            /* Skip archived terms
             */
//            if ($previousAcademicPeriod->getArchived()) {
//                continue;
//            }

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
                if (null === $reportingEnd) {
                    $reportingEnd = $previousAcademicPeriod->getDisplayFinish();
                }

                if (null === $reportingStart) {
                    $reportingStart = $previousAcademicPeriod->getStart();
                }

                /* We are traversing in reverse chronological order, so keep going back in time
                 * to find the oldest start date we can
                 */
                if ($reportingStart->gt($previousAcademicPeriod->getStart())) {
                    $reportingStart = $previousAcademicPeriod->getStart();
                }

                $n++;
            }
        }

        return [
            'start' => $reportingStart,
            'end' => $reportingEnd
        ];
    }
}
