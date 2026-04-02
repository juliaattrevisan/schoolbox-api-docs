<?php

/**
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Veracross\Base\Endpoint\Codec;

use Schoolbox\External\Connector\IAttendanceLink;
use Schoolbox\External\Connector\Veracross\Base\Endpoint\Factory;
use Schoolbox\External\Connector\Veracross\Base\Endpoint\TimetableCalendar\Metadata\Reader;
use Schoolbox\External\Event\Model;
use Schoolbox\User;

class AttendanceLink implements IAttendanceLink
{
    /**
     * Constructor
     *
     * @param bool $attendanceEnabled
     * @param int $externalAttendanceDays
     */
    public function __construct(private bool $attendanceEnabled, private int $externalAttendanceDays)
    {
    }


    /**
     * @param Model $event
     * @param User $user
     * @return string
     * @throws \InvalidArgumentException
     * @throws \JsonException
     */
    public function generate(
        \Schoolbox\External\Event\Model $event,
        \Schoolbox\User $user
    ): string {
        if ($event->getSource() !== Factory::SOURCE_NAME) {
            return '';
        }

        /* We needed this for an identifier obtained during timetable import
         */
        $metadata = $event->getMetadata();

        if (!isset($metadata)) {
            return '';
        }

        /* Only generate links where possible
         */
        if (!$this->isEnabled($event->getCode(), $user, $event->getStartAt())) {
            return '';
        }

        $url = (string)(new Reader())->getAttendanceLink($event->getMetadata());

        if ($url !== '' && $user->hasEmail()) {
            $separator = str_contains($url, '?') ? '&' : '?';
            $url .= $separator . http_build_query(['login_hint' => $user->getEmail()]);
        }

        return $url;
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

        /* Empty classcode results in no attendance in tass
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
