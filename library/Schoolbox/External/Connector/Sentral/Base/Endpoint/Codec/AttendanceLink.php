<?php

/**
 * @copyright   Copyright 2024, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Sentral\Base\Endpoint\Codec;

use Schoolbox\External\Connector\Sentral\Base\Endpoint\TimetableCalendar\Metadata\Reader;
use Schoolbox\External\Connector\IAttendanceLink;
use Schoolbox\External\Event\Model;
use Schoolbox\User;

/**
 * Builds a deeplink into sentral, via a specially crafted url
 * containing data we normally won't have kept
 */
class AttendanceLink implements IAttendanceLink
{
    /**
     * Constructor
     *
     * @param bool $attendanceEnabled
     * @param int  $externalAttendanceDays
     */
    public function __construct(private bool $attendanceEnabled, private int $externalAttendanceDays)
    {
    }


    /**
     * @param Model $event
     * @param User $user
     * @return string
     * @throws \JsonException
     */
    public function generate(
        \Schoolbox\External\Event\Model $event,
        \Schoolbox\User $user
    ): string {
        /* Get the parts you need
         */
        $classCode = $event->getCode();
        $startAtADF = $event->getStartAt();

        /* Only generate links where possible
         */
        if (!$this->isEnabled($classCode, $user, $startAtADF)) {
            return '';
        }

        // Only generate links if metadata exists
        $metadata = $event->getMetadata();

        if (!isset($metadata)) {
            return '';
        }

        return (new Reader())->getAttendanceLink($metadata);
    }


    /**
     * Decide whether a particular classcode occurring at a particular time
     * has the attendance link
     */
    private function isEnabled(string $classCode, \Schoolbox\User $user, \Alaress_Date_Format $time): bool
    {
        /* Attendance must be enabled in config
         */
        if (!$this->attendanceEnabled) {
            return false;
        }

        /* Empty classcode results in no attendance link
         */
        if ('' === $classCode) {
            return false;
        }

        /* Only staff members can use this; everyone else must get out
         */
        if (!$user->getRole()->isStaff()) {
            return false;
        }

        /* You only get daily attendance links
         *
         * This historically was only available from the Dashboard Timetable
         * but is now available everywhere in suds timetable
         */
        $now = new \Alaress_Date_Format();
        $now->endOfDay();

        $from = new \Alaress_Date_Format();
        $from->startOfDay();
        $from->addDays($this->externalAttendanceDays * -1);

        return $time->gte($from) && $time->lte($now);
    }
}
