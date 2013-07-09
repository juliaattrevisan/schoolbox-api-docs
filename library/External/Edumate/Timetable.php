<?php
/**
 * Implementation of Edumate Timetable connector
 * For schema information, see http://wiki.schoolbox.com.au/index.php/Interface_Calls
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2013, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class External_Edumate_Timetable extends External_Edumate_Connection implements External_TimetableInterface {

    protected $offset = 0;

    /**
     * Ideally, these two arrays would be replaced with a View to retrieve dynamically, rather than hardcoded
     * @var array
     */
    protected $labelMap = array(
        'BS' => array('Before School', 1),
        'MG' => array('Morning Activities', 1),
        '1'  => array('Period 1', 1),
        '2'  => array('Period 2', 1),
        'R'  => array('Recess', 2),
        '3'  => array('Period 3', 1),
        '4'  => array('Period 4', 1),
        '5'  => array('Period 5', 1),
        'L1' => array('Lunch 1', 2),
        'L2' => array('Lunch 2', 3),
        '6'  => array('Period 6', 1),
        '7'  => array('Period 7', 1),
        'AS' => array('After School', 2)
    );

    protected $timeMap = array(
        'BS' => array('08:00', '08:40'),
        'MG' => array('08:40', '09:00'),
        '1'  => array('09:00', '09:45'),
        '2'  => array('09:45', '10:30'),
        'R'  => array('10:30', '10:50'),
        '3'  => array('10:50', '11:35'),
        '4'  => array('11:35', '12:20'),
        '5'  => array('12:20', '13:05'),
        'L1' => array('13:05', '13:20'),
        'L2' => array('13:22', '13:40'),
        '6'  => array('13:40', '14:25'),
        '7'  => array('14:25', '15:10'),
        'AS' => array('15:10', '15:55'),
    );

    protected $cycleLength = 5;

    /**
     * Returns the timetable definition for a particular campus *FOR A PARTICULAR DAY*
     *
     * @param $campusId
     * @param int $day
     * @return array
     */
    public function getTimetableDefinition($campusId, $day = 0) {

        $definition = array();
        $periodCount = 0;

        $i = 0;
        foreach($this->labelMap as $shortName => $periodDetails) {
            $definition['desc'][] = $periodDetails[0];
            $definition['start'][] = $this->parseTime($this->timeMap[$shortName][0]);
            $definition['end'][] = $this->parseTime($this->timeMap[$shortName][1]);
            $definition['number'][] = $shortName;
            $definition['sequence'][] = $periodDetails[1];
            ++$periodCount;
        }

        if($periodCount == 0) {
            return false;
        }

        $definition['periodCount'] = $periodCount;

        $definition['dayCount'] = $this->getCycleLength();

        return $definition;

    }

    protected function parseTime($time) {
        return new DateTime($time, new DateTimeZone('UTC'));
    }

    /**
     * Helper function to retrieve the length of the timetable cycle in edumate
     * @return int
     */
    private function getCycleLength() {
        return $this->cycleLength;
    }

    public function getDayNumber($campusId, $date = 0) {

        if(!$date) {
            $date = time();
        }

        // if it's the weekend
        if(in_array($day_number = date('w', $date), array(0, 6))) {
            return false;
        }
        return $day_number;
    }

    /**
     * Calculates the current period based on the current time
     *
     * @param $campusId
     * @return int
     */
    public function getPeriodNumber($campusId) {
        $now = new DateTime('now', new DateTimeZone('UTC'));
        $now = $this->parseTime($now->format('Hi'));

        $defn = $this->getTimetableDefinition($campusId);
        $sequence = 0;

        for($i = 0; $i < count($defn['sequence']); ++$i) {
            if($now < $defn['end'][$i]) {
                $sequence = $defn['sequence'][$i];
                break;
            }
        }
        return $sequence;
    }

    /**
     * Return a user's timetable for a specific day
     *
     * @param $externalUserId
     * @param $userId
     * @param $campusId int ignored
     * @param $dayNumber
     * @return array|bool
     */
    public function getDayTimetable($externalUserId, $userId, $campusId, $dayNumber) {

        if(!($definition = $this->getTimetableDefinition($campusId, $dayNumber))) {
            return false;
        }

        if($this->getGroupHandler()->isStudent($userId)) {
            $type = 'student';
            $join = "
                LEFT JOIN db2inst1.schoolbox_studentclass c ON t.class_code = c.class_code
                WHERE c.student_number = :sn
            ";
        }
        elseif($this->getGroupHandler()->isStaff($userId)) {
            $type = 'staff';
            $join = "
                LEFT JOIN db2inst1.schoolbox_teacherclass c ON t.class_code = c.class_code
                WHERE c.staff_number = :sn
            ";
        }
        else {
            return false;
        }

        $stmt = $this->getDatabase()->prepare("
            SELECT period AS description, t.class_code AS code, c.{$type}_number AS staffID, room, cl.class
            FROM db2inst1.schoolbox_timetable t
            LEFT JOIN db2inst1.schoolbox_classes cl ON t.class_code = cl.class_code
            $join
            AND day = :d
        ");

        $stmt->execute(array(
            'sn' => $externalUserId,
            'd' => $dayNumber
        ));

        $classes = array();
        while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            // index on description
            $classes[$row['DESCRIPTION']] = $row;
        }

        // arrange data as $timetable[$sequence] = array('period[Name|Start|End|Number|Sequence]');
        $timetable = array();
        for($i = 0; isset($definition['sequence'][$i]); ++$i) {

            // the 'shortName' of the period
            $code = $definition['number'][$i];

            $timetable[$i] = array(
                'periodName'     => $definition['desc'][$i],
                'periodStart'    => $definition['start'][$i]->format('h:ia'),
                'periodEnd'      => $definition['end'][$i]->format('h:ia'),
                'periodNumber'   => $definition['number'][$i],
                'periodSequence' => $definition['sequence'][$i]
            );

            // if no class on this period, skip it
            if(isset($classes[$code])) {
                $timetable[$i]['description'] = $classes[$code]['CLASS'];
                $timetable[$i]['code'] = $classes[$code]['CODE'];
                $timetable[$i]['room'] = $classes[$code]['ROOM'];
                $timetable[$i]['staffID'] = $classes[$code]['STAFFID'];
            }

        }

        return $timetable;

    }

    /**
     * Return the ENTIRE timetable for a user
     *
     * @param $externalUserId
     * @param $userId
     * @param $campusId
     * @return array|bool
     */
    public function getFullTimetable($externalUserId, $userId, $campusId) {
        $definition = $this->getTimetableDefinition($campusId);
        $dayCount = is_numeric($definition['dayCount']) ? $definition['dayCount'] : 0;

        if(!$dayCount) {
            return false;
        }

        if($this->getGroupHandler()->isStudent($userId)) {
            $type = 'student';
            $join = "
                LEFT JOIN db2inst1.schoolbox_studentclass c ON t.class_code = c.class_code
                WHERE c.student_number = :sn
            ";
        }
        elseif($this->getGroupHandler()->isStaff($userId)) {
            $type = 'staff';
            $join = "
                WHERE t.staff_number = :sn
            ";
        }
        else {
            return false;
        }

        $stmt = $this->getDatabase()->prepare("
            SELECT period AS description, day, t.class_code AS code, c.{$type}_number AS staffID, room, cl.class
            FROM db2inst1.schoolbox_timetable t
            LEFT JOIN db2inst1.schoolbox_classes cl ON t.class_code = cl.class_code
            $join
        ");

        $stmt->execute(array(
            'sn' => $externalUserId,
        ));

        // compile the returned data
        $classes = array();
        while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {

            $classes[$row['DESCRIPTION']][$row['DAY']] = $row;
        }

        $timetable = array();
        for($i = 0; isset($definition['sequence'][$i]); ++$i) {

            // the 'shortName' of the period
            $code = $definition['number'][$i];

            $timetable[$i] = array(
                'periodName'     => $definition['desc'][$i],
                'periodStart'    => $definition['start'][$i]->format('h:ia'),
                'periodEnd'      => $definition['end'][$i]->format('h:ia'),
                'periodNumber'   => $definition['number'][$i],
                'periodSequence' => $definition['sequence'][$i]
            );

            for($j = 1; $j <= $dayCount; ++$j) {
                // period has class for user
                if(isset($classes[$code][$j])) {
                    $timetable[$i]['day'][$j]['periodOn'] = 1;
                    $timetable[$i]['day'][$j]['description'] = $classes[$code][$j]['CLASS'];
                    $timetable[$i]['day'][$j]['code'] = $classes[$code][$j]['CODE'];
                    $timetable[$i]['day'][$j]['room'] = $classes[$code][$j]['ROOM'];
                    $timetable[$i]['day'][$j]['staffID'] = $classes[$code][$j]['STAFFID'];
                }
                // user has not class in this period
                else {
                    $timetable[$i]['day'][$j]['periodOn'] = 0;
                }
            }
        }

        return $timetable;
    }

    /**
     * Retrieve the list of subjects for a user on a particular day
     *
     * @param $externalUserId
     * @param $dayNumber
     * @return bool
     */
    public function getDaySubjects($externalUserId, $dayNumber) {
        // Timetable.Room, SubjectClasses.Description, Timetable.PeriodNumber, Timetable.ClassCode, SubjectClasses.ClassCampus

        $user = $this->getUserHandler()->getBySynergy($externalUserId);

        $dayTimetable = $this->getDayTimetable($externalUserId, $user['uid'], 0, $dayNumber);

        if($dayTimetable === false) {
            return false;
        }

        foreach($dayTimetable as $k => $period) {
            if(!isset($period['code'])) {
                unset($dayTimetable[$k]);
            }
        }

        return array_values($dayTimetable);

    }

    /**
     * give a subject code and day this function returns the period that it occurs in
     * Assumption made that this should be the sequence rather than period name
     *
     * @param $classCode
     * @param $dayNumber
     * @return int|bool
     */
    public function getSubjectPeriod($classCode, $dayNumber) {
        $stmt = $this->getDatabase()->prepare("
            SELECT period
            FROM db2inst1.schoolbox_timetable
            WHERE class_code = :code
            AND day = :day
        ");

        $stmt->execute(array(
            'code' => $classCode,
            'day' => $dayNumber
        ));

        $class = $stmt->fetchColumn();

        if($class === false) {
            return false;
        }

        // return the SEQUENCE of the period (if that is not what is expected, then this should be changed)
        $i = 0;
        foreach($this->labelMap as $periodCode => $periodDetails) {
            ++$i;
            if($class == $periodCode) {
                return $i;
            }
        }

        return false;

    }

	/**
	 * Retrieves the name of a subject based on its class code
	 *
	 * @param $classCode
	 * @return string
	 */
	public function getSubjectName($classCode) {
        $stmt = $this->getDatabase()->prepare("
            SELECT class
            FROM db2inst1.schoolbox_classes
            WHERE class_code = :code
        ");

        $stmt->execute(array('code' => $classCode));

        return $stmt->fetchColumn(0);
    }

}
