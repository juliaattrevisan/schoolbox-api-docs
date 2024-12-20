<?php

/**
 * PCSchools user connector
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

use Alaress\Database\Connector\IConnector;
use Schoolbox\Database\Factory;
use Schoolbox\External\Connector\Pcschools\QueryHelper;
use Schoolbox\External\Connector\Pcschools\QueryHelper\Freedom;
use Schoolbox\External\Connector\Pcschools\QueryHelper\IQueryHelper;
use Schoolbox\External\Normalise\TupleStack;
use Schoolbox\Resource\Storage;
use Schoolbox\User;

class External_Pcschools_User implements External_UserInterface
{
    const CONTACT_TYPE_FATHER    = 'F';
    const CONTACT_TYPE_MOTHER    = 'M';
    const CONTACT_TYPE_GUARDIAN  = 'G';
    const CONTACT_TYPE_CAREGIVER = 'C';

    const CONTACT_LEVEL_FULL           = 'F';
    const CONTACT_LEVEL_NONE           = 'N';
    const CONTACT_LEVEL_NO_DEMOGRAPHIC = 'D';

    /**
     * Name of stored proc to retrieve a timetable
     *
     * @var string
     */
    protected $timetableStoredProc = 'spSelTimeTable';

    /**
     * @var IConnector
     */
    private $database;

    /**
     * @var \Schoolbox\UserMapper
     */
    private $userMapper;

    /** @var IQueryHelper */
    private $queryHelper;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->database = Factory::getInstanceExternal();
//        // Required, as using @LOGIC_NO
//        $this->getDatabase()->query('SET ANSI_PADDING ON');
//        $this->getDatabase()->query('SET ANSI_WARNINGS ON');
        $this->userMapper = new \Schoolbox\UserMapper();

        /** @noinspection PhpUnhandledExceptionInspection */
        $this->queryHelper = (new QueryHelper\Factory($this->database))->spawn();
    }

    /**
     * @inheritDoc
     */
    public function getStudent(string $externalUserId): array
    {
        [ $sql, $params ] = $this->queryHelper->getGetStudentQuery($externalUserId);

        $rows = $this->getDatabase()->getAll($sql, $params);

        // squash the result down into a single row
        $row = $this->queryHelper->processGetStudentResult($rows);
        if (!empty($row)) {
            $row = $this->getDatabase()->trimRow($row);
            // Get boarder codes and munge student boarder status in
            $boarderCodes = array_map(
                'trim',
                explode(',', $this->getDatabase()->getOne('SELECT BOARDERCODES FROM SYSFILE'))
            );
            $row['StudentBoarder'] = in_array($row['StudentBoarder'], $boarderCodes) ? 'Y' : 'N';
        }

        /* Forcibly obey External_UserInterface
         */

        /* Filter out ' ' and falsey values so we don't show empty alerts.
         * PCSchools seems to send us a ' ' for empty alerts.
         */
        $row['AlertMessage'] = array_unique(array_filter(array_map('trim', $row['AlertMessage'])));
        $row['AlertLegal'] = [];

        return $row;
    }

    /**
     * @inheritDoc
     */
    public function getStudentContactList(array $externalUserId): array
    {
        if (empty($externalUserId)) {
            return [];
        }

        [ $sql, $params ] = $this->queryHelper->getGetStudentContactListQuery(
            array_map([$this->database, 'legacyQuote'], $externalUserId)
        );

        // Split contacts into separate father/mother/guardian/carer records
        // (at this stage, all with the same contact ID)
        $rowset = $this->getDatabase()->getAll($sql, $params);

        // another hack sorry!
        if ($this->queryHelper instanceof Freedom) {
            // data is clean, just send it back
            return $rowset;
        }

        // non-freedom requires hackery to make this all come together properly
        $base   = [
            'StudentContactID'                 => '',
            'StudentContactTitle'              => '',
            'StudentContactPreferred'          => '',
            'StudentContactSurname'            => '',
            'StudentContactDefaultEmail'       => '',
            'StudentContactPhoneActual'        => '',
            'StudentContactSilentPhoneFlag'    => '',
            'StudentContactMobilePhone'        => '',
            'StudentContactOccupPhone'         => '',
            'StudentContactRelation'           => '',
            'StudentContactSpouseID'           => '',
            'StudentContactSpouseTitle'        => '',
            'StudentContactSpousePreferred'    => '',
            'StudentContactSpouseSurname'      => '',
            'StudentContactSpouseMobilePhone'  => '',
            'StudentContactSpouseOccupPhone'   => '',
            'StudentContactSpouseDefaultEmail' => '',
            'StudentContactType'               => '',
            'StudentContactSpouseRelation'     => '',
        ];
        $result = [];
        foreach ($rowset as $row) {
            $result = array_merge(
                $result,
                $this->buildParentRecords($base, $this->getDatabase()->trimRow($row))
            );
        }
        return $result;
    }

    /**
     * @inheritDoc
     */
    public function getStudentContactListExtended(string $yearLevel, string $name, string $boardingStatus, string $form): array
    {
        // Not required for MVP
        return [];
//        $query = "
//            SELECT
//                -- Student fields
//                S.STUDENT# AS StudentID,
//                S.GIVEN_NAME AS StudentGiven1,
//                S.LEGAL_SURNAME AS StudentSurname,
//                S.CURRENT_YEAR AS StudentYearLevel,
//                S.HOUSE AS StudentHouse,
//                S.STATUS AS StudentBoarder,
//                S.HOME_CLASS AS StudentForm,
//                -- Address
//                F.COR_ADDRESS,
//                F.COR_ADDRESS1,
//                F.COR_TOWN,
//                F.COR_POST_CODE,
//                F.STATE_COR,
//                -- Father
//                F.FATHER_TITLE,
//                F.DAD_FIRSTNAME,
//                F.DAD_SURNAME,
//                F.DAD_EMAIL,
//                F.DAD_HOME_PHONE,
//                F.DAD_MOBILE,
//                F.DAD_BUSS_PHONE,
//                F.DAD_STATUS,
//                -- Mother
//                F.MOTHER_TITLE,
//                F.MUM_FIRSTNAME,
//                F.MUM_SURNAME,
//                F.MUM_EMAIL,
//                F.MUM_HOME_PHONE,
//                F.MUM_MOBILE,
//                F.MUM_BUSS_PHONE,
//                F.MUM_STATUS,
//                -- Guardian
//                F.GUARDIAN_TITLE,
//                F.GUARD_FIRSTNAME,
//                F.GUARD_SURNAME,
//                F.GUARD_EMAIL,
//                F.GUARD_HOME_PH,
//                F.GUARD_MOBILE,
//                F.GUARD_BUSS_PH,
//                F.GUARD_STATUS,
//                -- Carer
//                F.CGIVE_TITLE,
//                F.CGIVE_FIRSTNAME,
//                F.CGIVE_SURNAME,
//                F.CGIVE_EMAIL,
//                F.CGIVE_HOME_PH,
//                F.CGIVE_MOBILE,
//                F.CGIVE_BUSS_PH,
//                F.CGIVE_STATUS
//            FROM STUDENT S
//            LEFT JOIN FAMILY AS S_FAMILY ON S.FAMILY_CODE = S_FAMILY.FAMKEY
//        ";
//
//        $whereParts = [];
//        if (strlen($yearlevel)) {
//            $whereParts[] = "S.CURRENT_YEAR = {$this->getDatabase()->smartQuote($yearlevel)}";
//        }
//        if (strlen($name)) {
//            $name         = $this->getDatabase()->quote($name);
//            $whereParts[] = "(S.GIVEN_NAME LIKE '%$name%' OR S.LEGAL_SURNAME LIKE '%$name%')";
//        }
//        if (strlen($form)) {
//            $whereParts[] = "S.HOME_CLASS = {$this->getDatabase()->smartQuote($yearlevel)}";
//        }
    }

    /**
     * @inheritDoc
     */
    public function getParentEmails(array $externalUserIds): array
    {
        if (empty($externalUserIds)) {
            return [];
        }

        [ $query, $params ] = $this->queryHelper->getGetParentEmailsQuery($externalUserIds);

        $whereParts = [];
        foreach ($externalUserIds as $id) {
            $whereParts[] = "S.STUDKEY = {$this->getDatabase()->legacyQuote($id)}";
        }
        $query .= ' AND (' . implode(' OR ', $whereParts) . ')';

        /* PCSchools comes in two flavours: Freedom, and Non Freedom
         * They do things slightly differently
         *
         * The contents of the db determines which flavour is being used
         * Search for IQueryHelper::FREEDOM_DETECT
         *
         * Maintaining this "hack" for now. Thanks for the mystery.
         */
        if ($this->queryHelper instanceof Freedom) {
            $result = $this->getDatabase()->getAll($query, $params);

            return (new TupleStack())->normaliseValues(array_column($result, 'EMAIL'));
        }

        // Munge data in same way we would in getStudentContactList
        $results = [];
        foreach ($this->getDatabase()->getAll($query) as $row) {
            $row = $this->getDatabase()->trimRow($row);

            // For now, I'm going to assume that parent IDs will be suffixed
            if ($row['DAD_LMS'] === self::CONTACT_LEVEL_FULL) {
                $results[] = $row['DAD_EMAIL'];
            }
            if ($row['MUM_LMS'] === self::CONTACT_LEVEL_FULL) {
                $results[] = $row['MUM_EMAIL'];
            }
            if ($row['GUARD_LMS'] === self::CONTACT_LEVEL_FULL) {
                $results[] = $row['GUARD_EMAIL'];
            }
            if ($row['CGIVE_LMS'] === self::CONTACT_LEVEL_FULL) {
                $results[] = $row['CGIVE_EMAIL'];
            }
        }

        return (new TupleStack())->normaliseValues($results);
    }

    /**
     * @inheritDoc
     */
    public function getParent(string $externalUserId): array
    {
        // Non-core promise
        return [];
    }

    /**
     * @inheritDoc
     */
    public function getStaff(string $externalUserId): array
    {
        $row = $this->getDatabase()->getRow("
                SELECT
                    T.TEACHER_CODE AS SchoolStaffCode,
                    '' AS StaffBuilding,
                    T.STAFF_ROOM AS StaffRoom,
                    T.INTERNAL_PH_EXT AS StaffExtension,
                    T.MOBILE AS StaffMobilePhone,
                    T.MOBILE AS StaffDefaultMobilePhone,
                    T_CAMPUS.DESCRIPTION AS StaffCampusDescription,
                    T.STATUS AS StaffCategoryDescription,
                    '' AS StaffDepartment,
                    '' AS StaffVoiceMail,
                    '' AS StaffHouseDescription
                FROM TEACHER T
                LEFT JOIN CODEMAST AS T_CAMPUS ON T.CAMPUS = T_CAMPUS.CODE AND T_CAMPUS.TYPE = 'CAMPUS'
                WHERE T.TEACHER_CODE = {$this->getDatabase()->legacyQuote($externalUserId)}
            ");
        return $row ? $this->database->trimRow($row) : [];
    }

    /**
     * @inheritDoc
     */
    public function getStaffContactListExtended(): array
    {
        // Non-core promise
        return [];
    }

    /**
     * @inheritDoc
     */
    public function getCurrentSemester(string $externalUserId = null): ?string
    {
        return date('m') < 6 ? '1' : '2';
    }

    /**
     * @inheritDoc
     */
    public function getCurrentYear(string $externalUserId = null): ?string
    {
        return date('Y');
    }

    public function findPeriod()
    {
        $query  = "SELECT TIME_PERIOD, SUB_PERIOD FROM dbo.CALENDAR WHERE DATE = :now";
        $period = $this->getDatabase()->getRow($query, [':now' => date('ymd')]);
        if ((int)$period['TIME_PERIOD'] === 0 || (int)$period['SUB_PERIOD'] === 0) {
            $query                 = "SELECT TIME_PERIOD, SUBJECT_PD FROM dbo.SYSFILE";
            $altPeriod             = $this->getDatabase()->getRow($query);
            $period['TIME_PERIOD'] = (int)$period['TIME_PERIOD'] ?: $altPeriod['TIME_PERIOD'];
            $period['SUB_PERIOD']  = (int)$period['SUB_PERIOD'] ?: $altPeriod['SUBJECT_PD'];
        }

        // Annoyingly the `time_period` gets retrieved by the PDO driver without the trailing zeros that are required.
        // The data type in MSSQL is defined as a DECIMAL (4,2) so to fudge around this I'm doing a number format

        $period['TIME_PERIOD'] = number_format((float)$period['TIME_PERIOD'], 2);
        $period['SUB_PERIOD']  = number_format((float)$period['SUB_PERIOD'], 2);

        return $period;
    }

    /**
     * @inheritDoc
     */
    public function getSubjectList(User $user): array
    {
        $externalId = $user->getExternalId();

        if (empty($externalId)) {
            return [];
        }

        if (!$user->getRole()->isStaff() && !$user->getRole()->isStudent()) {
            return [];
        }

        // Create return array
        $classes = [];

        // Retrieve timetabled classes
        $period = $this->findPeriod();

        if ($user->getRole()->isStudent()) {
            $query = "SELECT DISTINCT tf.TIME_PERIOD, tf.YEAR, tf.ABBREV, tf.CLASS, s.[DESC]
            FROM TIMEFILE tf
                        INNER JOIN SUB su ON tf.[YEAR] = su.[YEAR] AND tf.SUBJECT = su.SUBJECT_NO AND tf.CLASS = su.CLASS
                        INNER JOIN SUBJECT s ON tf.SUBJECT = s.NO
            WHERE su.STUDKEY = :externalID_1
              AND tf.TIME_PERIOD = :timePeriod_1
              AND su.PERIOD_ID =  :subPeriod
              AND (tf.[STATUS] = '' OR tf.[STATUS] = 'S' OR (tf.[STATUS] = 'C' AND tf.TEACHER_REP = :externalID_2))

            UNION

                SELECT DISTINCT tf.TIME_PERIOD, tf.YEAR, tf.ABBREV, tf.CLASS, s.[DESC]
                FROM TIMEFILE tf
                            INNER JOIN SUB su ON tf.[YEAR] = su.[YEAR] AND tf.SUBJECT = su.SUBJECT_NO AND tf.CLASS = su.CLASS AND tf.TIME_PERIOD = su.PERIOD_ID
                            INNER JOIN SUBJECT s ON tf.SUBJECT = s.NO
                            INNER JOIN STUDENT st ON su.STUDKEY = st.STUDKEY
                WHERE st.CURRENT_YEAR <> '99'
                  AND (st.STUDKEY = :externalID_3 AND tf.TIME_PERIOD = :timePeriod_2)
            ";
            $params = [
                'externalID_1' => $externalId,
                'timePeriod_1' => $period['TIME_PERIOD'],
                'subPeriod'    => $period['SUB_PERIOD'],
                'externalID_2' => $externalId,
                'externalID_3' => $externalId,
                'timePeriod_2' => $period['TIME_PERIOD'],
            ];
        } else if ($user->getRole()->isStaff()) {
            $query = "SELECT  DISTINCT tf.TIME_PERIOD, tf.YEAR, tf.ABBREV, tf.CLASS, s.[DESC]
                          FROM dbo.TIMEFILE tf
                          INNER JOIN dbo.SUB sub ON (tf.SUBJECT = sub.SUBJECT_NO AND tf.CLASS = sub.CLASS AND tf.[YEAR] = sub.[YEAR])
                          INNER JOIN dbo.SUBJECT s ON s.NO = tf.SUBJECT
                          WHERE  tf.TEACHER = :externalID
                                AND          tf.TIME_PERIOD = :timePeriod
                                AND          sub.PERIOD_ID = :subPeriod
                                AND          (tf.[STATUS] != 'S' OR tf.SUPERDATE = CONVERT(date, GETDATE()))
                        ";
            $params = [
                'externalID' => $externalId,
                'timePeriod' => $period['TIME_PERIOD'],
                'subPeriod'  => $period['SUB_PERIOD'],
            ];
        } else {
            return [];
        }

        $classArray = $this->getDatabase()->getAll($query, $params);

        foreach ($classArray as $class) {
            $code = $class['YEAR'] . $class['ABBREV'] . $class['CLASS'];
            $classes[$period['TIME_PERIOD'] . '/' . $code] = [
                'code'       => $code,
                'name'       => $class['DESC'],
                'classType'  => '',
                'campusCode' => $period['TIME_PERIOD'],
            ];
        }

        return array_values($classes);
    }

    /**
     * @inheritDoc
     */
    public function getEnrolledStudents(string $externalUserId): array
    {
        [ $query, $params ] = $this->queryHelper->getGetEnrolledStudentsQuery($externalUserId);

        return $this->getDatabase()->trimAll($this->getDatabase()->getAll($query, $params));
    }


    /**
     * {@inheritDoc}
     *
     * @param User $user
     * @return Storage\Model|null
     */
    public function getPortrait(User $user): ?Storage\Model
    {
        return $user->getPortrait();
    }


    /**
     * @inheritDoc
     */
    public function determineBoarderType(?string $boarderValue): string
    {
        // Non-core promise
        return '';
//        if (!strlen($boarderValue)) {
//            return '';
//        } else {
//            $boarders = explode(',', $this->getDatabase()->getOne('SELECT BOARDERCODES FROM SYSFILE'));
//            if (in_array($boarderValue, $boarders)) {
//                return 'Boarder';
//            }
//        }
//        return 'Day';
    }

    /**
     * @inheritdoc
     */
    public function getHousemasterEmails(array $externalUserIds): array
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    public function getTutorEmails(array $externalUserIds): array
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    public function getCampusFromTimetable(string $staffID): array
    {
        // Non-core promise
        return [];
    }

    /**
     * @inheritDoc
     */
    public function getStudentForms(): array
    {
        // Non-core promise
        return [];
    }

    /**
     * {@inheritDoc}
     *
     * @return array
     */
    public function getAllYearLevels(): array
    {
        return [];
    }

    // Local methods

    /**
     * @return IConnector
     */
    private function getDatabase()
    {
        return $this->database;
    }

    /**
     * Builds a parent record list from a row from `getStudentContactList()` or
     * other method that returns a list.
     *
     * @param array $base
     * @param array $row
     *
     * @return array
     */
    private function buildParentRecords($base, $row)
    {
        $results = array_filter([
            $this->buildSingleParentRecord($row, self::CONTACT_TYPE_FATHER),
            $this->buildSingleParentRecord($row, self::CONTACT_TYPE_MOTHER),
            $this->buildSingleParentRecord($row, self::CONTACT_TYPE_GUARDIAN),
            $this->buildSingleParentRecord($row, self::CONTACT_TYPE_CAREGIVER),
        ]);
        foreach ($results as &$result) {
            $result = array_merge($base, $result);
        }
        unset($result);
        return $results;
    }

    /**
     * Builds an individual parent record
     *
     * @param array  $row
     * @param string $type
     *
     * @return array
     */
    private function buildSingleParentRecord($row, $type)
    {
        switch ($type) {
            case self::CONTACT_TYPE_FATHER:
                $mainPrefix  = 'DAD';
                $titlePrefix = 'FATHER';
                $relation    = 'Father';
                break;
            case self::CONTACT_TYPE_MOTHER:
                $mainPrefix  = 'MUM';
                $titlePrefix = 'MOTHER';
                $relation    = 'Mother';
                break;
            case self::CONTACT_TYPE_CAREGIVER:
                $mainPrefix  = 'CGIVE';
                $titlePrefix = 'CGIVE';
                $relation    = 'Caregiver';
                break;
            case self::CONTACT_TYPE_GUARDIAN:
                $mainPrefix  = 'GUARD';
                $titlePrefix = 'GUARDIAN';
                $relation    = 'Guardian';
                break;
            default:
                return [];
        }

        // Assumption: if $mainPrefix.'_LMS' isn't full, don't build this record
        if ($row[$mainPrefix . '_LMS'] !== self::CONTACT_LEVEL_FULL) {
            return [];
        }

        // Field mappings
        $fieldMappings = [
            'StudentContactID'           => 'IDENTITY_ID#',
            'StudentContactTitle'        => $titlePrefix . '_TITLE',
            'StudentContactPreferred'    => $mainPrefix . '_FIRSTNAME',
            'StudentContactSurname'      => $mainPrefix . '_SURNAME',
            'StudentContactDefaultEmail' => $mainPrefix . '_EMAIL',
            'StudentContactAddress1'     => 'COR_ADDRESS',
            'StudentContactAddress2'     => 'COR_ADDRESS1',
            'StudentContactSuburb'       => 'COR_TOWN',
            'StudentContactPostCode'     => 'COR_POST_CODE',
            'StudentContactState'        => 'STATE_COR',
            'StudentContactPhoneActual'  => $mainPrefix . '_HOME_PHONE',
            'StudentContactMobilePhone'  => $mainPrefix . '_MOBILE',
            'StudentContactOccupPhone'   => $mainPrefix . '_BUSS_PHONE',
            'StudentContactRelation'     => $mainPrefix . '_STATUS',
            'StudentYearLevel'           => 'StudentYearLevel',
            'StudentCampus'              => 'StudentCampus',
            'StudentGender'              => 'StudentGender',
        ];
        $result = [];
        foreach ($fieldMappings as $to => $from) {
            if (array_key_exists($from, $row)) {
                $result[$to] = $row[$from];
            }
        }

        // Suffix contact ID with type
        $result['StudentContactID'] .= '-' . $type;

        // Build fullname too if I can
        if (array_key_exists($mainPrefix . '_FIRSTNAME', $row) || array_key_exists($mainPrefix . '_SURNAME', $row)) {
            $result['StudentContactLegalFullName']
                = $row[$mainPrefix . '_FIRSTNAME'] . ' ' . $row[$mainPrefix . '_SURNAME'];
        }
        // Build contact relation default value
        if (array_key_exists('StudentContactRelation', $result)) {
            $result['StudentContactRelation'] = $result['StudentContactRelation'] ?: $relation;
        }
        return $result;
    }

    /**
     * {@inheritDoc}
     * @return array
     */
    public function getPhotoRedactionList(): array
    {
        return [];
    }
}
