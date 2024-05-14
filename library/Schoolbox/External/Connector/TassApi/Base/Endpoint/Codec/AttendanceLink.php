<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\TassApi\Base\Endpoint\Codec;

use Schoolbox\External\Connector\IAttendanceLink;
use Schoolbox\External\Event\Model;
use Schoolbox\User;

/**
 * Builds a deeplink into tass, via a specially crafted url
 * containing data we normally won't have kept
 *
 * new AttendanceLink(
 *     (bool)$config->get('external_attendance'),
 *     (int)$config->get('external_attendance_days'),
 *     (string)$config->get('external_tass_attendance_show_prod_menu')
 * );
 */
class AttendanceLink implements IAttendanceLink
{
    /**
     * @var bool
     */
    private bool $attendanceEnabled;


    /**
     * @var int
     */
    private int $externalAttendanceDays;


    /**
     * @var string
     */
    private string $prodMenu;


    /**
     * @var Code
     */
    private Code $codeCodec;


    /**
     * @var Guid
     */
    private Guid $guidCodec;


    /**
     * Constructor
     *
     * @param bool $attendanceEnabled
     * @param int  $externalAttendanceDays
     * @param bool $prodMenu
     */
    public function __construct(bool $attendanceEnabled, int $externalAttendanceDays, bool $prodMenu)
    {
        $this->attendanceEnabled = $attendanceEnabled;
        $this->externalAttendanceDays = $externalAttendanceDays;
        $this->prodMenu = $prodMenu ? 'Y' : 'N';

        /* Codecs used to get pieces of data for this deeplink
         */
        $this->codeCodec = new Code();
        $this->guidCodec = new Guid();
    }


    /**
     * @param Model $event
     * @param User  $user
     * @return string
     * @throws \InvalidArgumentException
     */
    public function generate(
        \Schoolbox\External\Event\Model $event,
        \Schoolbox\User $user
    ): string {
        // Only generate links for TASS-created events
        if ($event->getSource() !== \Schoolbox\External\Connector\TassApi\Base\Endpoint\Factory::SOURCE_NAME) {
            return '';
        }

        /* Get the parts you need
         */
        [$startAt, $ttid, $day, $periodCode, $classCode] = $this->guidCodec->decode($event->getGuid());

        /* No date was found; guid might be empty for this tass record or
         * otherwise incorrectly encoded. Just silently not provide anything
         */
        if ('' === $startAt) {
            return '';
        }

        $startAtADF = new \Alaress_Date_Format($startAt, \Alaress_Date_Format::DATABASE_FORMAT);

        /* Only generate links where possible
         */
        if (!$this->isEnabled($classCode, $user, $startAtADF)) {
            return '';
        }

        [$year, $subject, $class] = $this->codeCodec->decode($classCode);

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
            'date'        => $startAtADF->getUsingFormat('d/m/Y'),
            'periodcode'  => $periodCode,
            'daycode'     => $day,
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
