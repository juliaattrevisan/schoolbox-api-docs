<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\TassApi\Base\Endpoint\Codec;

use Schoolbox\User;

/**
 * Builds a deeplink into tass, via a specially crafted url
 * containing data we normally won't have kept
 *
 * new AttendanceLink(
 *     (bool)$config->get('external_attendance'),
 *     (string)$config->get('external_attendance_days'),
 *     (string)$config->get('external_tass_attendance_show_prod_menu')
 * );
 */
class AttendanceLink
{
    /**
     * @var bool
     */
    private bool $attendanceEnabled;


    /**
     * @var string
     */
    private string $externalAttendanceDays;


    /**
     * @var string
     */
    private string $prodMenu;


    /**
     * @var Code
     */
    private Code $codec;


    /**
     * Constructor
     *
     * @param bool   $attendanceEnabled
     * @param string $externalAttendanceDays
     * @param string $prodMenu
     */
    public function __construct(bool $attendanceEnabled, string $externalAttendanceDays, string $prodMenu)
    {
        $this->attendanceEnabled = $attendanceEnabled;
        $this->externalAttendanceDays = $externalAttendanceDays;
        $this->prodMenu = $prodMenu ? 'Y' : 'N';
        $this->codec = new Code();
    }


    /**
     * @param string               $ttid
     * @param int                  $dayNumber
     * @param int                  $periodCode
     * @param string               $startAt
     * @param string               $classCode
     * @param User                 $user
     * @param \Alaress_Date_Format $time
     * @return string
     */
    public function generate(
        string $ttid,
        int $dayNumber,
        int $periodCode,
        string $startAt,
        string $classCode,
        \Schoolbox\User $user,
        \Alaress_Date_Format $time
    ): string {
        /* Only generate links where possible
         */
        if (!$this->isEnabled($classCode, $user, $time)) {
            return '';
        }

        [$year, $subject, $class] = $this->codec->decode($classCode);

        /* Example given by BTAC = [
                "target":      "teacher.rollcall",
                "ttid":        "23",
                "yeargroups":  "10",
                "classes":     "1",
                "tchcode":     "VPE",
                "subjectcode": "1111",
                "date":        "01/05/2018",
                "periodcode":  "3",
                "daycode":     "7"
            ]
         */
        $deepLinkParams = [
            'target'      => 'teacher.rollcall',
            'ttid'        => $ttid,
            'yeargroups'  => $year,
            'classes'     => $class,
            'tchcode'     => $user->getExternalId(),
            'subjectcode' => $subject,
            'date'        => $startAt,
            'periodcode'  => $periodCode,
            'daycode'     => $dayNumber,
        ];

        $deepLink = urlencode(http_build_query($deepLinkParams));

        return "/modules/tassportal/teacherkiosk?deep_link={$deepLink}&prod_menu={$this->prodMenu}";
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
