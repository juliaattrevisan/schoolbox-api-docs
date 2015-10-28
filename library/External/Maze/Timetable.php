<?php

/**
 * External_Maze_Timetable
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class External_Maze_Timetable implements External_TimetableInterface
{
    /**
     * An instance of MSSQL
     *
     * @var null
     */
    private $db = null;

    /**
     * Helper
     *
     * @var null
     */
    private $helper = null;

    /**
     * An instance of Group
     *
     * @var Group
     */
    private $groupHandler = null;

    /**
     * Array key
     *
     * @var string
     */
    private $timetablePeriodLabel = 'TH_RLABEL';

    /**
     * Array key
     *
     * @var string
     */
    private $timetableDayLabel = 'TH_CLABEL';

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->setDb(MSSQL::getInstance());
        $this->setHelper(new External_Maze_Helper());
        $this->setGroupHandler(new Group());
    }

    /**
     * Test method for experimentation
     */
    public function getTest()
    {
//		$query = "
//			SELECT *
//			FROM TH
//			INNER JOIN THTQ ON TH.THKEY = THTQ.QKEY
//			INNER JOIN STMA ON THTQ.IDENT = STMA.IDENT
//			INNER JOIN TCTD ON THTQ.QROW = TCTD.TIME_INDEX
//			WHERE TH.THKEY = '{$this->getHelper()->getCurrentTHKEY()}'
//			AND TCTD.QKEY = '{$this->getHelper()->getCurrentTHKEY()}'
//			AND TCTD.TCTDKEY = '{$this->getHelper()->currentDate()} 12:00:00:000AM'
//			AND THTQ.QCOL = '{$this->getDayNumber(1)}'
//			AND STMA.SKEY = 'ABBOMEG'
//		";
//
//		$result1 = $this->getDb()->getAll($query);
//
//		$query = "
//			SELECT TOP 100 *
//			FROM TCTD
//			WHERE QKEY = '{$this->getHelper()->getCurrentTHKEY()}'
//			AND TCTDKEY = '{$this->getHelper()->currentDate()} 12:00:00:000AM'
//			ORDER BY TIME_INDEX
//		";
//
//		$result2 = $this->getDb()->getAll($query);
//
//		new \Alaress_ThreeProbe(null, $result1, null);
    }

    /**
     * The headings of the timetable, going horizontally
     * from left to right containing all the times and day numbers
     *
     * @param string $externalUserId
     * @param \Schoolbox\Campus $campus
     * @param int $day
     * @return array
     */
    public function getTimetableDefinition($externalUserId, \Schoolbox\Campus $campus, $day = 0)
    {
        $query = "
			SELECT TOP 50 *
			FROM TH
			INNER JOIN TCTD ON TH.THKEY = TCTD.QKEY
			WHERE THKEY = '{$this->getHelper()->getCurrentTHKEY()}'
		";

        $result = $this->getDb()->getAll($query);

        $timetableDef = array();

        if (count($result) && is_array($result)) {
            $definition = $result[0];

            for ($i = 1; $i <= $definition['THROWS']; $i++) {
                $dayTempKey = $this->getTimetableDayLabel($i);

                /* Day-specific e.g. for Dashboard
                 */
                if (!$day || "Day {$day}" == $definition[$dayTempKey]) {
/* TH_RLABEL01, TH_RLABEL02...TH_RLABEL13
                     */
                    $periodTempKey = $this->getTimetablePeriodLabel($i);

                    /* Ram it in
                     */
                    $timetableDef['desc'][] = $definition[$periodTempKey];
                    $timetableDef['start'][] = $this->getHelper()->mazeTime($result[$i - 1]['TIME_START']);
                    $timetableDef['end'][] = $this->getHelper()->mazeTime($result[$i - 1]['TIME_END']);
                    $timetableDef['number'][] = $i;
                    $timetableDef['sequence'][] = $i;
                }
            }
        }

        return $timetableDef;
    }

    /**
     * Get timetable period label
     *
     * @param $i
     * @return string
     */
    private function getTimetablePeriodLabel($i)
    {
        return $this->timetablePeriodLabel . ($i < 10 ? 0 . $i : $i);
    }

    /**
     * Get timetable day label
     *
     * @param $i
     * @return string
     */
    private function getTimetableDayLabel($i)
    {
        return $this->timetableDayLabel . ($i < 10 ? 0 . $i : $i);
    }

    /**
     * Get the current day number
     *
     * @param \Schoolbox\Campus $campus
     * @param int $date
     * @return bool
     */
    public function getDayNumber(\Schoolbox\Campus $campus, $date = 0)
    {
        $query = "
			SELECT TOP 1 QCOL
			FROM TCTQ
			INNER JOIN TH ON TCTQ.QKEY = TH.THKEY
			WHERE TCTQKEY = '{$this->getHelper()->currentDate()} 12:00:00:000AM'
		";

        $result = $this->getDb()->getRow($query);

        return $result['QCOL'];
    }

    /**
     * Returns the day name
     *
     * @param $dayNumber
     * @return String
     */
    public function getDayName($dayNumber)
    {
        return 'Day ' . $dayNumber;
    }

    /**
     * Calculates the current period based on the current time
     *
     * The returned data structure is required for Dashboard Timetable Controller
     *
     * @param int|\Schoolbox\Campus $campus
     * @param int $now
     * @return int
     */
    public function getPeriodNumber(\Schoolbox\Campus $campus, $now = 0)
    {
        if ($now) {
            $now = new \Alaress_Date_Format($now, 'U');
        } else {
            $now = Alaress_Date_Format::now();
        }


        $allTimes = $this->getPeriodTimes();

        if (is_array($allTimes) && count($allTimes)) {
            $i = 1;

            foreach ($allTimes as $currentTime) {
                if ($currentTime['startTime']->getDatabaseTime() <= $now->getDatabaseTime()) {
                    if ($currentTime['endTime']->getDatabaseTime() > $now->getDatabaseTime()) {
                        return array(
                            'PeriodNumber'    => $i,
                            'PeriodNumberSeq' => 1,
                        );
                    }
                }

                $i++;
            }
        }

        return array(
            'PeriodNumber'    => 0,
            'PeriodNumberSeq' => 0,
        );
    }

    /**
     * Return a user's timetable for a specific day
     *
     * @param $externalUserId
     * @param $userId
     * @param \Schoolbox\Campus $campus
     * @param $dayNumber
     * @return array|bool
     */
    public function getDayTimetable($externalUserId, $userId, \Schoolbox\Campus $campus, $dayNumber)
    {
        if (empty($externalUserId)) {
            return array();
        }
        if ($this->getGroupHandler()->isStudent($userId)) {
            return $this->getStudentTimetable($externalUserId, $campus, $dayNumber);
        } else {
            if ($this->getGroupHandler()->isStaff($userId)) {
                return $this->getStaffTimetable($externalUserId, $campus, $dayNumber);
            }
        }
    }

    /**
     * Return a user's timetable for a specific date
     *
     * Depending on the role for $userId (staff or student) this summons the respective
     * method to spawn associated timetable
     *
     *
     * @param $externalUserId
     * @param $userId
     * @param Schoolbox\Campus $campus
     * @param Alaress_Date_Format $date
     * @return mixed
     */
    public function getTimetableForDate($externalUserId, $userId, \Schoolbox\Campus $campus, Alaress_Date_Format $date)
    {
        return $this->getDayTimetable($externalUserId, $userId, $campus, $this->getDayNumber($campus, $date->getUnixTimeStamp()));
    }

    /**
     * Determine whether you're in a weekend
     *
     * @param $date
     * @return bool
     */
    private function isWeekend($date)
    {
        $object = new \Alaress_Date_Format(strtotime($date), 'U');
        return $object->isWeekend();
    }

    /**
     * Find next Monday
     *
     * @param $date
     * @return Alaress_Date_Format
     */
    private function nextMonday($date)
    {
        $object = new \Alaress_Date_Format(strtotime($date), 'U');

        $dayIndex = $object->getWeekDayNumber();

        /* Find out how may days to add to find the next Monday
         * Saturday is 6, Sunday is 7
         */
        if ($dayIndex > 5) {
            $difference = 8 - $dayIndex;
            $object->addDays($difference);
        }

        return $object->getUsingFormat('M j Y');
    }

    /**
     * Spawn a staff timetable for a particular user and day number
     *
     * @param $externalUserId
     * @param \Schoolbox\Campus $campus
     * @param $dayNumber
     * @return array
     */
    public function getStaffTimetable($externalUserId, \Schoolbox\Campus $campus, $dayNumber)
    {
        $definition = $this->getTimetableDefinition($externalUserId, $campus);

        $finalTimetable = array();

        foreach ($definition['number'] as $index) {
            $finalTimetable[$index - 1] = array(
                'periodName'     => $definition['desc'][$index - 1],
                'periodStart'    => $definition['start'][$index - 1],
                'periodEnd'      => $definition['end'][$index - 1],
                'periodNumber'   => $index,
                'periodSequence' => 1,
            );
        }

        /* Accelerate to the next Monday if we're in a weekend
         */
        $newDate = $this->getHelper()->currentDate();

        if ($this->isWeekend($newDate)) {
            $newDate = $this->nextMonday($newDate);
        }

        /* Proceed "normally"
         *
         * SB-6670
         * Note that when you are between semesters the TCTDKEY filter here
         * may force the query to return no data (no subjects exist at the
         * specified date)
         */
        $query = "
			SELECT *
			FROM TH
			INNER JOIN THTQ ON TH.THKEY = THTQ.QKEY
			INNER JOIN TCTD ON THTQ.QROW = TCTD.TIME_INDEX
			INNER JOIN SU ON SU.SUKEY = THTQ.SUBJ
			WHERE TH.THKEY = '{$this->getHelper()->getCurrentTHKEY()}'
			AND TCTD.QKEY = '{$this->getHelper()->getCurrentTHKEY()}'
			AND TCTD.TCTDKEY = '{$newDate} 12:00:00:000AM'
			AND THTQ.QCOL = '{$dayNumber}'
			AND (THTQ.T1TEACH = '{$externalUserId}' OR THTQ.T2TEACH = '{$externalUserId}')
			ORDER BY QCOL, QROW
		";

        $allRows = $this->getDb()->getAll($query);

        if (is_array($allRows) && count($allRows)) {
            foreach ($allRows as $currentRow) {
                $finalTimetable[$currentRow['QROW'] - 1]['class'][0]['description'] = $currentRow['FULLNAME'];
                $finalTimetable[$currentRow['QROW'] - 1]['class'][0]['code'] = $this->getHelper()->generateClassCode(
                    $currentRow['SUBJ'],
                    $currentRow['CLASS']
                );

                /* Find the teacher if one is assigned
                 */
                if (strlen($currentRow['T1TEACH']) || strlen($currentRow['T2TEACH'])) {
                    if ($currentRow['T1TEACH'] == $externalUserId) {
                        $key = 'T1TEACH';
                    } else {
                        if ($currentRow['T2TEACH'] == $externalUserId) {
                            $key = 'T2TEACH';
                        }
                    }

                    try {
                        $userMapper = new \Schoolbox\UserMapper();
                        $user = $userMapper->findOneByColumn('synergy_id', $currentRow[$key]);

                        /* Paste teacher in
                         */
                        $finalTimetable[$currentRow['QROW'] - 1]['class'][0]['staffID'] = $externalUserId;
                        $finalTimetable[$currentRow['QROW'] - 1]['class'][0]['staffTitle'] = $user->getTitle();
                        $finalTimetable[$currentRow['QROW'] - 1]['class'][0]['staffFirstname'] = $user->getFirstname();
                        $finalTimetable[$currentRow['QROW'] - 1]['class'][0]['staffSurname'] = $user->getLastname();
                    } catch (Exception $e) {
                    }
                }

                $finalTimetable[$currentRow['QROW'] - 1]['class'][0]['room'] = trim($currentRow['R1ROOM']) . " " . trim($currentRow['R2ROOM']);
            }
        }

        return $finalTimetable;
    }

    /**
     * Return a student's timetable for a specific day
     *
     * @param $externalUserId
     * @param \Schoolbox\Campus $campus
     * @param $dayNumber
     * @return mixed
     */
    public function getStudentTimetable($externalUserId, \Schoolbox\Campus $campus, $dayNumber)
    {
        $definition = $this->getTimetableDefinition($externalUserId, $campus);

        $finalTimetable = array();

        foreach ($definition['number'] as $index) {
            $finalTimetable[$index - 1] = array(
                'periodName'     => $definition['desc'][$index - 1],
                'periodStart'    => $definition['start'][$index - 1],
                'periodEnd'      => $definition['end'][$index - 1],
                'periodNumber'   => $index,
                'periodSequence' => 1,
            );
        }

        /* Accelerate to the next Monday if we're in a weekend
         */
        $newDate = $this->getHelper()->currentDate();

        if ($this->isWeekend($newDate)) {
            $newDate = $this->nextMonday($newDate);
        }

        /* Proceed "normally"
         */
        $query = "
			SELECT
				THTQ.QROW, THTQ.QCOL, THTQ.SUBJ, THTQ.CLASS, THTQ.R1ROOM, THTQ.R2ROOM,
				SU.FULLNAME,
				TTTG.T1TEACH, TTTG.T2TEACH
			FROM TH
			INNER JOIN THTQ ON TH.THKEY = THTQ.QKEY
			INNER JOIN STMA ON THTQ.IDENT = STMA.IDENT
			INNER JOIN TCTD ON THTQ.QROW = TCTD.TIME_INDEX
			INNER JOIN SU ON SU.SUKEY = THTQ.SUBJ
			INNER JOIN TTTG ON THTQ.IDENT = TTTG.IDENT
			WHERE TH.THKEY = '{$this->getHelper()->getCurrentTHKEY()}'
			AND TCTD.QKEY = '{$this->getHelper()->getCurrentTHKEY()}'
			AND TCTD.TCTDKEY = '{$newDate} 12:00:00:000AM'
			AND THTQ.QCOL = '{$dayNumber}'
			AND STMA.SKEY = '{$externalUserId}'
		";

        $allRows = $this->getDb()->getAll($query);

        if (is_array($allRows) && count($allRows)) {
            foreach ($allRows as $currentRow) {
                $finalTimetable[$currentRow['QROW'] - 1]['class'][0]['description'] = $currentRow['FULLNAME'];
                $finalTimetable[$currentRow['QROW'] - 1]['class'][0]['code'] = $this->getHelper()->generateClassCode(
                    $currentRow['SUBJ'],
                    $currentRow['CLASS']
                );

                /* Find the teacher - just choose the first one with content
                 */
                $teacherKey = '';

                if (strlen($currentRow['T1TEACH'])) {
                    $teacherKey = $currentRow['T1TEACH'];
                } else {
                    if (strlen($currentRow['T2TEACH'])) {
                        $teacherKey = $currentRow['T2TEACH'];
                    }
                }

                if (strlen($teacherKey)) {
                    try {
                        $userMapper = new \Schoolbox\UserMapper();
                        $user = $userMapper->findOneByColumn('synergy_id', $teacherKey);

                        /* Paste teacher in. There are dupe records for teachers at this point,
                         * so accept the first in the list
                         */
                        if (!strlen($finalTimetable[$currentRow['QROW'] - 1]['staffID'])) {
                            $finalTimetable[$currentRow['QROW'] - 1]['class'][0]['staffID'] = $teacherKey;
                            $finalTimetable[$currentRow['QROW'] - 1]['class'][0]['staffTitle'] = $user->getTitle();
                            $finalTimetable[$currentRow['QROW'] - 1]['class'][0]['staffFirstname'] = $user->getFirstname();
                            $finalTimetable[$currentRow['QROW'] - 1]['class'][0]['staffSurname'] = $user->getLastname();
                        }
                    } catch (Exception $e) {
                    }
                }

                $finalTimetable[$currentRow['QROW'] - 1]['class'][0]['room'] = trim($currentRow['R1ROOM']) . " " . trim($currentRow['R2ROOM']);
            }
        }

        return $finalTimetable;
    }

    /**
     * Get times for when periods begin and end
     *
     * @return array|bool
     */
    private function getPeriodTimes()
    {

        /* TODO: Some serious assumption about 14 here
         */
        $query = "
			SELECT TOP 14
				CONVERT(VARCHAR, TIME_START, 120) AS startTime,
				CONVERT(VARCHAR, TIME_END, 120) AS endTime
			FROM TCTD
			WHERE QKEY = '{$this->getHelper()->getCurrentTHKEY()}'
		";

        $result = $this->getDb()->getAll($query);

        $processed = array();

        if (is_array($result) && count($result)) {
            foreach ($result as $currentResult) {
                $processed[] = array(
                    'startTime' => new \Alaress_Date_Format($currentResult['startTime'], 'Y-m-d H:i:s'),
                    'endTime'   => new \Alaress_Date_Format($currentResult['endTime'], 'Y-m-d H:i:s'),
                );
            }
        }

        return $processed;
    }

    /**
     * Return the ENTIRE timetable for a user
     *
     * @param $externalUserId
     * @param $userId
     * @param \Schoolbox\Campus $campus
     * @return array|bool
     */
    public function getFullTimetable($externalUserId, $userId, \Schoolbox\Campus $campus)
    {
        if (empty($externalUserId)) {
            return array();
        }
        $timetable = array();
        $allDayTimetables = array();

        /* Get all the days' timetables first
         */
        for ($day = 1; $day <= $campus->getTimetableDay(); $day++) {
            $allDayTimetables[] = $this->getDayTimetable($externalUserId, $userId, $campus, $day);
        }

        /* Assemble into a 2D array
         * Each row represents a Period across each Day in the cycle
         *
         * [Period1 [Day1, Day2, Day3, DayN...]]
         * [Period2 [Day1, Day2, Day3, DayN...]]
         * [Period3 [Day1, Day2, Day3, DayN...]]
         * [PeriodN [Day1, Day2, Day3, DayN...]]
         * ...
         */
        for ($period = 0; $period < count($allDayTimetables[0]); $period++) {
            $timetable[$period]['periodName'] = $allDayTimetables[0][$period]['periodName'];
            $timetable[$period]['periodStart'] = $allDayTimetables[0][$period]['periodStart'];
            $timetable[$period]['periodEnd'] = $allDayTimetables[0][$period]['periodEnd'];
            $timetable[$period]['periodNumber'] = $allDayTimetables[0][$period]['periodNumber'];
            $timetable[$period]['periodSequence'] = $allDayTimetables[0][$period]['periodSequence'];

            /* Put in Day classes
             * Note that $timetable is now using a 1-index array
             */
            for ($day = 0; $day < $campus->getTimetableDay(); $day++) {
                if($allDayTimetables[$day][$period]['class'][0]['description']) {
                    $timetable[$period]['day'][$day+1][0] = array(
                        'description'       => $allDayTimetables[$day][$period]['class'][0]['description'],
                        'code'              => $allDayTimetables[$day][$period]['class'][0]['code'],
                        'room'              => $allDayTimetables[$day][$period]['class'][0]['room'],
                        'staffID'           => $allDayTimetables[$day][$period]['class'][0]['staffID'],
                        'staffFirstname'    => $allDayTimetables[$day][$period]['class'][0]['staffFirstname'],
                        'staffSurname'      => $allDayTimetables[$day][$period]['class'][0]['staffSurname'],
                        'staffTitle'        => $allDayTimetables[$day][$period]['class'][0]['staffTitle'],
                    );
                }
                else {
                    $timetable[$period]['day'][$day+1] = array();
                }
            }
        }

        return $timetable;
    }

    /**
     * @todo Implement for MAZE connector
     *
     * @param  array|string $roomCodes
     * @return array
     */
    public function getRoomTimetable($roomCodes)
    {
        // I'm not even going to try navigating this MAZE
        return array();
    }

    /* Getters/Setters
     */

    /**
     * Set db
     *
     * @param null $db
     */
    private function setDb($db)
    {
        $this->db = $db;
    }

    /**
     * Get db
     *
     * @return MSSQL
     */
    private function getDb()
    {
        return $this->db;
    }

    /**
     * Set helper
     *
     * @param null $helper
     */
    private function setHelper($helper)
    {
        $this->helper = $helper;
    }

    /**
     * Get helper
     *
     * @return External_Maze_Helper
     */
    private function getHelper()
    {
        return $this->helper;
    }

    /**
     * Set group handler
     *
     * @param \Group $groupHandler
     */
    private function setGroupHandler($groupHandler)
    {
        $this->groupHandler = $groupHandler;
    }

    /**
     * Get group handler
     *
     * @return \Group
     */
    private function getGroupHandler()
    {
        return $this->groupHandler;
    }

    /**
     * Set timetable period label
     *
     * @param string $timetablePeriodLabel
     */
    private function setTimetablePeriodLabel($timetablePeriodLabel)
    {
        $this->timetablePeriodLabel = $timetablePeriodLabel;
    }

    /**
     * Set timetable day label
     *
     * @param string $timetableDayLabel
     */
    private function setTimetableDayLabel($timetableDayLabel)
    {
        $this->timetableDayLabel = $timetableDayLabel;
    }
}
