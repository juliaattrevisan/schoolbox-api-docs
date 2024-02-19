<?php

/**
 * @copyright   Copyright 2024, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Civica\Base\Endpoint\TimetableCalendar;

class Metadata
{
    /**
     * Constructor
     */
    public function __construct()
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
     * @return null|string
     */
    private function buildAttendanceLink(array $class, array $classInstance): ?string
    {
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
        $prefix = 'https://ces-n.civicaed.com.au/TeacherToolkit/attendance'; // FIXME is this hardcoded or dynamic?
        $teachingGroupId = $class['teachingGroupId'];                        // FIXME What's this? Group Id, maybe
        $date = $classInstance['date'];

        /* These aren't in the api specs; hardcoding for now
         * No idea what they even mean.
         *
         * FIXME Find where these were SUPPOSED to come from;
         *  I made various best-guesses here
         */
        $hasAttendance = 'true';                // FIXME What's this?
        $type = 0;                              // FIXME What's this? Group Type, maybe - but where does it come from
        $session = $classInstance['sessionId']; // FIXME Made an assumption here, but what is Session Number?
        $singleSession = 'false';               // FIXME What's this?
        $schoolContextId = $class['schoolId'];  // FIXME Made an assumption here

        return sprintf(
            '%s/%s?attendance=%s&type=%s&session=%s&date=%s&singlesession=%s&schoolContextId=%s',
            $prefix,
            $teachingGroupId,
            $hasAttendance,
            $type,
            $session,
            $date,
            $singleSession,
            $schoolContextId
        );
    }


    /**
     * Extract the attendance url from the metdata
     *
     * @param array|string $metadata
     * @return string|null
     * @throws \JsonException
     */
    public function getAttendanceLink(array|string $metadata): ?string
    {
        /* Accept both the json-encoded metadata and string formats
         * for interchangeability. You could be working with either the
         * mapper-decoded version or the db-encoded versions...
         */
        $deserialized = $metadata;

        if (is_string($metadata)) {
            $deserialized = json_decode($deserialized, true, 512, JSON_THROW_ON_ERROR);
        }

        return $deserialized['links']['attendance'] ?? null;
    }
}
