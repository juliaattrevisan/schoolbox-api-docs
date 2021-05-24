<?php

/**
 *
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

use Schoolbox\GenericCollection;
use Schoolbox\IConfigLoader;

class External_Tass_AttendanceLink implements External_AttendanceLinkInterface
{
    /**
     * @var int
     */
    private $externalAttendanceDays;

    /**
     * @var bool
     */
    private $isStaff;
    private $prodMenu;

    /**
     * @inheritDoc
     */
    public function getAttendanceLink(string $classCode, ?string $campus, ?string $fileType, array $attendancePeriods, ?int $currentPeriod, Alaress_Date_Format $time, GenericCollection $campusList, int $campusId, ?string $externalId, int $dayNumber): string
    {
        [$year, $subject, $class] = explode('-', $classCode);
        [$cmpCode, $timetableCode] = explode('-', $campusList->getBy('id', $campusId)->getExtTimetableCode());

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
            'ttid'        => $timetableCode,
            'yeargroups'  => $year,
            'classes'     => $class,
            //                    'tchcode'     => $this->getUser()->getExternalId(),
            'tchcode'     => $externalId,
            'subjectcode' => $subject,
            'date'        => $time->getUsingFormat('d/m/Y'),
            'periodcode'  => $currentPeriod,
            'daycode'     => $dayNumber,
        ];

        $deepLink = urlencode(http_build_query($deepLinkParams));
//      return "{$this->router->generate('tassportal.teacherKiosk')}?deep_link={$deepLink}&prod_menu={$this->prodMenu}";
        return "/modules/tassportal/teacherkiosk?deep_link={$deepLink}&prod_menu={$this->prodMenu}";
    }

    /**
     * @inheritDoc
     */
    public function getAttendanceLinkTarget(): string
    {
        return '_blank';
    }

    /**
     * @inheritDoc
     */
    public function init(IConfigLoader $config, bool $isStaff): void
    {
        // load configuration
        $this->externalAttendanceDays = $config->get('external_attendance_days');
        $this->prodMenu               = $config->get('external_tass_attendance_show_prod_menu') ? 'Y' : 'N';
        $this->isStaff                = $isStaff;
    }

    /**
     * @inheritDoc
     */
    public function isEnabled(string $classCode, ?bool $attendanceEnabled, Alaress_Date_Format $time): bool
    {
        if (!$this->isStaff || ('' === $classCode)) {
            return false;
        }

        $now = new Alaress_Date_Format();
        $now->endOfDay();

        $from = new Alaress_Date_Format();
        $from->startOfDay();
        $from->addDays($this->externalAttendanceDays * -1);

        return $time->gte($from) && $time->lte($now);
    }
}
