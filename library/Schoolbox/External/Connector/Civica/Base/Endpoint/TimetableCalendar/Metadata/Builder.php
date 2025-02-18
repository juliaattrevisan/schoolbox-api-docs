<?php

/**
 * @copyright   Copyright 2024, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Civica\Base\Endpoint\TimetableCalendar\Metadata;

class Builder
{
    /**
     * Apparently this is hardcoded too - we only use
     * TeachingGroup (1)
     *
     * Other variations are:
     *      0 RollGroup
     *      1 TeachingGroup
     *      2 EventGroup
     *      3 DetentionGroup
     *      4 SchoolGroup
     */
    private const GROUP_TYPE = 1;


    /**
     * Constructor
     */
    public function __construct(private string $externalStaffPortal)
    {
    }


    /**
     * Builds event-specific metadata such as attendance links etc.
     *
     * @param array $class
     * @param array $classInstance
     * @return array
     */
    public function build(array $class, array $classInstance): array
    {
        /* Each event contains this additional metadata struct,
         * for extra info that you might need later
         */
        return [
            'links' => [
                'attendance' => $this->buildAttendanceLink($class, $classInstance)
            ]
        ];
    }


    /**
     * Precompute a deeplinking url into Civica using the ingredients provided
     *
     * @param array $class
     * @param array $classInstance
     * @return string
     */
    private function buildAttendanceLink(array $class, array $classInstance): string
    {
        /* We only care about class instances whose isTeachingSession is true;
         * returns empty string otherwise.
         */
        if (!$classInstance['isTeachingSession']) {
            return '';
        }

        /* FIXME Is attendance link mandatory for every class, or just some?
         *  Find optionality
         *
         * $attendanceLink = null;
         * ...
         * return $attendanceLink;
         */

        /* Attendance Deeplinks were supposed to be in this format, but you don't have everything
         * https://ces-n.civicaed.com.au/TeacherToolkit/attendance/3853918461142000008?attendance=true&type=0&session=3&date=2023-09-28&singlesession=false&schoolContextId=3826345270793000199
         *
         * FIXME Find the missing parts
         */
        $teachingGroupId = $class['teachingGroupId'];      // FIXME What's this? Group Id, maybe
        $date = $classInstance['date'];

        /* These aren't in the api specs; hardcoding for now
         * No idea what they even mean.
         *
         * FIXME Find where these were SUPPOSED to come from;
         *  I made various best-guesses here
         */
        $hasAttendance = 'true';                // FIXME What's this?
        $type = self::GROUP_TYPE;               // FIXME What's this? Group Type, maybe - but where does it come from
        $session = $classInstance['sessionId']; // FIXME Made an assumption here, but what is Session Number?
        $singleSession = 'false';               // FIXME What's this?
        $schoolContextId = $class['schoolId'];  // FIXME Made an assumption here

        /* This is a secret thing that loads actual students; without it, you get nobody
         */
        $fromIframe = 'true';

        return sprintf(
            '%s/TeacherToolkit/attendance/%s?attendance=%s&type=%s&session=%s&date=%s&singlesession=%s&schoolContextId=%s&fromIframe=%s',
            $this->externalStaffPortal,
            $teachingGroupId,
            $hasAttendance,
            $type,
            $session,
            $date,
            $singleSession,
            $schoolContextId,
            $fromIframe
        );
    }
}
