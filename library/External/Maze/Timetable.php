<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Alaress\Database\Connector\IConnector;
use Schoolbox\Campus;
use Schoolbox\Database\Factory;

/**
 * Class External_Maze_Timetable
 */
class External_Maze_Timetable implements External_TimetableInterface
{
    /**
     * An instance of MSSQL
     *
     * @var IConnector
     */
    private $db;


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
     * A user mapper, used within the timetable to fetch the period's teacher
     *
     * @var \Schoolbox\UserMapper
     */
    private $userMapper = null;


    /**
     * A cache of users retrieved from $this->userMapper.
     *
     * @var array|\Schoolbox\User[]
     */
    private $userCache = [];


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
        $this->db = Factory::getInstanceExternal();
        $this->setHelper(new External_Maze_Helper());
        $this->setGroupHandler(new Group());
        $this->setUserMapper(\Schoolbox\UserMapper::getInstance());
    }


    /**
     * Test method for experimentation
     */
    public function getTest()
    {
//      $query = "
//          SELECT *
//          FROM TH
//          INNER JOIN THTQ ON TH.THKEY = THTQ.QKEY
//          INNER JOIN STMA ON THTQ.IDENT = STMA.IDENT
//          INNER JOIN TCTD ON THTQ.QROW = TCTD.TIME_INDEX
//          WHERE TH.THKEY = '{$this->getHelper()->getCurrentTHKEY()}'
//          AND TCTD.QKEY = '{$this->getHelper()->getCurrentTHKEY()}'
//          AND TCTD.TCTDKEY = '{$this->getHelper()->currentDate()} 12:00:00:000AM'
//          AND THTQ.QCOL = '{$this->getDayNumber(1)}'
//          AND STMA.SKEY = 'ABBOMEG'
//      ";
//
//      $result1 = $this->getDb()->getAll($query);
//
//      $query = "
//          SELECT TOP 100 *
//          FROM TCTD
//          WHERE QKEY = '{$this->getHelper()->getCurrentTHKEY()}'
//          AND TCTDKEY = '{$this->getHelper()->currentDate()} 12:00:00:000AM'
//          ORDER BY TIME_INDEX
//      ";
//
//      $result2 = $this->getDb()->getAll($query);
//
//      new \Alaress_ThreeProbe(null, $result1, null);
    }


    /**
     * @inheritDoc
     */
    public function getTimetableDefinition(?string $externalUserId, Campus $campus, $day = 0, $date = null): array
    {
        $periodQuery = "
            SELECT TOP 1 *
            FROM TH
            WHERE THKEY = '{$this->getHelper()->getCurrentTHKEY()}'
        ";
        $periods = $this->getDb()->getRow($periodQuery) ?: [];
        $query = "
            SELECT *
            FROM TCTD
            WHERE QKEY = '{$this->getHelper()->getCurrentTHKEY()}' AND
            CAST(TCTDKEY AS DATE) = CAST('{$date}' AS DATE)
        ";

        $result = $this->getDb()->getAll($query);

        $timetableDef = [
            'desc'     => [],
            'start'    => [],
            'end'      => [],
            'number'   => [],
            'sequence' => [],
        ];

        if (count($result) && is_array($result)) {
            foreach ($result as $current) {
                if (!empty($periods[$this->getTimetablePeriodLabel($current['TIME_INDEX'])])) {
                    $desc = $periods[$this->getTimetablePeriodLabel($current['TIME_INDEX'])];
                } else {
                    $desc = "Period {$current['TIME_INDEX']}";
                }

                $timetableDef['desc'][] = $desc;
                $timetableDef['start'][] = date("g:ia", strtotime($current['TIME_START']));
                $timetableDef['end'][] = date("g:ia", strtotime($current['TIME_END']));
                $timetableDef['number'][] = $current['TIME_INDEX'];
                $timetableDef['sequence'][] = 1;
            }
        }
        $timetableDef['periodCount'] = count($periods);
        $timetableDef['dayCount']    = 0;
        return $timetableDef;
    }


    /**
     * Get timetable period label
     *
     * @param int $i
     * @return string
     */
    private function getTimetablePeriodLabel($i)
    {
        return $this->timetablePeriodLabel . ($i < 10 ? 0 . $i : $i);
    }


    /**
     * Get the day number for the date specified
     *
     * @param Campus $campus
     * @param int    $date
     * @return bool
     * @throws InvalidArgumentException
     */
    public function getDayNumber(Campus $campus, $date = 0)
    {
        if ($date == 0) {
            $tempDate = $this->getHelper()->currentDate();
        } else {
            $temp = new Alaress_Date_Format($date, 'U');
            $tempDate = $temp->getDatabaseDate();
        }

        $query = "
            SELECT TOP 1 QCOL
            FROM TCTQ
            INNER JOIN TH ON TCTQ.QKEY = TH.THKEY AND TH.THKEY = '{$this->getHelper()->getCurrentTHKEY()}'
            WHERE TCTQKEY = CAST('{$tempDate}' AS DATE)
        ";

        $result = $this->getDb()->getRow($query);

        return empty($result) ? 0 : $result['QCOL'];
    }


    /**
     * Returns the day name
     *
     * @param int $dayNumber
     * @return String
     */
    public function getDayName($dayNumber): string
    {
        return 'Day ' . $dayNumber;
    }


    /**
     * @inheritDoc
     */
    public function getPeriodNumber(Campus $campus, $now = 0, \Schoolbox\User $user = null): array
    {
        if ($now) {
            $now = new Alaress_Date_Format($now, 'U');
        } else {
            $now = Alaress_Date_Format::now();
        }


        $allTimes = $this->getPeriodTimes();

        if (is_array($allTimes) && count($allTimes)) {
            $i = 1;

            foreach ($allTimes as $currentTime) {

                /** @var Alaress_Date_Format $startTime */
                /** @var Alaress_Date_Format $endTime */
                $startTime = $currentTime['startTime'];
                $endTime = $currentTime['endTime'];

                if ($now->between($startTime, $endTime)) {
                        return array(
                            'PeriodNumber'    => $i,
                            'PeriodNumberSeq' => 1,
                        );
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
     * @inheritDoc
     */
    public function getDayTimetable(string $externalUserId, $userId, Campus $campus, $dayNumber, ?Alaress_Date_Format $date = null): array
    {
        if (empty($externalUserId)) {
            return [];
        }

        if ($this->getGroupHandler()->isStudent($userId)) {
            return $this->getStudentTimetable($externalUserId, $campus, $dayNumber);
        }

        if ($this->getGroupHandler()->isStaff($userId)) {
            return $this->getStaffTimetable($externalUserId, $campus, $dayNumber);
        }

        return [];
    }


    /**
     * @inheritDoc
     */
    public function getTimetableForDate(string $externalUserId, $userId, Campus $campus, Alaress_Date_Format $date): array
    {
        if (empty($externalUserId)) {
            return [];
        }

        if ($this->getGroupHandler()->isStudent($userId)) {
            return $this->getStudentTimetable($externalUserId, $campus, $this->getDayNumber($campus, $date->getUnixTimeStamp()), $date);
        }

        if ($this->getGroupHandler()->isStaff($userId)) {
            return $this->getStaffTimetable($externalUserId, $campus, $this->getDayNumber($campus, $date->getUnixTimeStamp()), $date);
        }

        return [];
    }


    /**
     * Determine whether you're in a weekend
     *
     * @param string $date
     * @return bool
     * @throws InvalidArgumentException
     */
    private function isWeekend($date)
    {
        $object = new Alaress_Date_Format(strtotime($date), 'U');
        return $object->isWeekend();
    }


    /**
     * Find next Monday
     *
     * @param string $date
     * @return string
     * @throws InvalidArgumentException
     */
    private function nextMonday($date)
    {
        $object = new Alaress_Date_Format(strtotime($date), 'U');

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
     * @param string              $externalUserId
     * @param Campus              $campus
     * @param int                 $dayNumber
     * @param Alaress_Date_Format $date
     * @return array
     */
    public function getStaffTimetable($externalUserId, Campus $campus, $dayNumber, Alaress_Date_Format $date = null): array
    {
        if ($date instanceof Alaress_Date_Format) {
            $definition = $this->getTimetableDefinition($externalUserId, $campus, null, $date->getDatabaseDate());
        } else {
            $definition = $this->getTimetableDefinition($externalUserId, $campus);
        }

        $finalTimetable = array();

        foreach ($definition['number'] as $index) {
            $finalTimetable[$index - 1] = array(
                'periodName'     => $definition['desc'][$index - 1],
                'periodStart'    => $definition['start'][$index - 1],
                'periodEnd'      => $definition['end'][$index - 1],
                'periodNumber'   => $index,
                'periodSequence' => 1,
                'class'          => [],
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
                $key = '';

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
                        $user = $this->findUserByMazeId($currentRow[$key]);

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
                $finalTimetable[$currentRow['QROW'] - 1]['class'][0]['campus'] = $campus->getExtTimetableCode();
            }
        }

        return $finalTimetable;
    }


    /**
     * @param string                   $externalUserId
     * @param Campus        $campus
     * @param int                      $dayNumber
     * @param Alaress_Date_Format|null $date
     * @return array
     */
    public function getStudentTimetable($externalUserId, Campus $campus, $dayNumber, Alaress_Date_Format $date = null): array
    {
        if ($date instanceof Alaress_Date_Format) {
            $definition = $this->getTimetableDefinition($externalUserId, $campus, null, $date->getDatabaseDate());
        } else {
            $definition = $this->getTimetableDefinition($externalUserId, $campus);
        }

        $finalTimetable = array();

        foreach ($definition['number'] as $index) {
            $finalTimetable[$index - 1] = array(
                'periodName'     => $definition['desc'][$index - 1],
                'periodStart'    => $definition['start'][$index - 1],
                'periodEnd'      => $definition['end'][$index - 1],
                'periodNumber'   => $index,
                'periodSequence' => 1,
                'class'          => [],
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
                        $user = $this->findUserByMazeId($teacherKey);

                        /* Paste teacher in. There are dupe records for teachers at this point,
                         * so accept the first in the list
                         */
                        if (!strlen($finalTimetable[$currentRow['QROW'] - 1]['class'][0]['staffID'])) {
                            $finalTimetable[$currentRow['QROW'] - 1]['class'][0]['staffID'] = $teacherKey;
                            $finalTimetable[$currentRow['QROW'] - 1]['class'][0]['staffTitle'] = $user->getTitle();
                            $finalTimetable[$currentRow['QROW'] - 1]['class'][0]['staffFirstname'] = $user->getFirstname();
                            $finalTimetable[$currentRow['QROW'] - 1]['class'][0]['staffSurname'] = $user->getLastname();
                        }
                    } catch (Exception $e) {
                    }
                }

                $finalTimetable[$currentRow['QROW'] - 1]['class'][0]['room'] = trim($currentRow['R1ROOM']) . " " . trim($currentRow['R2ROOM']);
                $finalTimetable[$currentRow['QROW'] - 1]['class'][0]['campus'] = $campus->getExtTimetableCode();
            }
        }

        return $finalTimetable;
    }


    /**
     * Get times for when periods begin and end
     *
     * @return array
     * @throws InvalidArgumentException
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
                    'startTime' => new Alaress_Date_Format($currentResult['startTime'], 'Y-m-d H:i:s'),
                    'endTime'   => new Alaress_Date_Format($currentResult['endTime'], 'Y-m-d H:i:s'),
                );
            }
        }

        return $processed;
    }


    /**
     * @inheritDoc
     */
    public function getFullTimetable($externalUserId, $userId, Campus $campus): array
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
                if ($allDayTimetables[$day][$period]['class'][0]['description']) {
                    $timetable[$period]['day'][$day + 1][0] = array(
                        'description'       => $allDayTimetables[$day][$period]['class'][0]['description'],
                        'code'              => $allDayTimetables[$day][$period]['class'][0]['code'],
                        'campus'            => $allDayTimetables[$day][$period]['class'][0]['campus'],
                        'room'              => $allDayTimetables[$day][$period]['class'][0]['room'],
                        'staffID'           => $allDayTimetables[$day][$period]['class'][0]['staffID'],
                        'staffFirstname'    => $allDayTimetables[$day][$period]['class'][0]['staffFirstname'],
                        'staffSurname'      => $allDayTimetables[$day][$period]['class'][0]['staffSurname'],
                        'staffTitle'        => $allDayTimetables[$day][$period]['class'][0]['staffTitle'],
                    );
                } else {
                    $timetable[$period]['day'][$day + 1] = array();
                }
            }
        }

        return $timetable;
    }


    /**
     * @inheritDoc
     */
    public function getRoomTimetable(array $roomCodes): array
    {
        // I'm not even going to try navigating this MAZE
        return array();
    }


    /**
     * Finds a user by their MAZE ID.
     *
     * @param mixed $mazeId
     * @return \Schoolbox\User
     * @throws RuntimeException if $mazeId isn't set. This will be interpreted as no user found.
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \Schoolbox\Mapper\Exception\NotFound
     */
    private function findUserByMazeId($mazeId)
    {
        if (!isset($mazeId)) {
            throw new RuntimeException('$mazeId is empty');
        }
        if (array_key_exists($mazeId, $this->userCache)) {
            return $this->userCache[$mazeId];
        }
        $this->userCache[$mazeId] = $this->getUserMapper()->findByExternalId($mazeId);
        return $this->userCache[$mazeId];
    }

    /* Getters/Setters
     */

    /**
     * Set db
     *
     * @param IConnector $db
     */
    private function setDb($db)
    {
        $this->db = $db;
    }


    /**
     * Get db
     *
     * @return IConnector
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
     * @return \Schoolbox\UserMapper
     */
    private function getUserMapper()
    {
        return $this->userMapper;
    }


    /**
     * @param \Schoolbox\UserMapper $userMapper
     * @return $this
     */
    private function setUserMapper($userMapper)
    {
        $this->userMapper = $userMapper;
        return $this;
    }


    /**
     * @inheritDoc
     */
    public function findCycleLookup(): array
    {
        return [];
    }
}
