<?php
/**
 * Awards component for St Michaels.
 *
 * Don't try this for anyone other than St Michaels - it will fail miserably.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Component_Dashboard_StMichaelsAwardsController extends Component_Dashboard_ControllerAbstract {

    /**
     * @var External_UserDetentionInterface
     */
    protected $externalDb;

    /**
     * Detentions for the current user.
     *
     * @var array
     */
    protected $detentionList;

    /**
     * Absences for the current user.
     *
     * @var array
     */
    protected $absenceList;

    /**
     * Late arrivals for the current user.
     *
     * @var array
     */
    protected $lateArrivalList;

    /**
     * Awards for the current user.
     *
     * @var array
     */
    protected $awardList;

    /**
     * Prepares data for the render function. Business logic.
     *
     * @return bool
     */
    public function execute() {

        // Not St Michaels? This isn't for you
        if (!(Config::get('site_location') == 'stmichaels')) {
            return false;
        }

        $userId = $this->user->getExternalId();

//        // Here's some test data, because this only works on St Michaels servers
//        $this->detentionList = array(
//            array(
//                'ID'                   => $userId,
//                'DetentionDate'        => date('Y-m-d'),
//                'DetentionDescription' => 'Noobed out',
//                'DetentionComment'     => 'You are a noob',
//            ),
//            array(
//                'ID'                   => $userId,
//                'DetentionDate'        => date('Y-m-d'),
//                'DetentionDescription' => 'Fell asleep at desk',
//                'DetentionComment'     => "* Fell asleep at desk\nAgain",
//            ),
//        );
//        $this->absenceList = array(
//            array(
//                'ID'                  => $userId,
//                'EventDateTime'       => date('Y-m-d H:i:s'),
//                'AbsenceTypeCode'     => 'LT',
//                'AbsenceDescription'  => 'Late',
//                'ClassCode'           => 'ENC17',
//                'ClassCampus'         => 'SEN',
//                'ClassDescription'    => 'English Unit 1',
//                'AttendanceDayNumber' => '1',
//                'AttendancePeriod'    => '1',
//                'LateArrivalFlag'     => true,
//                'LateArrivalTime'     => date('H:i:s'),
//            ),
//            array(
//                'ID'                  => $userId,
//                'EventDateTime'       => date('Y-m-d H:i:s'),
//                'AbsenceTypeCode'     => 'ABS',
//                'AbsenceDescription'  => 'Absent',
//                'ClassCode'           => 'ENC17',
//                'ClassCampus'         => 'SEN',
//                'ClassDescription'    => 'English Unit 1',
//                'AttendanceDayNumber' => '1',
//                'AttendancePeriod'    => '1',
//                'LateArrivalFlag'     => false,
//                'LateArrivalTime'     => '',
//            ),
//        );
//        $this->awardList = array(
//            array(
//                'ID'               => $userId,
//                'FileYear'         => $_SESSION['FileYear'],
//                'FileSemester'     => $_SESSION['FileSemester'],
//                'AwardCode'        => 'EIA',
//                'AwardDescription' => 'Everything is awesome',
//                'AwardDate'        => date('Y-m-d'),
//                'AwardAchievement' => 'was awesome',
//                'AwardComment'     => 'You are awesome',
//            ),
//            array(
//                'ID'               => $userId,
//                'FileYear'         => $_SESSION['FileYear'],
//                'FileSemester'     => $_SESSION['FileSemester'],
//                'AwardCode'        => 'PRO',
//                'AwardDescription' => 'You are a pro',
//                'AwardDate'        => date('Y-m-d'),
//                'AwardAchievement' => 'graduated from noob to pro',
//                'AwardComment'     => 'Stopped sucking and started being awesome',
//            ),
//        );

        // Live data
        $this->externalDb    = new External_UserDetention();
        $this->detentionList = $this->externalDb->getDetentions($userId, new DateTime('now'));
        $this->absenceList   = $this->externalDb->getAbsences($userId);
        $this->awardList     = $this->externalDb->getAwards(
            $userId,
            $_SESSION['FileYear'],
            $_SESSION['FileSemester']
        );

        // Filter award list into absences and lateness
        $absenceList = $this->absenceList;
        $lateFilter = function($absence) {
            return ($absence['LateArrivalFlag']);
        };
        $absenceFilter = function($absence) {
            return !($absence['LateArrivalFlag']);
        };
        $this->lateArrivalList = array_filter($absenceList, $lateFilter);
        $this->absenceList     = array_filter($absenceList, $absenceFilter);
        return true;
    }

    /**
     * Parse <!-- blockage --> and set {VARIABLES}
     *
     * @param  String $templateHandle
     * @return boolean
     */
    public function renderTemplate($templateHandle) {
        if (!parent::loadTemplate($templateHandle, 'stMichaelsAwards')) {
            return false;
        }

        if (empty($this->detentionList)
            && empty($this->awardList)
            && empty($this->absenceList)
        ) {
            return false;
        }

        $this->parseConfig();

        // <!-- BEGIN blockages -->
        $this->setBlock($templateHandle,   'awardList',       'awardList_block');
        $this->setBlock('awardList',       'award',           'award_block');
        $this->setBlock($templateHandle,   'detentionList',   'detentionList_block');
        $this->setBlock('detentionList',   'detention',       'detention_block');
        $this->setBlock('detention',       'detentionLine',   'detentionLine_block');
        $this->setBlock($templateHandle,   'absenceList',     'absenceList_block');
        $this->setBlock('absenceList',     'absence',         'absence_block');
        $this->setBlock($templateHandle,   'lateArrivalList', 'lateArrivalList_block');
        $this->setBlock('lateArrivalList', 'lateArrival',     'lateArrival_block');
        // <!-- END blockages -->

        // {VARIABLES}
        $this->setVar(
            array(
                'awardList_block'       => '',
                'detentionList_block'   => '',
                'absenceList_block'     => '',
                'lateArrivalList_block' => '',
            )
        );

        // Awards
        if (!empty($this->awardList)) {
            $this->parse('awardList_block', 'awardList');
            $this->setVar('award_block', '');
            foreach ($this->awardList as $award) {
                $date   = new Alaress_Date_Format(new DateTime($award['AwardDate']));
                $params = array(
                    'AWARD_CODE'        => $award['AwardCode'],
                    'AWARD_DESCRIPTION' => $award['AwardDescription'],
                    'AWARD_DATE'        => $award['AwardDate'],
                    'AWARD_DATE_LABEL'  => $date->getLongDateOnly(),
                    'AWARD_ACHIEVEMENT' => $award['AwardAchievement'],
                    'AWARD_COMMENT'     => $award['AwardComment'],
                );
                // Scrub. Assume comments may have line breaks
                $params = array_map(array('Scrub', 'text'), $params);
                $params['AWARD_COMMENT'] = nl2br($params['AWARD_COMMENT']);
                $this->setVar($params);
                $this->parse('award_block', 'award', true);
            }
        }

        // Detentions
        if (!empty($this->detentionList)) {
            $this->parse('detentionList_block', 'detentionList');
            $this->setVar('detention_block', '');
            foreach ($this->detentionList as $detention) {
                $date   = new Alaress_Date_Format(new DateTime($detention['DetentionDate']));
                $params = array(
                    'DETENTION_CODE'        => $detention['DetentionCode'],
                    'DETENTION_DESCRIPTION' => $detention['DetentionDescription'],
                    'DETENTION_DATE'        => $detention['DetentionDate'],
                    'DETENTION_DATE_LABEL'  => $date->getLongDateOnly(),
                    'DETENTION_COMMENT'     => $detention['DetentionComment'],
                    'detentionLine_block'   => '',
                );
                // Scrub.
                // Assume comments may have line breaks, put each one in a UL.
                // They also seem to add * characters in front of each line to
                // denote lists, remove that.
                $params = array_map(array('Scrub', 'text'), $params);
                $this->setVar($params);
                foreach (preg_split('/[\\n\\r]+/', $params['DETENTION_COMMENT']) as $line) {
                    $line = trim(preg_replace('/^\\W+/', '', $line));
                    if (strlen($line) > 0) {
                        $this->setVar('DETENTION_COMMENT_LINE', Scrub::text($line));
                        $this->parse('detentionLine_block', 'detentionLine', true);
                    }
                }
                $this->parse('detention_block', 'detention', true);
            }
        }

        // Absences
        if (!empty($this->absenceList)) {
            $count = count($this->absenceList);
            $this->parse('absenceList_block', 'absenceList');
            $this->setVar(
                array(
                    'absence_block'  => '',
                    'ABSENCE_COUNT'  => $count,
                    'ABSENCE_HEADER' => ($count > 1 ? 'Unexplained Absences' : 'Unexplained Absence'),
                )
            );
            foreach ($this->absenceList as $absence) {
                $eventDate = new Alaress_Date_Format(new DateTime($absence['EventDateTime']));
                $params    = array(
                    'EVENT_DATETIME'        => $absence['EventDateTime'],
                    'EVENT_DATETIME_LABEL'  => $eventDate->getLong(),
                    'ABSENCE_TYPE_CODE'     => $absence['AbsenceTypeCode'],
                    'ABSENCE_DESCRIPTION'   => $absence['AbsenceDescription'],
                    'CLASS_CODE'            => $absence['ClassCode'],
                    'CLASS_CAMPUS'          => $absence['ClassCampus'],
                    'CLASS_DESCRIPTION'     => $absence['ClassDescription'],
                    'ATTENDANCE_DAY_NUMBER' => $absence['AttendanceDayNumber'],
                    'ATTENDANCE_PERIOD'     => $absence['AttendancePeriod'],
                );
                $params = array_map(array('Scrub', 'text'), $params);
                $this->setVar($params);
                $this->parse('absence_block', 'absence', true);
            }
        }

        // Lateness
        if (!empty($this->lateArrivalList)) {
            $count = count($this->lateArrivalList);
            $this->parse('lateArrivalList_block', 'lateArrivalList');
            $this->setVar(
                array(
                    'lateArrival_block'   => '',
                    'LATE_ARRIVAL_COUNT'  => $count,
                    'LATE_ARRIVAL_HEADER' => 'Late to Class',
                )
            );
            foreach ($this->lateArrivalList as $lateArrival) {
                $eventDate = new Alaress_Date_Format(new DateTime($lateArrival['EventDateTime']));
                $lateTime  = new Alaress_Date_Format(new DateTime($lateArrival['LateArrivalTime']));
                $params    = array(
                    'EVENT_DATETIME'          => $lateArrival['EventDateTime'],
                    'EVENT_DATETIME_LABEL'    => $eventDate->getLong(),
                    'ABSENCE_TYPE_CODE'       => $lateArrival['AbsenceTypeCode'],
                    'ABSENCE_DESCRIPTION'     => $lateArrival['AbsenceDescription'],
                    'CLASS_CODE'              => $lateArrival['ClassCode'],
                    'CLASS_CAMPUS'            => $lateArrival['ClassCampus'],
                    'CLASS_DESCRIPTION'       => $lateArrival['ClassDescription'],
                    'ATTENDANCE_DAY_NUMBER'   => $lateArrival['AttendanceDayNumber'],
                    'ATTENDANCE_PERIOD'       => $lateArrival['AttendancePeriod'],
                    'LATE_ARRIVAL_FLAG'       => $lateArrival['LateArrivalFlag'],
                    'LATE_ARRIVAL_TIME'       => $lateArrival['LateArrivalTime'],
                    'LATE_ARRIVAL_TIME_LABEL' => $lateTime->getTimeOnly(),
                );
                $params = array_map(array('Scrub', 'text'), $params);
                $this->setVar($params);
                $this->parse('lateArrival_block', 'lateArrival', true);
            }
        }

        return true;
    }
}
