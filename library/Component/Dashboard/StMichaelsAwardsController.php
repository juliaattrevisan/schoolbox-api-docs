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

        $userId = $this->user->getId();
        $this->externalDb    = new External_UserDetention();
        $this->detentionList = $this->externalDb->getDetentions($userId, new DateTime('now'));
        $this->absenceList   = $this->externalDb->getAbsences($userId);
        $this->awardList     = $this->externalDb->getAwards(
            $userId,
            $_SESSION['FileYear'],
            $_SESSION['FileSemester']
        );
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

//        if (empty($this->detentionList)
//            || empty($this->awardList)
//            || empty($this->absenceList)
//        ) {
//            return false;
//        }

        $this->parseConfig();

        // <!-- BEGIN blockages -->
        $this->setBlock($templateHandle, 'awardList',     'awardList_block');
        $this->setBlock('awardList',     'award',         'award_block');
        $this->setBlock($templateHandle, 'detentionList', 'detentionList_block');
        $this->setBlock('detentionList', 'detention',     'detention_block');
        $this->setBlock($templateHandle, 'absenceList',   'absenceList_block');
        $this->setBlock('absenceList',   'absence',       'absence_block');
        // <!-- END blockages -->

        // {VARIABLES}
        $this->setVar(
            array(
                'awardList_block'     => '',
                'detentionList_block' => '',
                'absenceList_block'   => '',
            )
        );

        // Awards
        if (!empty($this->awardList)) {
            $this->parse('awardList_block', 'awardList');
            $this->setVar('award_block', '');
            foreach ($this->awardList as $award) {
                $date = new Alaress_Date_Format(new DateTime($award['AwardDate']));
                $this->setVar(
                    array(
                        'AWARD_CODE'        => $award['AwardCode'],
                        'AWARD_DESCRIPTION' => $award['AwardDescription'],
                        'AWARD_DATE'        => $award['AwardDate'],
                        'AWARD_DATE_LABEL'  => $date->getLongDateOnly(),
                        'AWARD_ACHIEVEMENT' => $award['AwardAchievement'],
                        'AWARD_COMMENT'     => $award['AwardComment'],
                    )
                );
                $this->parse('award_block', 'award', true);
            }
        }

        // Detentions
        if (!empty($this->detentionList)) {
            $this->parse('detentionList_block', 'detentionList');
            $this->setVar('detention_block', '');
            foreach ($this->detentionList as $detention) {
                $date = new Alaress_Date_Format(new DateTime($detention['DetentionDate']));
                $this->setVar(
                    array(
                        'DETENTION_CODE'        => $detention['DetentionCode'],
                        'DETENTION_DESCRIPTION' => $detention['DetentionDescription'],
                        'DETENTION_DATE'        => $detention['DetentionDate'],
                        'DETENTION_DATE_LABEL'  => $date->getLongDateOnly(),
                        'DETENTION_COMMENT'     => $detention['DetentionComment'],
                    )
                );
                $this->parse('detention_block', 'detention', true);
            }
        }

        // Absences
        if (!empty($this->absenceList)) {
            $this->parse('absenceList_block', 'absenceList');
            $this->setVar('absence_block', '');
            foreach ($this->absenceList as $absence) {
                $eventDate = new Alaress_Date_Format(new DateTime($absence['EventDateTime']));
                $lateTime  = new Alaress_Date_Format(new DateTime($absence['LateArrivalTime']));
                $this->setVar(
                    array(
                        'EVENT_DATETIME'          => $absence['EventDateTime'],
                        'EVENT_DATETIME_LABEL'    => $eventDate->getLong(),
                        'ABSENCE_TYPE_CODE'       => $absence['AbsenceTypeCode'],
                        'ABSENCE_DESCRIPTION'     => $absence['AbsenceDescription'],
                        'CLASS_CODE'              => $absence['ClassCode'],
                        'CLASS_CAMPUS'            => $absence['ClassCampus'],
                        'CLASS_DESCRIPTION'       => $absence['ClassDescription'],
                        'ATTENDANCE_DAY_NUMBER'   => $absence['AttendanceDayNumber'],
                        'ATTENDANCE_PERIOD'       => $absence['AttendancePeriod'],
                        'LATE_ARRIVAL_FLAG'       => $absence['LateArrivalFlag'],
                        'LATE_ARRIVAL_TIME'       => $absence['LateArrivalTime'],
                        'LATE_ARRIVAL_TIME_LABEL' => $lateTime->getTimeOnly(),
                    )
                );
                $this->parse('absence_block', 'absence', true);
            }
        }

        return true;
    }

}
