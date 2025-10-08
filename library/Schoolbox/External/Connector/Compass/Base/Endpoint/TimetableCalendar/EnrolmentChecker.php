<?php

namespace Schoolbox\External\Connector\Compass\Base\Endpoint\TimetableCalendar;

use Psr\Log\LoggerInterface;

/**
 * Decides whether someone's enrolment
 */
class EnrolmentChecker
{
    /**
     * @param LoggerInterface $log
     */
    public function __construct(private LoggerInterface $log)
    {
    }


    /**
     * Figure out if this person's enrolment in a particular class expired.
     * If so, process a REMOVE override for them elsewhere.
     *
     * @param array $subject
     * @param string $title
     * @param string $classCode
     * @param \Alaress_Date_Format $startAt
     * @param \Alaress_Date_Format $finishAt
     * @return bool
     */
    public function isEnrolled(
        array $subject,
        string $title,
        string $classCode,
        \Alaress_Date_Format $startAt,
        \Alaress_Date_Format $finishAt,
    ): bool {
        /* Compare this subject's TO_DATE to $startAt
         * Subjects that aren't actually relevant to this date still appear here
         * for us to manually deduce validity (thanks tass)
         *
         * FROM_DATE and TO_DATE are optionally mandatory - they could be empty string/null
         * or not even here, despite what the docs say: Undefined array key "TO_DATE"
         */
        $subjectTo = substr(trim((string)($subject['TO_DATE'] ?? null)), 0, 19);
        $subjectFrom = substr(trim((string)($subject['FROM_DATE'] ?? null)), 0, 19);

        /* If there's a conclusive date range for this subject, then it's going to expire
         * at a particular datetime and we need to check whether it's happening.
         *
         * If either date is missing, do individual checks as well.
         */
        if ('' !== $subjectTo && '' !== $subjectFrom) {
            $subjectFromADF = new \Alaress_Date_Format($subjectFrom, \Alaress_Date_Format::DATABASE_FORMAT);
            $subjectToADF = new \Alaress_Date_Format($subjectTo, \Alaress_Date_Format::DATABASE_FORMAT);

            $this->log->debug(sprintf(
                'Checking whether current date %s is within the subject lifespan %s to %s...%s',
                $startAt->getDatabase(),
                $subjectFromADF->getDatabase(),
                $subjectToADF->getDatabase(),
                $startAt->between($subjectFromADF, $subjectToADF) ? 'it is, so we process involvements' : 'out of range; skipping'
            ));

            if (!$startAt->between($subjectFromADF, $subjectToADF)) {
                $this->log->debug(sprintf(
                    'Current date %s is outside of the subject lifespan %s to %s; skipping',
                    $startAt->getDatabase(),
                    $subjectFromADF->getDatabase(),
                    $subjectToADF->getDatabase()
                ));

                return false;
            }
        }

        /* If TO_DATE is before $startAt, it is no longer occurring and we can
         * skip over it
         */
        if ('' !== $subjectTo) {
            $subjectToADF = new \Alaress_Date_Format($subjectTo, \Alaress_Date_Format::DATABASE_FORMAT);

            if ($subjectToADF->lt($startAt)) {
                $this->log->debug(sprintf(
                    '%s %s is an expired class which went until %s; skipping',
                    $title,
                    $classCode,
                    $subjectTo
                ));

                return false;
            }
        }

        /* If FROM_DATE is after $finishAt, it's not yet occurring (it's going to start later)
         * and we can skip over it
         */
        if ('' !== $subjectFrom) {
            $subjectFromADF = new \Alaress_Date_Format($subjectFrom, \Alaress_Date_Format::DATABASE_FORMAT);

            if ($subjectFromADF->gte($finishAt)) {
                $this->log->debug(sprintf(
                    '%s %s is an upcoming class which starts on %s; skipping',
                    $title,
                    $classCode,
                    $subjectFrom
                ));

                return false;
            }
        }

        /* No reason to think their enrolment stopped
         */
        return true;
    }
}
