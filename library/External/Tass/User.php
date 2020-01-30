<?php
/**
 * This file provides access to TASS user info
 *
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

use Schoolbox\Resource\Storage;
use Schoolbox\User;

class External_Tass_User implements External_UserInterface
{

    /**
     * This identifies the CONVERT/CAST format for ISO8601 date
     * yyyy-mm-ddThh:mi:ss.mmm
     */
    protected const FORMAT_ISO8601 = 126;


    /**
     * @var MSSQL
    */
    protected $database;


    /**
     * The TASS company code to use, for queries which require it.
     *
     * @var string
     */
    protected $companyCode;


    /**
     * @var External_Tass_Helper
     */
    protected $helper;


    /**
     * External_Tass_User constructor.
     */
    public function __construct()
    {
        $this->database = MSSQL::getInstance();
        $this->companyCode  = $this->database->smartQuote(Config::get('external_tass_company_code'));
        $this->helper = new External_Tass_Helper();
    }


    /**
     * @inheritDoc
     */
    public function getParent(string $externalUserId): array
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    public function getParentEmails(array $externalUserIds): array
    {
        $emailList = array();
        $db = $this->database;
        $idList    = implode(',', array_map(array($db, 'smartQuote'), $externalUserIds));

        if (empty($idList)) {
            return array();
        }

        $addnum = $this->helper->getAddNum();

        $query = "
            SELECT pa.par_code, pa.e_mail, pa.add_num
            FROM paraddress pa
            INNER JOIN parent p ON pa.par_code=p.par_code AND pa.cmpy_code=p.cmpy_code
            INNER JOIN student s ON p.par_code=s.par_code AND p.cmpy_code=s.cmpy_code
            WHERE
                  pa.e_mail IS NOT NULL AND
                  s.stud_code IN ($idList) AND
                  pa.cmpy_code = {$this->companyCode} AND
                  pa.add_num IN ($addnum)
        ";

        /* If Tass Comm rules are enabled then locate the guardian relationship
         * from there as well
         */
        if (Config::get('external_tass_commrules')) {

            $query .= "
                UNION
                SELECT DISTINCT pa.par_code, pa.e_mail, pa.add_num
                FROM paraddress pa
                INNER JOIN studcommrules scr ON pa.par_code=scr.par_code
                    AND pa.cmpy_code = scr.cmpy_code
                    AND scr.cmpy_code = {$this->companyCode}
                    AND pa.add_num = scr.add_num
            ";

            $typecode = $this->helper->getTypeCode();

            if (!empty($typecode)) {
                $query .= "AND scr.commtype_code IN ({$typecode}) ";
            }

            $query .= "
                INNER JOIN student s ON scr.stud_code=s.stud_code AND scr.cmpy_code=s.cmpy_code
                WHERE
                    pa.e_mail IS NOT NULL AND
                    s.stud_code IN ($idList) AND
                    pa.cmpy_code = {$this->companyCode} AND
                    pa.add_num IN ($addnum)
            ";
        }

        $statement = $this->database->query($query);
        /** @noinspection PhpAssignmentInConditionInspection */
        while ($row = $statement->fetch()) {
            if (false !== strpos($row['e_mail'], ';')) {
                $i = 0;
                foreach(explode(';',$row['e_mail']) as $email) {
                    $emailList[trim($row['par_code']).'-'.trim($row['add_num'].'-'.$i++)] = $email;
                }
            }
            else {
                $emailList[trim($row['par_code']).'-'.trim($row['add_num'])] = trim($row['e_mail']);
            }
        }
        return $emailList;
    }


    /**
     * @inheritDoc
     */
    public function getStudent(string $externalUserId): array
    {
        $query = "
        SELECT
            s.stud_code AS StudentID,
            s.preferred_name AS StudentGiven1,
            s.surname AS StudentSurname,
            s.sex AS StudentGender,
            CONVERT(nvarchar(30), s.dob, ".self::FORMAT_ISO8601." )  AS StudentBirthDate,
            '' AS StudentTutorName,
            '' AS StudentHeadOfHouse,
            '' AS HeadOfHouseID,
            t.tch_name AS StudentFormStaffName,
            '' AS StudentTutorDescription,
            '' AS TeacherFormID,
            s.form_cls AS StudentForm,
            t.tch_code AS TeacherTutorID,
            h.hse_name AS StudentHouseDescription,
            s.house AS StudentHouse,
            s.year_grp AS StudentYearLevel,
            '' AS StudentYearLevelCoordinator,
            '' AS YearLevelCoordID,
            '' AS StudentEmergencyName,
            '' AS StudentEmergencyPhone,
            '' AS StudentEmergencyPhoneAlt,
            '' AS StudentEmergency2Name,
            '' AS StudentEmergency2Phone,
            '' AS StudentEmergency2PhoneAlt,
            '' AS StudentEmergency3Name,
            '' AS StudentEmergency3Phone,
            '' AS StudentEmergency3PhoneAlt,
            '' AS StudentMedicalAlert,
            '' AS StudentMobilePhone,
            '' AS StudentSpecialNeedsFlag,
            '' AS EnglishSecondFlag,
            '' AS StudentStudiesCode,
            '' AS AlertMessage,
            '' AS CourtOrderType,
            boarder AS StudentBoarder,
            '' AS Comment,
            '' AS StudentEnrolmentDate,
            '' AS StudentLeavingDate,
            '' AS StudentIBFlag,
            0 AS countIndividualEducationPlans
        FROM student AS s
        LEFT JOIN house AS h ON s.house=h.hse_code AND s.cmpy_code=h.cmpy_code
        -- LEFT JOIN parent AS p ON s.par_code=p.par_code AND s.cmpy_code=p.cmpy_code
        LEFT JOIN teacher AS t ON s.pctut_grp=t.pctut_grp AND t.curr_flg = 'Y' AND s.cmpy_code=t.cmpy_code
        WHERE stud_code = '$externalUserId'
        ";
        $student = $this->database->trimRow($this->database->getRow($query));
        if ($student['StudentBirthDate'] && $birthDate = strtotime($student['StudentBirthDate'])) {
            $student['StudentBirthDate'] = $birthDate;
        } else {
            $student['StudentBirthDate'] = false;
        }
        return $student;
    }


    /**
     * @inheritDoc
     */
    public function getStaff(string $externalUserId): array
    {
        return [];
//        $query = "
//            SELECT *
//            FROM tel_vw_telemf
//        ";
    }


    /**
     * @inheritDoc
     */
    public function getStudentContactList(array $externalUserId): array
    {
        if (empty($externalUserId)) {
            return [];
        }

        $charIndexQuery = [];

        $ids = implode(',', array_map(static function ($val) {
            return sprintf("'%s'", $val);
            }, $externalUserId)
        );

        foreach ($externalUserId as $externalId) {
            $charIndexQuery[] = "(CHARINDEX(','+ '$externalId' +',', ','+RTRIM(sf.stud_codes)+',') > 0)";
        }

        $charIndexQueryString = implode(' OR ', $charIndexQuery);

        /* students profile should provide all parents, but only show contact info if not in a split family
         */
        $addnum_mother = $this->helper->getAddNumMother();
        $addnum_father = $this->helper->getAddNumFather();
        $query = "
            SELECT DISTINCT
                'P' + RTRIM(parent.par_code)
                                  AS StudentContactID,
                pa.add_num          AS StudentContactAddressNumber,
                parent.f_name       AS StudentContactPreferred,
                0                   AS StudentContactSilentPhoneFlag,
                pa.home_phone       AS StudentContactPhoneActual,
                pa.bus_phone        AS StudentContactOccupPhone,
                pa.e_mail           AS StudentContactDefaultEmail,
                ''                  AS StudentContactSpouseID,
                COALESCE(RTRIM(pa.mobile1), RTRIM(pa.mobile2))
                                    AS StudentContactMobilePhone,
                pa.mobile2          AS StudentContactSpouseMobilePhone
            FROM student
            JOIN parent
                ON student.par_code = parent.par_code
                AND student.cmpy_code = parent.cmpy_code
            INNER JOIN family
                ON parent.par_code = family.entity_code
                AND family.entity_gender = 'M'
            INNER JOIN persondefs
                ON family.person_num = persondefs.person_num
                AND persondefs.deceased_flg = 'N' OR persondefs.deceased_flg IS NULL
            JOIN paraddress AS pa
                ON parent.par_code = pa.par_code
                AND pa.cmpy_code = parent.cmpy_code
                AND pa.add_num IN ($addnum_father,'1')
            WHERE stud_code IN ($ids)

            UNION

            SELECT DISTINCT
                '{$this->helper->getSpousePrefix()}' + RTRIM(parent.par_code)
                                    AS StudentContactID,
                pa.add_num          AS StudentContactAddressNumber,
                parent.m_name       AS StudentContactPreferred,
                0                   AS StudentContactSilentPhoneFlag,
                pa.home_phone       AS StudentContactPhoneActual,
                pa.bus_phone        AS StudentContactOccupPhone,
                pa.e_mail           AS StudentContactDefaultEmail,
                ''                  AS StudentContactSpouseID,
                COALESCE(RTRIM(pa.mobile1), RTRIM(pa.mobile2))
                                    AS StudentContactMobilePhone,
                pa.mobile2          AS StudentContactSpouseMobilePhone
            FROM student
            JOIN parent
                ON student.par_code = parent.par_code
                AND student.cmpy_code = parent.cmpy_code
            INNER JOIN family
                ON parent.par_code = family.entity_code
                AND family.entity_gender = 'F'
            INNER JOIN persondefs
                ON family.person_num = persondefs.person_num
                AND persondefs.deceased_flg = 'N' OR persondefs.deceased_flg IS NULL
            JOIN paraddress AS pa
                ON parent.par_code = pa.par_code
                AND pa.cmpy_code = parent.cmpy_code
                AND pa.add_num IN ($addnum_mother,'1')
            WHERE stud_code IN ($ids)

            UNION

            SELECT DISTINCT
                'P' + RTRIM(parent.par_code) + RTRIM(sf.sfa_num)
                                    AS StudentContactID,
                pa.add_num          AS StudentContactAddressNumber,
                parent.f_name       AS StudentContactPreferred,
                0                   AS StudentContactSilentPhoneFlag,
                pa.home_phone       AS StudentContactPhoneActual,
                pa.bus_phone        AS StudentContactOccupPhone,
                pa.e_mail           AS StudentContactDefaultEmail,
                ''                  AS StudentContactSpouseID,
                COALESCE(RTRIM(pa.mobile1), RTRIM(pa.mobile2))
                                    AS StudentContactMobilePhone,
                pa.mobile2          AS StudentContactSpouseMobilePhone

            FROM pia_sfaccess AS sf
            JOIN parent
                ON parent.par_code = sf.par_code
                AND parent.cmpy_code = sf.cmpy_code
            INNER JOIN family
                ON parent.par_code = family.entity_code
                AND family.entity_gender = 'M'
            INNER JOIN persondefs
                ON family.person_num = persondefs.person_num
                AND persondefs.deceased_flg = 'N' OR persondefs.deceased_flg IS NULL
            JOIN paraddress AS pa
                ON parent.par_code = pa.par_code
                AND pa.cmpy_code = parent.cmpy_code
                AND pa.add_num IN ($addnum_father,'1')
                AND (CHARINDEX(','+RTRIM(pa.add_num)+',', ','+RTRIM(sf.add_nums)+',') > 0)
            WHERE $charIndexQueryString

            UNION

            SELECT DISTINCT
                '{$this->helper->getSpousePrefix()}' + RTRIM(parent.par_code) + RTRIM(sf.sfa_num)
                                    AS StudentContactID,
                pa.add_num          AS StudentContactAddressNumber,
                parent.m_name       AS StudentContactPreferred,
                0                   AS StudentContactSilentPhoneFlag,
                pa.home_phone       AS StudentContactPhoneActual,
                pa.bus_phone        AS StudentContactOccupPhone,
                pa.e_mail           AS StudentContactDefaultEmail,
                ''                  AS StudentContactSpouseID,
                COALESCE(RTRIM(pa.mobile1), RTRIM(pa.mobile2))
                                    AS StudentContactMobilePhone,
                pa.mobile2          AS StudentContactSpouseMobilePhone

            FROM pia_sfaccess AS sf
            JOIN parent
                ON parent.par_code = sf.par_code
                AND parent.cmpy_code = sf.cmpy_code
            INNER JOIN family
                ON parent.par_code = family.entity_code
                AND family.entity_gender = 'F'
            INNER JOIN persondefs
                ON family.person_num = persondefs.person_num
                AND persondefs.deceased_flg = 'N' OR persondefs.deceased_flg IS NULL
            JOIN paraddress AS pa
                ON parent.par_code = pa.par_code
                AND pa.cmpy_code = parent.cmpy_code
                AND pa.add_num IN ($addnum_mother,'1')
                AND (CHARINDEX(','+RTRIM(pa.add_num)+',', ','+RTRIM(sf.add_nums)+',') > 0)
            WHERE $charIndexQueryString
        ";

        $parents = $this->database->trimAll($this->database->getAll($query));

        foreach ($parents as $key => $parent) {
            /* Fallback to add_num 1 when there is no add_num mother or add_num father
             */
            if ($parent['StudentContactAddressNumber'] == '1') {
                foreach ($parents as $key2 => $parent2) {
                    if ($parent['StudentContactID'] == $parent2['StudentContactID'] && ($this->database->smartQuote(trim($parent2['StudentContactAddressNumber'])) == $addnum_mother or $this->database->smartQuote(trim($parent2['StudentContactAddressNumber'])) == $addnum_father)) {
                        unset($parents[$key]);
                    }
                }
            }
        }

        return $parents;
    }


    /**
     * @inheritDoc
     */
    public function getCurrentSemester(string $externalUserId = null): ?string
    {
        $query = "
            SELECT curr_sem
            FROM ttparms
            WHERE cmpy_code = {$this->database->smartQuote($this->companyCode)}
        ";
        $currentSemester = $this->database->getOne($query);
        return $currentSemester === null ? null : (string)$currentSemester;
    }


    /**
     * @inheritDoc
     */
    public function getCurrentYear(string $externalUserId = null): ?string
    {
        $query = "
            SELECT curr_year
            FROM ttparms
            WHERE cmpy_code = {$this->database->smartQuote($this->companyCode)}
        ";
        $currentYear = $this->database->getOne($query);
        return $currentYear === null ? null : (string)$currentYear;
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
        if ($user->getRole()->isStudent()) {
            $sql = "SELECT class, studsub.cmpy_code, semester, studsub.sub_code, ISNULL(student.year_grp,subtab.year_grp) as year_grp, sub_desc, sub_type
                    FROM studsub
                    JOIN student ON studsub.stud_code = student.stud_code AND studsub.cmpy_code=student.cmpy_code
                    JOIN subtab ON subtab.sub_code=studsub.sub_code AND subtab.cmpy_code=studsub.cmpy_code
                    WHERE studsub.stud_code = '$externalId'
                    AND semester = '{$this->database->quote($this->getCurrentSemester($externalId))}'
                    AND yr_study = '{$this->database->quote($this->getCurrentYear($externalId))}'
                    ORDER BY studsub.sub_code ASC, studsub.class ASC
            ";
        } else if ($user->getRole()->isStaff()) {
            $sql = "SELECT class, tchsub.cmpy_code, semester, tchsub.sub_code, year_num, ISNULL(tchsub.year_grp,subtab.year_grp) as year_grp, sub_desc, sub_type
                    FROM tchsub
                    JOIN subtab ON subtab.sub_code=tchsub.sub_code AND subtab.cmpy_code=tchsub.cmpy_code
                    WHERE tch_code = '$externalId'
                    AND semester = '{$this->database->quote($this->getCurrentSemester($externalId))}'
                    AND year_num = '{$this->database->quote($this->getCurrentYear($externalId))}'
                    ORDER BY tchsub.sub_code ASC, tchsub.class ASC
            ";
        } else {
            return [];
        }

        $statement = $this->database->query($sql);
        $subjectList = array();
        while ($subject = $this->database->trimRow($statement->fetch())) {
            if (Config::get('external_tass_year_in_class_code') == '0') {
                $subject['year_grp'] = '';
            }
            $subjectList[] = [
                'code'       => $subject['year_grp'] . '-' . $subject['sub_code'] . '-' . $subject['class'],
                'name'       => trim($subject['sub_desc']),
                'classType'  => '',
                'campusCode' => '',
            ];
        }

        if (is_array($subjectList)) {
            return $subjectList;
        } else {
            return [];
        }
    }

    /**
    * Get enrolled students
    *
    * @param $externalUserId
    * @return array
    */
    public function getEnrolledStudents(string $externalUserId): array
    {
        /* If the TassID is 8 characters long then it is assumed to be
         * someone in the split-parent scenario.
         */

        // id has to have a [P]arent prefix (or [S]pouse)
        if (!preg_match("#(?:P|{$this->helper->getSpousePrefix()})[0-9A-Za-z]+#s", $externalUserId)) {
            return array();
        }

        // remove the P|S prefix
        $id = substr($externalUserId, 1);

        $commrules = Config::get('external_tass_commrules');
        $addnum = $this->helper->getAddNum();
        $typecode = $this->helper->getTypeCode();

        /* If Tass Comm rules are enabled then locate the guardian relationship from there as well */

        $splitParentLength = Config::get('external_tass_parent_id_length') + 1;
        if (strlen($id) == $splitParentLength) {
            // This is a split family parent, deal with them differently
            $parentCode = substr($id, 0, $splitParentLength-1);
            $parentNum = $id[$splitParentLength-1];

            $query = "
                SELECT DISTINCT
                    RTRIM(sf.stud_codes) AS RelatedID
                FROM parent AS p
                INNER JOIN paraddress as pa
                    ON p.par_code = pa.par_code AND p.cmpy_code = pa.cmpy_code
                LEFT JOIN pia_sfaccess AS sf
                    ON p.par_code = sf.par_code
                    AND (CHARINDEX(','+RTRIM(pa.add_num)+',', ','+RTRIM(sf.add_nums)+',') > 0)
                    AND p.cmpy_code = sf.cmpy_code
                LEFT JOIN student as s
                    ON p.par_code = s.par_code
                    AND p.cmpy_code = s.cmpy_code
                    AND (s.dol IS NULL OR s.dol >= getdate())
                WHERE sf.par_code = {$this->database->smartQuote($parentCode)}
                AND   sf.sfa_num  = {$this->database->smartQuote($parentNum)}
            ";

            if ($commrules) {
                $query .= "
                    UNION

                    SELECT DISTINCT
                        RTRIM(scr.stud_code) AS RelatedID
                    FROM studcommrules as scr
                    WHERE cmpy_code = {$this->companyCode}
                    AND par_code = {$this->database->smartQuote($parentCode)}
                    AND add_num IN({$addnum})
                ";

                if (!empty($typecode)) {
                    $query .= "AND commtype_code IN ({$typecode}) ";
                }
            }

            $return = array();
            $result = $this->database->getAll($query);

            // parse the array, because this data is stored in a funky way
            if (count($result) && is_array($result)) {
                foreach ($result as $row) {
                    // data is stored in a single field, comma separated
                    $list = explode(',', $row['RelatedID']);
                    foreach ($list as $childID) {
                        $return[trim($childID)] = array('RelatedID' => trim($childID));
                    }
                }
            }
        } else {
            $query = "
                SELECT DISTINCT
                    RTRIM(s.stud_code) AS RelatedID
                FROM student s
                LEFT JOIN parent p
                    ON  p.par_code = s.par_code
                    AND p.cmpy_code = s.cmpy_code
                WHERE s.par_code = {$this->database->smartQuote($id)}
                AND (s.dol IS NULL OR s.dol >= getdate())
            ";
            if ($commrules) {
                $query .= "
                    UNION

                    SELECT DISTINCT
                        RTRIM(scr.stud_code) AS RelatedID
                    FROM studcommrules as scr
                    WHERE cmpy_code = {$this->companyCode}
                    AND par_code = {$this->database->smartQuote($id)}
                    AND add_num IN ({$addnum})
                ";

                if (!empty($typecode)) {
                    $query .= "AND commtype_code IN ({$typecode}) ";
                }
            }

            $return = $this->database->getAll($query);
        }

        return $return;
    }


    /**
    * @inheritDoc
    */
    public function determineBoarderType(?string $boarderValue): string
    {
        $boarders = array('Y');

        if (in_array($boarderValue, $boarders, true)) {
            return 'Boarder';
        }

        return 'Day';
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

    public function getStudentContactListExtended(string $yearLevel, string $name, string $boardingStatus, string $form): array
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    public function getCampusFromTimetable(string $staffID): array
    {
        return array();
    }

    /**
     * @inheritDoc
     */
    public function getStaffContactListExtended(): array
    {
        // Exclude selected position text

        $staffCategory = explode(',',\Config::get('tass_staff_dir_position_text_exclude'));

        $wherePositionText = '';

        if (is_array($staffCategory) && $staffCategory[0] != '')
        {
            $wherePositionText = 'AND (';
            $db = $this->database;

            $count = 0;

            foreach ($staffCategory as $category) {
                if ($count > 0)
                    $wherePositionText .= " AND ";

                $wherePositionText .= " dbo.tel_vw_telemf.position_text NOT LIKE '" . $db->quote($category) . "'";
                $count++;
            }

            $wherePositionText .= ') ';
        }

        // Exclude selected position title

        $staffCategory = explode(',',\Config::get('tass_staff_dir_position_title_exclude'));

        $wherePositionTitle = '';

        if (is_array($staffCategory) && $staffCategory[0] != '')
        {
            $wherePositionTitle = " AND (";

            $db = $this->database;

            $count = 0;

            foreach ($staffCategory as $category) {
                if ($count > 0)
                    $wherePositionTitle .= " AND ";

                $wherePositionTitle .= " dbo.tel_vw_telemf.position_title NOT LIKE '" . $db->quote($category) . "'";
                $count++;
            }

            $wherePositionTitle .= ') ';
        }

        // Exclude selected award codes

        $staffAward = explode(',',\Config::get('tass_staff_dir_award_exclude'));

        $whereAward = '';

        if (is_array($staffAward) && $staffAward[0] != '')
        {
            $whereAward = " AND (";
            $db = $this->database;

            $count = 0;

            foreach ($staffAward as $award) {
                if ($count > 0)
                    $whereAward .= " AND ";

                $whereAward .= " dbo.tel_vw_telemf.awd_code NOT LIKE '" . $db->quote($award) . "'";
                $count++;
            }

            $whereAward .= ') ';
        }

        // Custom category code descriptions

        $awardMappings = explode(',',\Config::get('tass_staff_dir_award_mappings'));

        if (is_array($awardMappings) && $awardMappings[0] != '' && count($awardMappings) % 2 == 0)
        {
            $caseAwardMappings = 'CASE (dbo.tel_vw_telemf.awd_code) ';
            $db = $this->database;

            $countAwardMappers = count($awardMappings);
            for ($i = 0; $i < $countAwardMappers; $i += 2)
                $caseAwardMappings .= " WHEN '" . $db->quote($awardMappings[$i]) . "' THEN '" . $db->quote($awardMappings[$i+1]) . "'";

            $caseAwardMappings .= " ELSE '' END AS StaffCategoryType";
        }
        else {

            // No mappings specified, just display the code

            $caseAwardMappings = "dbo.tel_vw_telemf.awd_code AS StaffCategoryType";
        }

        $database = $this->database;

        $query = "
            SELECT TOP (100) PERCENT
                CASE WHEN tch_code IS NOT NULL THEN tch_code ELSE dbo.tel_vw_telemf.emp_code END AS StaffID,
                dbo.tel_vw_telemf.salutation_flag AS StaffTitle,
                dbo.tel_vw_telemf.prefer_name_text AS StaffPreferredName,
                dbo.tel_vw_telemf.surname_text AS StaffSurname,
                dbo.tel_vw_telemf.phone_w_text AS StaffOccupPhone,
                dbo.tel_vw_telemf.school_email AS StaffOccupEmail,
                '' AS StaffExtension,
                '' AS StaffCampusDescription,
                {$caseAwardMappings},
                '' AS StaffDepartment,
                dbo.tel_vw_telemf.position_title AS JobPositionDescription,
                '' AS StaffJobPositionStartDate,
                '' AS StaffJobPositionEndDate
            FROM dbo.tel_vw_telemf
            LEFT OUTER JOIN dbo.teacher ON dbo.tel_vw_telemf.emp_code = dbo.teacher.emp_code
            WHERE
              (dbo.tel_vw_telemf.cust_code = {$this->companyCode}) AND
              (dbo.tel_vw_telemf.term_date IS NULL)
              {$wherePositionText}
              {$wherePositionTitle}
              {$whereAward}
            ORDER BY StaffSurname, StaffPreferredName
        ";

        return $database->trimAll($database->getAll($query));
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
    public function getStudentForms(): array
    {
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
}
