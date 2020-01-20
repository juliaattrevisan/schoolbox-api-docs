<?php
/**
 * Implementation of Edumate User Connection
 * For schema information, see http://wiki.schoolbox.com.au/index.php/Interface_Calls
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

use Alaress\Database\Predicate\Builder as PredicateBuilder;
use Schoolbox\Resource\Storage;
use Schoolbox\User;

class External_Edumate_User extends External_Edumate_Connection implements External_UserInterface
{
    /**
     * @inheritdoc
     */
    public function getParent(string $externalUserId): array
    {
        // prepare

        $caseSensitiveFields = array('ID', 'Email', 'MobilePhone', 'SpouseID', 'SpouseMobilePhone', 'DirectoryIncludeFlag');

        $stmt = $this->getConnection()->prepareQuery('
            SELECT CARER_NUMBER AS ID, FULL_NAME, COMBINED_EMAIL_ADDRESS AS EMAIL, MOBILE AS MOBILEPHONE, SPOUSE_CARER_NUMBER AS SPOUSEID, SPOUSE_FULL_NAME,
              SPOUSE_MOBILE AS SPOUSEMOBILEPHONE, 1 AS DIRECTORYINCLUDEFLAG
            FROM SCHOOLBOX_PARENTS
            WHERE CARER_NUMBER = :id
        ');

        // retrieve
        $stmt->execute(array(
            'id' => $externalUserId
        ));

        $parent = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!is_array($parent)) {
            return [];
        }

        // manipulate
        // split out [spouse_]full_name fields and try to guess name pieces
        // ** ASSIGNMENT (=) HERE INTENTIONAL, NOT COMPARISON (==)
        if ($selfName = $this->guessNamePartsFromFull($parent['FULL_NAME'])) {
            $parent = array_merge($parent, $selfName);
            unset($parent['FULL_NAME']);
        }
        if ($spouseName = $this->guessNamePartsFromFull($parent['SPOUSE_FULL_NAME'], 'Spouse')) {
            $parent = array_merge($parent, $spouseName);
            unset($parent['SPOUSE_FULL_NAME']);
        }

        return $this->normaliseFields($parent, $caseSensitiveFields);
    }

    /**
     * @inheritdoc
     */
    protected function guessNamePartsFromFull($fullName, $prefix = '')
    {
        // strip out any consecutive or trailing spaces
        $fullName = trim(preg_replace("/\s\s+/", ' ', $fullName));

        if (!strlen($fullName)) {
            return false;
        }

        $parts = explode(' ', $fullName);
        switch (count($parts)) {
            case 2: // one space, take as 'Preferred Surname'
                return array(
                    $prefix . 'Preferred' => $parts[0],
                    $prefix . 'Surname' => $parts[1],
                );
            case 3: // 2 spaces, take as 'Title Preferred Surname'
                return array(
                    $prefix . 'Title' => $parts[0],
                    $prefix . 'Preferred' => $parts[1],
                    $prefix . 'Surname' => $parts[2],
                );
            default: // anything else, can't work with it, return failure
                return false;
        }
    }

    /**
     * @inheritdoc
     */
    public function getStudent(string $externalUserId): array
    {
        // Fields present in Synergetic but not Edumate
        //
        // StudentTutorName
        // HeadOfHouseID
        // StudentTutorDescription
        // TeacherFormID
        // TeacherTutorID
        // StudentHouseDescription
        // StudentYearLevelCoordinator
        // YearLevelCoordID
        // StudentEmergencyPhoneAlt
        // StudentMedicalAlert
        // StudentSpecialNeedsFlag
        // EnglishSecondFlag
        // AlertMessage
        // StudentBoarder
        // StudentMobilePhone

        // Use this for mapping DB2INDICES to NormalIndices

        $caseSensitiveFields = array(
            'StudentID', 'StudentGiven1', 'StudentSurname', 'StudentBirthDate',
            'StudentHouseDescription', 'StudentYearLevel', 'StudentTutor', 'StudentHeadOfHouse', 'StudentForm',
            'StudentFormStaffName', 'StudentEmergencyName', 'StudentEmergencyPhone', 'StudentMobilePhone', 'EnglishSecondFlag'
        );

        $stmt = $this->getConnection()->prepareQuery("
            SELECT S.STUDENT_NUMBER AS STUDENTID, S.FIRSTNAME AS STUDENTGIVEN1, S.SURNAME AS STUDENTSURNAME,
              S.BIRTHDATE AS STUDENTBIRTHDATE, S.HOUSE AS STUDENTHOUSEDESCRIPTION, S.YEAR_LEVEL AS STUDENTYEARLEVEL,
              S.TUTOR_NAME AS STUDENTFORMSTAFFNAME, S.HEAD_OF_HOUSE AS STUDENTHEADOFHOUSE, S.FORM_NAME AS STUDENTFORM,
              S.FORM_STAFF_NAME AS STUDENTTUTOR, P.FULL_NAME AS STUDENTEMERGENCYNAME,
              '' AS STUDENTGENDER,
              P.MOBILE AS STUDENTEMERGENCYPHONE,
              '' AS COMMENT,
              '' AS COURTORDERTYPE,
              '' AS STUDENTENROLMENTDATE,
              '' AS STUDENTLEAVINGDATE,
              '' AS STUDENTIBFLAG,
              '' AS STUDENTSTUDIESCODE,
              '' AS ENGLISHSECONDFLAG,
              0 AS COUNTINDIVIDUALEDUCATIONPLANS
            FROM SCHOOLBOX_STUDENTS S
            LEFT JOIN SCHOOLBOX_PARENT_STUDENT PS ON S.STUDENT_NUMBER = PS.STUDENT_NUMBER
            LEFT JOIN SCHOOLBOX_PARENTS P ON PS.CARER_NUMBER = P.CARER_NUMBER
            WHERE S.STUDENT_NUMBER = :id
        ");

        // retrieve
        $stmt->execute(array(
            'id' => $externalUserId
        ));

        // manipulate

        // use the first record to plaster the data into an array, or it will be returned false if nonexistent
        $student = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$student) {
            return [];
        }

        // turn 'Year 08' into 8
        $student['STUDENTYEARLEVEL'] = preg_replace('/[^0-9]/', '', $student['STUDENTYEARLEVEL']) + 0;

        // sometimes Edumate will return blank emergency contact on the first record...
        if (strlen($student['STUDENTEMERGENCYNAME'])) {
            $start = 2;
        } else {
            $start = 1;
            unset($student['STUDENTEMERGENCYNAME']);
            unset($student['STUDENTEMERGENCYPHONE']);
        }

        // parse rows into student - it will be replicated by being joined to parent rows, so need to flatten
        // populate emergency contacts by stacking
        for ($i = $start; $i < 4, $row = $stmt->fetch(PDO::FETCH_ASSOC); ++$i) {

            // logic to compensate for Edumate returning blank emergency contact
            if (isset($row['STUDENTEMERGENCYNAME']) && !strlen($row['STUDENTEMERGENCYNAME'])) {
                --$i;
                continue;
            }

            if ($i == 1) {
                $i = '';
            }

            $student['StudentEmergency' . $i . 'Name'] = $row['STUDENTEMERGENCYNAME'];
            $student['StudentEmergency' . $i . 'Phone'] = $row['STUDENTEMERGENCYPHONE'];

            if ($i == '') {
                $i = 1;
            }
        }

        // normalise the birth date: change YYYY-mm-dd to timestamp
        $student['STUDENTBIRTHDATE'] = strtotime($student['STUDENTBIRTHDATE']);

        return $this->normaliseFields($student, $caseSensitiveFields);
    }

    /**
     * @inheritdoc
     */
    public function getStudentContactList(array $externalUserId): array
    {
        if (empty($externalUserId)) {
            return [];
        }

        $predicate = PredicateBuilder::getArrayBindings($externalUserId, 'studentIds');

        $caseSensitiveFields = array(
            'StudentContactID',
            'StudentContactTitle',
            'StudentContactPreferred',
            'StudentContactSurname',
            'StudentContactDefaultEmail',
            'StudentContactPhoneActual',
            'StudentContactSilentPhoneFlag',
            'StudentContactMobilePhone',
            'StudentContactOccupPhone',
            'StudentContactSpouseID',
            'StudentContactSpouseTitle',
            'StudentContactSpousePreferred',
            'StudentContactSpouseSurname',
            'StudentContactSpouseMobilePhone',
            'StudentContactSpouseOccupPhone',
            'StudentContactSpouseDefaultEmail',
            'StudentContactType'
        );

        $stmt = $this->getConnection()->prepareQuery("
            SELECT P.CARER_NUMBER AS STUDENTCONTACTID, P.FULL_NAME, P.COMBINED_EMAIL_ADDRESS AS STUDENTCONTACTDEFAULTEMAIL,
              P.MOBILE AS STUDENTCONTACTMOBILEPHONE, P.SPOUSE_CARER_NUMBER AS STUDENTCONTACTSPOUSEID, P.SPOUSE_FULL_NAME,
              P.SPOUSE_MOBILE AS STUDENTCONTACTSPOUSEMOBILEPHONE, P.COMBINED_EMAIL_ADDRESS AS STUDENTCONTACTSPOUSEDEFAULTEMAIL
            FROM SCHOOLBOX_PARENTS P
            INNER JOIN SCHOOLBOX_PARENT_STUDENT PS ON P.CARER_NUMBER = PS.CARER_NUMBER
            WHERE PS.STUDENT_NUMBER IN ({$predicate['placeholders']})
        ");

        $stmt->execute($predicate['bindings']);

        $parents = array();
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            // split out [spouse_]full_name fields and try to guess name pieces
            // ** ASSIGNMENT (=) HERE INTENTIONAL, NOT COMPARISON (==)
            if ($selfName = $this->guessNamePartsFromFull($row['FULL_NAME'], 'StudentContact')) {
                $row = array_merge($row, $selfName);
                unset($row['FULL_NAME']);
            }

            if ($spouseName = $this->guessNamePartsFromFull($row['SPOUSE_FULL_NAME'], 'StudentContactSpouse')) {
                $row = array_merge($row, $spouseName);
                unset($row['SPOUSE_FULL_NAME']);
            }
            $parents[] = $this->normaliseFields($row, $caseSensitiveFields);
        }

        return $parents;
    }

    /**
     * @inheritdoc
     */
    public function getCurrentSemester(string $externalUserId = null): ?string
    {
        $stmt = $this->getConnection()->prepareQuery("
            SELECT REPORT_PERIOD
            FROM SCHOOLBOX_REPORT_PERIODS
            WHERE START_DATE < CURRENT_TIMESTAMP
            AND END_DATE > CURRENT_TIMESTAMP
        ");

        $stmt->execute();

        $semester = $stmt->fetchColumn();

        if ($semester) {
            return $semester;
        }

        return null;
    }

    /**
     * @inheritdoc
     */
    public function getCurrentYear(string $externalUserId = null): ?string
    {
        return date('Y');
    }

    /**
     * @inheritdoc
     */
    public function getSubjectList(User $user): array
    {
        $externalId = $user->getExternalId();

        if (empty($externalId)) {
            return [];
        }

        if ($user->getRole()->isStaff()) {
            $sql = "
                SELECT C.CLASS_CODE AS CODE, CLASS AS NAME, TC.CLASS_TYPE AS CLASSTYPE
                FROM SCHOOLBOX_TEACHERCLASS TC
                LEFT JOIN SCHOOLBOX_CLASSES C ON TC.CLASS_CODE = C.CLASS_CODE
                WHERE TC.STAFF_NUMBER = :id
                ORDER BY CLASS_TYPE, NAME
            ";
        } else {
            if ($user->getRole()->isStudent()) {
                $sql = "
                SELECT C.CLASS_CODE AS CODE, CLASS AS NAME, CLASS_TYPE AS CLASSTYPE
                FROM SCHOOLBOX_STUDENTCLASS SC
                LEFT JOIN SCHOOLBOX_CLASSES C ON SC.CLASS_CODE = C.CLASS_CODE
                WHERE SC.STUDENT_NUMBER = :id
                ORDER BY CLASS_TYPE, NAME
            ";
            } else {
                return [];
            }
        }

        $stmt = $this->getConnection()->prepareQuery($sql);

        $stmt->execute(array(
            'id' => $externalId
        ));

        $classes = array();

        while ($class = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $classes[$class['CODE']] = array(
                'code' => $class['CODE'],
                'name' => $class['NAME'],
                'classType' => $class['CLASSTYPE'],
                'campusCode' => '',
            );
        }

        return array_values($classes);
    }


    /**
     * @inheritdoc
     * @throws \Alaress\Database\Exception
     */
    public function getEnrolledStudents(string $externalUserId): array
    {
        // prepare
        $stmt = $this->getConnection()->prepareQuery("
            SELECT STUDENT_NUMBER
            FROM SCHOOLBOX_PARENT_STUDENT
            WHERE CARER_NUMBER = :id
        ");

        // retrieve
        $stmt->execute(array(
            'id' => $externalUserId
        ));

        // manipulate
        $children = array();
        while ($synId = $stmt->fetchColumn()) {
            // ensure a lack of duplicates
            $children[$synId] = array(
                'RelatedID' => $synId
            );
        }

        return array_values($children);
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
     * @inheritdoc
     */
    public function getParentEmails(array $externalUserIds): array
    {
        $emails = [];
        $userEmails = [];
        $contacts = $this->getStudentContactList($externalUserIds);

        foreach ($contacts as $contact) {
            $userEmails[] = [
                'externalId' => $contact['StudentContactID'],
                'email' => $contact['StudentContactDefaultEmail']
            ];
            $userEmails[] = [
                'externalId' => $contact['StudentContactID'],
                'email' => $contact['StudentContactSpouseDefaultEmail']
            ];
        }

        foreach ($userEmails as $i => $email) {
            $emails[$email['externalId'] . '-' . $i] = $email['email'];
        }

        return array_filter(array_unique($emails));
    }

    /**
     * @inheritdoc
     * @deprecated
     */
    public function getStudentContactListExtended(string $yearLevel, string $name, string $boardingStatus, string $form): array
    {
        return [];
    }

    /**
     * @inheritdoc
     * @deprecated
     */
    public function determineBoarderType(?string $boarderValue): string
    {
        return '';
    }

    /**
     * @inheritdoc
     */
    public function getHousemasterEmails(array $externalUserIds): array
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    public function getStaffContactListExtended(): array
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    public function getCampusFromTimetable(string $staffID): array
    {
        return [];
    }

    /**
     * @inheritdoc
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

    /**
     * @inheritdoc
     */
    public function getStaff(string $externalUserId): array
    {
        return [];
    }
}
