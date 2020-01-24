<?php
/**
 * Schooledge implementation of timetable.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
use Schoolbox\Campus;
use Schoolbox\UserMapper;

class External_Schooledge_Timetable implements External_TimetableInterface
{

    /**
     * Timetable date type constant - this date is not in the sequence.
     *
     * @var int
     */
    private const TIMETABLE_DATE_TYPE_NON_DAY = 10000;

    /**
     * Timetable date type constant - this day is forced to a particular day in the sequence.
     *
     * @var int
     */
    private const TIMETABLE_DATE_TYPE_FIXED_DAY = 10003;

    /**
     * Timetable date type constant - the timetable restarts on this day.
     *
     * @var int
     */
    private const TIMETABLE_DATE_TYPE_RESTART_DAY = 10002;

    /**
     * Timetable date type constant - this day is in the sequence, but is skipped?
     *
     * @var int
     */
    private const TIMETABLE_DATE_TYPE_SKIP_DAY = 10001;

    // Common timetable query parts
    private $classSelect = '
        t.Name                      AS ClassCampus,
        tc.Name                     AS ClassCode,
        ts.Name                     AS ClassName,
        td.SortOrder                AS DayNumber,
        tpe.Code                    AS PeriodNumber,
        td.Name                     AS DayName,
        CAST(tpe.StartTime AS TIME) AS PeriodStart,
        CAST(tpe.EndTime   AS TIME) AS PeriodEnd,
        tpe.Name                    AS PeriodName
    ';

    private $baseStaffSelect = '
        CASE WHEN tcis.TimetableClassStaffID IS NULL
            THEN 1
            ELSE 0
        END                         AS UseBaseStaff,
        staff_base.Code             AS BaseStaffID,
        staff_base.FirstName        AS BaseStaffPreferredName,
        staff_base.LastName         AS BaseStaffSurname,
        staff_base.Title            AS BaseStaffTitle
    ';

    private $replacementStaffSelect = '
        CASE WHEN staff_replacement.Code IS NOT NULL
            THEN 1
            ELSE 0
        END                         AS UseReplacementStaff,
        staff_replacement.Code      AS ReplacementStaffID,
        staff_replacement.FirstName AS ReplacementStaffPreferredName,
        staff_replacement.LastName  AS ReplacementStaffSurname,
        staff_replacement.Title     AS ReplacementStaffTitle
    ';

    private $baseRoomSelect = '
        room_base.Code              AS BaseRoom
    ';

    private $replacementRoomSelect = '
        room_replacement.Code       AS ReplacementRoom
    ';

    private $timetableJoin = 'INNER JOIN fc.Timetable t ON ts.TimetableID = t.ID';

    private $baseStaffJoin = '
        LEFT  JOIN fc.TimetableClassStaff tcs        ON tc.ID        = tcs.TimetableClassId
        LEFT  JOIN dbo.Staff              staff_base ON tcs.PersonID = staff_base.PersonID
    ';

    private $replacementStaffJoin = '
        LEFT  JOIN fc.TimetableClassInstanceStaff tcis              ON tci.ID        = tcis.TimetableClassInstanceId
        LEFT  JOIN dbo.Staff                      staff_replacement ON tcis.PersonID = staff_replacement.PersonID
    ';

    private $baseRoomJoin = '
        LEFT  JOIN fc.TimetableClassRoom tcr       ON tc.ID            = tcr.TimetableClassId
        LEFT  JOIN fc.Room               tcrr      ON tcr.RoomID       = tcrr.ID
        LEFT  JOIN dbo.Room              room_base ON tcrr.AdminRoomID = room_base.ID
    ';

    private $replacementRoomJoin = '
        LEFT  JOIN fc.TimetableClassInstanceRoom tcir             ON tci.ID            = tcir.TimetableClassInstanceId AND tcir.RoomSpecified = 1
        LEFT  JOIN fc.Room                       tcirr            ON tcir.RoomID       = tcirr.ID
        LEFT  JOIN dbo.Room                      room_replacement ON tcirr.AdminRoomID = room_replacement.ID
    ';


    /**
     * @var MSSQL
     */
    private $database;


    /**
     * Constructor
     */
    public function __construct()
    {
        $this->database = MSSQL::getInstance();
    }


    /**
     * @inheritdoc
     */
    public function getTimetableDefinition(?string $externalUserId, Campus $campus, $day = 0): array
    {
        if (!$day) {
            $day = $this->getDayNumber($campus);
        }
        if (!$day) {
            $day = 1;
        }
        $dayCount = $this->database->getOne("
            SELECT COUNT(DISTINCT td.ID)
            FROM       fc.Timetable       t
            INNER JOIN fc.TimetableDay    td ON t.ID  = td.TimetableId
            WHERE t.Name    = {$this->database->smartQuote($campus->getExtTimetableCode())}
            AND   t.Enabled = 1
        ");

        // Filter out anything other than HH:MM in query
        $result = $this->database->getAll("
            SELECT
                tp.Name                    AS Name,
                SUBSTRING(CONVERT(CHAR(8), tp.StartTime, 108), 1, 5) AS StartTime,
                SUBSTRING(CONVERT(CHAR(8), tp.EndTime, 108), 1, 5)   AS EndTime,
                td.SortOrder               AS DayNumber,
                tp.Code                    AS PeriodNumber

            FROM       fc.Timetable       t
            INNER JOIN fc.TimetableDay    td ON t.ID  = td.TimetableId
            INNER JOIN fc.TimetablePeriod tp ON td.ID = tp.TimetableDayId

            WHERE t.Name       = {$this->database->smartQuote($campus->getExtTimetableCode())}
            AND   t.Enabled    = 1
            AND   td.SortOrder = {$this->database->smartQuote($day)}

            ORDER BY DayNumber ASC, StartTime ASC, EndTime ASC
        ");

        // Key result by period number
        $periods = array_combine(
            array_column($result, 'PeriodNumber'),
            $result
        );

        $definition = [
            'desc'        => [],
            'start'       => [],
            'end'         => [],
            'number'      => [],
            'sequence'    => [],
            'periodCount' => count($periods),
            'dayCount'    => $dayCount,
        ];

        foreach ($periods as $period) {
            $definition['desc'][]     = $period['Name'];
            $definition['start'][]    = $period['StartTime'];
            $definition['end'][]      = $period['EndTime'];
            $definition['number'][]   = $period['PeriodNumber'];
            $definition['sequence'][] = 1;
        }
        return $definition;
    }


    /**
     * @inheritdoc
     */
    public function getDayNumber(Campus $campus, $date = 0)
    {
        $timetableDate = $this->getTimetableDate($campus, $this->getDateObjectFromTimestamp($date));
        if (isset($timetableDate)) {
            return $timetableDate['TimetableDay']['SortOrder'];
        }
        return 0;
    }


    /**
     * @inheritdoc
     */
    public function getDayName($dayNumber): string
    {
        // Kind of requires a campus, be this method doesn't have one.
        return $this->database->getOne('
            SELECT DISTINCT td.Name
            FROM       fc.Timetable       t
            INNER JOIN fc.TimetableDay    td ON t.ID  = td.TimetableId
            WHERE t.Enabled = 1
            AND   td.SortOrder = :day_number
        ', [ 'day_number' => $dayNumber]);
    }


    /**
     * @inheritdoc
     */
    public function getPeriodNumber(Campus $campus, $now = 0, \Schoolbox\User $user = null): array
    {
        $dateObject    = $this->getDateObjectFromTimestamp($now);
        $timetableDate = $this->getTimetableDate($campus, $dateObject);
        if (!isset($timetableDate)) {
            return [];
        }

        // SchoolEdge doesn't really have period numbers as such - use period name.
        $quotedTime      = $this->database->smartQuote($dateObject->getDatabaseTime());
        $timetablePeriod = $this->database->getRow($q="
            SELECT tp.*
            FROM fc.TimetablePeriod tp

            WHERE tp.TimetableDayID = {$this->database->smartQuote($timetableDate['TimetableDay']['ID'])}
            AND   CAST(tp.StartTime AS TIME) <= $quotedTime
            AND   CAST(tp.EndTime   AS TIME) >  $quotedTime
        ");
        if (empty($timetablePeriod)) {
            return [];
        }
        return [
            'PeriodNumber'    => $timetablePeriod['Code'],
            'PeriodNumberSeq' => $timetablePeriod['Code'],
        ];
    }


    /**
     * @inheritdoc
     */
    public function getDayTimetable(string $externalUserId, $userId, Campus $campus, $dayNumber): array
    {
        $extraWhere          = "td.SortOrder = {$this->database->smartQuote($dayNumber)}";
        $timetabledClassRows = $this->getAllTimetableRows($externalUserId, $userId, $campus, $extraWhere);
        if (empty($timetabledClassRows)) {
            return [];
        }

        $timetable = $this->getTimetableDefinitionForTimetable($externalUserId, $campus);
        foreach ($timetabledClassRows as $timetabledClass) {
            foreach ($timetable as $x => $timetablePeriod) {
                if ($timetablePeriod['periodNumber'] == $timetabledClass['PeriodNumber']) {
                    if (!isset($timetable[$x]['class'])) {
                        $timetable[$x]['class'] = [];
                    }
                    $timetable[$x]['class'] += $this->getTimetableEntries($timetabledClass);
                }
            }
        }
        return $timetable;
    }


    /**
     * @inheritdoc
     */
    public function getTimetableForDate(string $externalUserId, $userId, Campus $campus, Alaress_Date_Format $date): array
    {
        $timetabledClassRows = $this->getAllTimetableRows($externalUserId, $userId, $campus);
        if (empty($timetabledClassRows)) {
            return [];
        }

        // Figure out the day number for this day, then join onto that day
        $timetableDate = $this->getTimetableDate($campus, $date);
        if (!isset($timetableDate['TimetableDay']['ID'])) {
            return [];
        }

        $extraWhere          = "td.ID = {$this->database->smartQuote($timetableDate['TimetableDay']['ID'])}";
        $timetabledClassRows = $this->getAllTimetableRows($externalUserId, $userId, $campus, $extraWhere);
        if (empty($timetabledClassRows)) {
            return [];
        }

        $timetable = $this->getTimetableDefinitionForTimetable($externalUserId, $campus);
        foreach ($timetabledClassRows as $timetabledClass) {
            foreach ($timetable as $x => $timetablePeriod) {
                if ($timetablePeriod['periodNumber'] == $timetabledClass['PeriodNumber']) {
                    if (!isset($timetable[$x]['class'])) {
                        $timetable[$x]['class'] = [];
                    }
                    $timetable[$x]['class'] += $this->getTimetableEntries($timetabledClass);
                }
            }
        }
        return $timetable;
    }


    /**
     * @inheritdoc
     */
    public function getFullTimetable($externalUserId, $userId, Campus $campus): array
    {
        $timetabledClassRows = $this->getAllTimetableRows($externalUserId, $userId, $campus);
        if (empty($timetabledClassRows)) {
            return [];
        }

        // Build the timetable
        $timetable = $this->getTimetableDefinitionForTimetable($externalUserId, $campus);
        foreach ($timetabledClassRows as $timetabledClass) {
            foreach ($timetable as $x => $timetablePeriod) {
                if ($timetablePeriod['periodNumber'] == $timetabledClass['PeriodNumber']) {
                    $dayNumber = $timetabledClass['DayNumber'];
                    if (!isset($timetable[$x]['day'][$dayNumber])) {
                        $timetable[$x]['day'][$dayNumber] = [];
                    }
                    $timetable[$x]['day'][$dayNumber] += $this->getTimetableEntries($timetabledClass);
                }
            }
        }
        return $timetable;
    }


    /**
     * @inheritdoc
     */
    public function getRoomTimetable(array $roomCodes): array
    {
        // Sort out room codes
        if (empty($roomCodes)) {
            return [];
        }

        // Build query parts for them
        $extraWhereParts = [];
        foreach ($roomCodes as $roomCode) {
            $extraWhereParts[] = "room_base.Code = {$this->database->smartQuote($roomCode)}";
            $extraWhereParts[] = "room_replacement.Code = {$this->database->smartQuote($roomCode)}";
        }
        $extraWhere = implode(' OR ', $extraWhereParts);

        // The room timetable query.
        // Note it has no dates, only timetable day and period;
        // therefore it doesn't join onto CROSS APPLY fc.PeriodExpand() like
        // the other timetable queries.
        $query = "
            SELECT
                ts.Name                     AS Description,
                $this->baseRoomSelect,
                $this->replacementRoomSelect,
                td.SortOrder                AS DayNumber,
                tp.Code                     AS PeriodNumber,
                SUBSTRING(CONVERT(CHAR(8), tp.StartTime, 108), 1, 5) AS PeriodStart,
                SUBSTRING(CONVERT(CHAR(8), tp.EndTime, 108), 1, 5)   AS PeriodEnd,
                tp.Name                     AS PeriodName,
                t.Name                      AS TimetableGroup,
                t.Name                      AS ClassCampus,
                tc.Name                     AS ClassCode,
                ts.Name                     AS ClassName,
                $this->baseStaffSelect,
                $this->replacementStaffSelect

            FROM       fc.TimetableSubject       ts
            -- Join onto staff
            INNER JOIN fc.TimetableClass         tc   ON ts.ID             = tc.TimetableSubjectID
            INNER JOIN fc.TimetableClassInstance tci  ON tc.ID             = tci.TimetableClassID
            -- Join onto period
            INNER JOIN fc.TimetableBlockInstance tbi  ON tci.TimetableBlockInstanceID = tbi.ID
            INNER JOIN fc.TimetablePeriod        tp   ON tbi.TimetablePeriodID        = tp.ID
            INNER JOIN fc.TimetableDay           td   ON tp.TimetableDayID            = td.ID
            $this->baseStaffJoin
            $this->replacementStaffJoin
            $this->baseRoomJoin
            $this->replacementRoomJoin
            $this->timetableJoin

            WHERE t.Enabled = 1
            AND {$extraWhere}

            ORDER BY td.SortOrder ASC, tp.Code ASC
        ";

        // Finagle result into room code format
        $result = $this->database->getAll($query);
        foreach ($result as &$row) {
            $newRow = [
                'Description'    => $row['Description'],
                'DayNumber'      => $row['DayNumber'],
                'PeriodNumber'   => $row['PeriodNumber'],
                'PeriodStart'    => $row['PeriodStart'],
                'PeriodEnd'      => $row['PeriodEnd'],
                'PeriodName'     => $row['PeriodName'],
                'TimetableGroup' => $row['TimetableGroup'],
                'ClassCampus'    => $row['ClassCampus'],
                'ClassCode'      => $row['ClassCode'],
                'ClassName'      => $row['ClassName'],
            ];
            $newRow['RoomCode'] = $row['ReplacementRoom'] ?? $row['BaseRoom'] ?? '';

            if ($row['UseReplacementStaff']) {
                $newRow['StaffID']            = $row['ReplacementStaffID'];
                $newRow['StaffTitle']         = $row['ReplacementStaffTitle'];
                $newRow['StaffPreferredName'] = $row['ReplacementStaffPreferredName'];
                $newRow['StaffSurname']       = $row['ReplacementStaffSurname'];
            }
            else if ($row['UseBaseStaff']) {
                $newRow['StaffID']            = $row['BaseStaffID'];
                $newRow['StaffTitle']         = $row['BaseStaffTitle'];
                $newRow['StaffPreferredName'] = $row['BaseStaffPreferredName'];
                $newRow['StaffSurname']       = $row['BaseStaffSurname'];
            }

            $row = $newRow;
        }
        unset($row);

        return $result;
    }


    // Helper methods


    /**
     * Gets dates for a timetable
     *
     * @param Campus $campus
     * @return array
     * @throws InvalidArgumentException
     */
    protected function getTimetableDates(Campus $campus): array
    {
        // Adapted from some C# code SchoolEdge gave us
        $quotedCampus   = $this->database->smartQuote($campus->getExtTimetableCode());
        $timetable      = $this->database->getRow("
            SELECT
                CAST(t.StartDate AS DATE) AS StartDate,
                CAST(t.EndDate   AS DATE) AS EndDate
            FROM fc.Timetable t
            WHERE t.Name = {$quotedCampus}
        ");
        if (!$timetable) {
            return [];
        }

        // All timetable days
        $timetableDays  = $this->database->getAll("
            SELECT td.*
            FROM       fc.TimetableDay td
            INNER JOIN fc.Timetable    t  ON td.TimetableID    = t.ID
            WHERE t.Name = {$quotedCampus}
            ORDER BY td.SortOrder ASC
        ");
        if (!is_array($timetableDays)) {
            return [];
        }

        // All dates within the timetable
        $timetableDates = $this->database->getAll("
            SELECT
                CAST(td.DateValue AS DATE) AS TimetableDate,
                td.TimetableDateTypeID,
                td.TimetableDayID
            FROM       fc.TimetableDate td
            INNER JOIN fc.Timetable     t  ON td.TimetableID    = t.ID
            WHERE t.Name = {$quotedCampus}
            ORDER BY TimetableDate ASC
        ");
        if (is_array($timetableDates)) {
            $timetableDates = array_combine(
                array_column($timetableDates, 'TimetableDate'),
                $timetableDates
            );

            // Get first date
            ksort($timetableDates);
            $firstDate = current($timetableDates);
        } else {
            // The timetable requires at least one timetable date in order to parse,
            // so we know where to start.
            return [];
        }

        // Start at the latest of timetable start, and first timetable date
        $startDate = new Alaress_Date_Format(
            max($timetable['StartDate'], $firstDate['TimetableDate']),
            Alaress_Date_Format::DATABASE_DATE_FORMAT
        );
        $endDate   = new Alaress_Date_Format($timetable['EndDate'], Alaress_Date_Format::DATABASE_DATE_FORMAT);

        // Fill in missing timetable dates
        for ($currentDate = clone $startDate; $currentDate->lte($endDate); $currentDate = $currentDate->addDays(1)) {
            $dateString = $currentDate->getDatabaseDate();
            if (!isset($timetableDates[$dateString])) {
                $timetableDates[$dateString] = [
                    'TimetableDate'       => $dateString,
                    'TimetableDateTypeID' => null,
                    'TimetableDayID'      => null,
                ];
            }
        }

        // Sort by key for clarity
        ksort($timetableDates);

        // Add timetable days
        $dayIndex = -1;
        foreach ($timetableDates as &$timetableDate) {
            $keepIndex = $dayIndex;
            $dayIndex++;
            if ($dayIndex > (count($timetableDays) - 1)) {
                $dayIndex = 0;
            }
            $timetableDay   = $timetableDays[$dayIndex];
            $timetableDayId = $timetableDay['ID'];

            switch ($timetableDate['TimetableDateTypeID']) {
                case self::TIMETABLE_DATE_TYPE_NON_DAY:
                    // Non-day - increment the keep index?
                    $dayIndex = $keepIndex;
                    break;

                case self::TIMETABLE_DATE_TYPE_SKIP_DAY:
                    break;

                case self::TIMETABLE_DATE_TYPE_RESTART_DAY:
                    foreach ($timetableDays as $i => $timetableDay) {
                        if ($timetableDay['ID'] == $timetableDate['TimetableDayID']) {
                            $dayIndex = $i;
                            break;
                        }
                    }
                    break;

                case self::TIMETABLE_DATE_TYPE_FIXED_DAY:
                    break;

                default:
                    $timetableDate['TimetableDayID'] = $timetableDayId;
                    break;
            }

            // Set timetable day fully
            $timetableDate['TimetableDay'] = isset($timetableDate['TimetableDayID'])
                ? $timetableDay
                : null;
            unset($timetableDate['TimetableDayID']);
        }
        unset($timetableDate);

        return $timetableDates;
    }


    /**
     * Extract date object from timestamp.
     *
     * @param int $timestamp
     * @return Alaress_Date_Format
     * @throws InvalidArgumentException
     */
    protected function getDateObjectFromTimestamp($timestamp): \Alaress_Date_Format
    {
        return $timestamp
            ? new Alaress_Date_Format($timestamp, Alaress_Date_Format::TIMESTAMP)
            : Alaress_Date_Format::now();
    }


    /**
     * Fetches the timetable date for a given day
     *
     * @param Campus              $campus
     * @param Alaress_Date_Format $dateObject
     * @return array|null the timetable date, or null if it doesn't exist
     * @throws InvalidArgumentException
     */
    protected function getTimetableDate(Campus $campus, Alaress_Date_Format $dateObject): ?array
    {
        $timetableDates = $this->getTimetableDates($campus);
        $key            = $dateObject->getDatabaseDate();
        return array_key_exists($key, $timetableDates)
            ? $timetableDates[$key]
            : null;
    }


    /**
     * Gets timetable definition into a form which timetable methods can use.
     *
     * @param mixed  $externalUserId
     * @param Campus $campus
     * @param int    $day
     * @return array
     */
    protected function getTimetableDefinitionForTimetable($externalUserId, Campus $campus, $day = 0): array
    {
        $timetabledef = $this->getTimetableDefinition($externalUserId, $campus, $day);
        $timetable    = [];
        foreach ($timetabledef['desc'] as $x => $name) {
            $timetable[$x]['periodName']     = $timetabledef['desc'][$x];
            $timetable[$x]['periodNumber']   = $timetabledef['number'][$x];
            $timetable[$x]['periodSequence'] = $timetabledef['sequence'][$x];
            $timetable[$x]['periodStart']    = $timetabledef['start'][$x];
            $timetable[$x]['periodEnd']      = $timetabledef['end'][$x];
            // Fill in each day with blanks
            $timetable[$x]['day']            = array_fill(1, $timetabledef['dayCount'], []);
            $timetable[$x]['class']          = [];
        }
        return $timetable;
    }


    /**
     * @param $timetabledClass
     * @return array
     */
    protected function getTimetableEntries($timetabledClass): array
    {
        $parts     = [];
        $classCode = $timetabledClass['ClassCode'];
        $basePart  = [
            'description'    => $timetabledClass['ClassName'],
            'code'           => $timetabledClass['ClassCode'],
            'campus'         => $timetabledClass['ClassCampus'],
            'room'           => $timetabledClass['ReplacementRoom'] ?? $timetabledClass['BaseRoom'] ?? '',
        ];
        if ($timetabledClass['UseReplacementStaff']) {
            $parts[$classCode.'-'.$timetabledClass['ReplacementStaffID']] = $basePart + [
                    'staffID'        => $timetabledClass['ReplacementStaffID'],
                    'staffFirstname' => $timetabledClass['ReplacementStaffPreferredName'],
                    'staffSurname'   => $timetabledClass['ReplacementStaffSurname'],
                    'staffTitle'     => $timetabledClass['ReplacementStaffTitle'],
                ];
        }
        else if ($timetabledClass['UseBaseStaff']) {
            $parts[$classCode.'-'.$timetabledClass['BaseStaffID']] = $basePart + [
                'staffID'        => $timetabledClass['BaseStaffID'],
                'staffFirstname' => $timetabledClass['BaseStaffPreferredName'],
                'staffSurname'   => $timetabledClass['BaseStaffSurname'],
                'staffTitle'     => $timetabledClass['BaseStaffTitle'],
            ];
        }
        return $parts;
    }


    /**
     * Helper function for getting all timetable related rows for a user.
     *
     * @param mixed  $externalUserId
     * @param int    $userId
     * @param Campus $campus
     * @param string $extraWhere
     * @return array
     */
    protected function getAllTimetableRows($externalUserId, $userId, Campus $campus, $extraWhere = ''): array
    {
        try {
            $user = UserMapper::getInstance()->findById($userId);
        } catch (\Schoolbox\Mapper\Exception $e) {
            return [];
        }

        if (!empty($extraWhere)) {
            $extraWhere = 'AND ' . $extraWhere;
        }
        if ($user->getRole()->isStudent()) {
            $query = "
                {$this->getStudentBlockedTimetableQuery($externalUserId, $campus)}
                $extraWhere
                UNION
                {$this->getStudentNonBlockedTimetableQuery($externalUserId, $campus)}
                $extraWhere
                ORDER BY DayNumber asc, PeriodStart asc
            ";

        } elseif ($user->getRole()->isStaff()) {
            $query = "
                {$this->getStaffBlockedTimetableQuery($externalUserId, $campus)}
                $extraWhere
                UNION
                {$this->getStaffNonBlockedTimetableQuery($externalUserId, $campus)}
                $extraWhere
                ORDER BY DayNumber ASC, PeriodStart ASC
            ";

        } else {
            return [];
        }


        $result = $this->database->getAll($query);
        return is_array($result) ? $result : [];
    }


    /**
     * @param mixed  $externalUserId
     * @param Campus $campus
     * @return string
     */
    protected function getStaffBlockedTimetableQuery($externalUserId, Campus $campus): string
    {
        $quotedId = $this->database->smartQuote($externalUserId);
        return "
            SELECT
                $this->classSelect,
                $this->baseStaffSelect,
                $this->replacementStaffSelect,
                $this->baseRoomSelect,
                $this->replacementRoomSelect

            FROM       fc.TimetableSubject       ts

            -- Join onto blocked classes
            INNER JOIN fc.TimetableClass         tc   ON ts.ID = tc.TimetableSubjectID
            INNER JOIN fc.TimetableClassInstance tci  ON tc.ID = tci.TimetableClassID AND tci.Active = 1
            INNER JOIN fc.TimetableBlockInstance tbi  ON tci.TimetableBlockInstanceID = tbi.ID

            -- Periods for blocked classes
            INNER JOIN fc.TimetablePeriod        tp   ON tbi.TimetablePeriodID        = tp.ID
            CROSS APPLY fc.PeriodExpand(tbi.TimetablePeriodID, tbi.Consecutive) tpe
            INNER JOIN fc.TimetableDay           td   ON tpe.TimetableDayID           = td.ID

            -- Staff, room, timetable
            $this->baseStaffJoin
            $this->replacementStaffJoin
            $this->baseRoomJoin
            $this->replacementRoomJoin
            $this->timetableJoin

            WHERE (
                (staff_base.Code = {$quotedId} AND tcis.TimetableClassStaffID IS NULL)
                OR
                staff_replacement.Code = {$quotedId}
            )
            AND t.Name    = {$this->database->smartQuote($campus->getExtTimetableCode())}
            AND t.Enabled = 1
        ";
    }


    /**
     * @param mixed  $externalUserId
     * @param Campus $campus
     * @return string
     */
    protected function getStaffNonBlockedTimetableQuery($externalUserId, Campus $campus): string
    {
        $quotedId = $this->database->smartQuote($externalUserId);
        return "
            SELECT
                $this->classSelect,
                $this->baseStaffSelect,
                $this->replacementStaffSelect,
                $this->baseRoomSelect,
                $this->replacementRoomSelect

            FROM       fc.TimetableSubject       ts

            -- Join onto non-blocked classes
            INNER JOIN fc.TimetableClass         tc   ON ts.ID = tc.TimetableSubjectID
            INNER JOIN fc.TimetableClassInstance tci  ON tc.ID = tci.TimetableClassID  AND tci.Active = 1
                                                      AND tci.TimetableBlockInstanceID IS NULL

            -- Periods for non-blocked classes
            CROSS APPLY fc.PeriodExpand(tci.TimetablePeriodID, tci.Consecutive) tpe
            INNER JOIN fc.TimetableDay           td   ON tpe.TimetableDayID           = td.ID

            -- Staff, room, timetable
            $this->baseStaffJoin
            $this->replacementStaffJoin
            $this->baseRoomJoin
            $this->replacementRoomJoin
            $this->timetableJoin

            WHERE (
                (staff_base.Code = {$quotedId} AND tcis.TimetableClassStaffID IS NULL)
                OR
                staff_replacement.Code = {$quotedId}
            )
            AND t.Name    = {$this->database->smartQuote($campus->getExtTimetableCode())}
            AND t.Enabled = 1
        ";
    }


    /**
     * @param mixed  $externalUserId
     * @param Campus $campus
     * @return string
     */
    protected function getStudentBlockedTimetableQuery($externalUserId, Campus $campus): string
    {
        $quotedId = $this->database->smartQuote($externalUserId);
        return "
            SELECT
                $this->classSelect,
                $this->baseStaffSelect,
                $this->replacementStaffSelect,
                $this->baseRoomSelect,
                $this->replacementRoomSelect

            FROM       fc.TimetableSubject       ts
            -- Join onto student via subject choice
            INNER JOIN fc.TimetableClass         tc   ON ts.ID             = tc.TimetableSubjectID
            INNER JOIN fc.TimetableClassInstance tci  ON tc.ID             = tci.TimetableClassID  AND tci.Active = 1
            INNER JOIN fc.TimetableSubjectChoice tsc  ON tc.ID             = tsc.TimetableClassID
            INNER JOIN dbo.Student               s    ON tsc.PersonID      = s.PersonID
            -- Expand period
            INNER JOIN fc.TimetableBlockInstance tbi  ON tci.TimetableBlockInstanceID = tbi.ID
            INNER JOIN fc.TimetablePeriod        tp   ON tbi.TimetablePeriodID        = tp.ID
            CROSS APPLY fc.PeriodExpand(tbi.TimetablePeriodID, tbi.Consecutive) tpe
            INNER JOIN fc.TimetableDay           td   ON tpe.TimetableDayID           = td.ID
            $this->baseStaffJoin
            $this->replacementStaffJoin
            $this->baseRoomJoin
            $this->replacementRoomJoin
            $this->timetableJoin

            WHERE s.Code = {$quotedId}
            AND   t.Name    = {$this->database->smartQuote($campus->getExtTimetableCode())}
            AND   t.Enabled = 1
        ";
    }


    /**
     * @param mixed  $externalUserId
     * @param Campus $campus
     * @return string
     */
    protected function getStudentNonBlockedTimetableQuery($externalUserId, Campus $campus): string
    {
        $quotedId = $this->database->smartQuote($externalUserId);
        return "
            SELECT
                $this->classSelect,
                $this->baseStaffSelect,
                $this->replacementStaffSelect,
                $this->baseRoomSelect,
                $this->replacementRoomSelect

            FROM       fc.TimetableSubject       ts
            -- Join onto student via year level
            INNER JOIN fc.TimetableClass         tc   ON ts.ID             = tc.TimetableSubjectID
            INNER JOIN fc.YearLevelSub           yls  ON tc.YearLevelSubID = yls.ID
            INNER JOIN fc.YearLevelSubStudent    ylss ON yls.ID            = ylss.YearLevelSubID
            INNER JOIN dbo.Student               s    ON ylss.PersonID     = s.PersonID
            -- Expand period
            INNER JOIN fc.TimetableClassInstance tci  ON tc.ID             = tci.TimetableClassID  AND tci.Active = 1
            INNER JOIN fc.TimetablePeriod        tp   ON tci.TimetablePeriodID = tp.ID
            CROSS APPLY fc.PeriodExpand(tci.TimetablePeriodID, tci.Consecutive) tpe
            INNER JOIN fc.TimetableDay           td   ON tpe.TimetableDayID    = td.ID
            $this->baseStaffJoin
            $this->replacementStaffJoin
            $this->baseRoomJoin
            $this->replacementRoomJoin
            $this->timetableJoin

            WHERE s.Code = {$quotedId}
            AND   tci.TimetableBlockInstanceID IS NULL
            AND   tci.TimetablePeriodID IS NOT NULL
            AND   t.Name    = {$this->database->smartQuote($campus->getExtTimetableCode())}
            AND   t.Enabled = 1
        ";
    }
}
