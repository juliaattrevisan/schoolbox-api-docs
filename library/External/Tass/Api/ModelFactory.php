<?php

/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace External\Tass\Api;

use External\Tass\Api\Exception\NotFound;
use External\Tass\Api\Exception\UdParseException;
use External\Tass\Api\Models;
use Alaress_Date_Format;
use Schoolbox\GenericCollection;

/**
 * Responsible for converting data from Tass API into a strongly-typed object for consumption by the Tass External Connector
 */
class ModelFactory
{

    /**
     * @param mixed[] $data
     * @return Models\Student
     */
    public function buildStudent(array $data): Models\Student
    {
        // TODO need to get example of $data structure
        return new Models\Student();
    }


    /**
     * @param mixed[] $data
     * @return Models\StudentUD
     * @throws UdParseException If cannot parse UD data
     */
    public function buildStudentUd(array $data): Models\StudentUD
    {
        $patterns = ['/^UD(\d+)_CODE$/', '/^UD(\d+)_DATE$/', '/^UD(\d+)_FLG$/'];
        [$udCodes, $udDates, $udFlags] = $this->parseUd($data, $patterns, false);

        // Convert all UD dates to ADF
        foreach ($udDates as $idx => $dateString) {
            $udDates[$idx] = $this->hydrateDate($dateString, Alaress_Date_Format::DATABASE_FORMAT_MS);
        }

        return new Models\StudentUD(
            $udCodes,
            $udDates,
            $udFlags,
            new Alaress_Date_Format($data['UPDATE_ON'], Alaress_Date_Format::DATABASE_FORMAT_MS)
        );
    }


    /**
     * @param mixed[][] $data
     * @return Models\StudentAddresses
     */
    public function buildStudentAddresses(array $data): Models\StudentAddresses
    {
        $studentAddresses = new GenericCollection(Models\StudentAddress::class);
        foreach ($data['ADDRESSES'] as $idx => $addressData) {
            $studentAddresses[$idx] = new Models\StudentAddress(
                $addressData['ADDRESS1'],
                $addressData['ADDRESS2'],
                $addressData['ADDRESS3'],
                $addressData['CONTACTTYPE'],
                $addressData['EMAIL'],
                $addressData['NAME'],
                $addressData['OCCUPPHONE'],
                $addressData['PHONE'],
                $addressData['PHONEALT'],
                $addressData['POSTCODE'],
                $addressData['SPOUSEBUSPHONE'],
                $addressData['SPOUSEEMAIL'],
                $addressData['SPOUSEID'],
                $addressData['SPOUSEMOBILEPHONE'],
                $addressData['SPOUSEPREFERRED'],
                $addressData['SPOUSERELATION'],
                $addressData['SPOUSESURNAME'],
                $addressData['SPOUSETITLE'],
                $addressData['STATE'],
                $addressData['SUBURB'],
                $addressData['VALIDADDRESSFLG']
            );
        }
        return new Models\StudentAddresses($studentAddresses);
    }


    /**
     * @param mixed[][] $data
     * @return Models\ParentDetails
     * @throws UdParseException If cannot parse UD data
     */
    public function buildParent(array $data): Models\ParentDetails
    {
        $patterns = ['/^PUD(\d+)_CODE$/', '/^PUD(\d+)_FLG$/', '/^PUD(\d+)_TEXT$/'];
        [$udCodes, $udDates, $udFlags] = $this->parseUd($data['UD'], $patterns, true);

        return new Models\ParentDetails(
            $data['ADDRESSES'],
            $data['CANDELETE'],
            $data['CMPY_CODE'],
            $data['F_NAME'],
            $data['F_OCC'],
            $data['M_NAME'],
            $data['M_OCC'],
            $data['PAR_CODE'],
            $data['PAR_TYPE'],
            $data['RECORD_ID'],
            $data['SFA_EXISTS'],
            $data['STATUS_DESC'],
            $data['STATUS_FLG'],
            $data['SURNAME'],
            $data['TABLE_ID'],
            $udCodes,
            $udDates,
            $udFlags,
            $data['UPDATE_BY'],
            new Alaress_Date_Format($data['UPDATE_ON'], Alaress_Date_Format::DATABASE_DATE_FORMAT),
            $data['WEB_ACCESS_FLG'] === 'Y'
        );
    }


    /**
     * @param mixed[][] $data
     * @return Models\SubjectList
     */
    public function buildSubjectList(array $data): Models\SubjectList
    {
        $subjects = new GenericCollection(Models\Subject::class);
        foreach ($data['SUBJECTS'] as $idx => $subjectData) {
            $subjects[$idx] = new Models\Subject(
                (string)$subjectData['SUB_CODE'],
                $subjectData['YEAR_GRP'],
                $subjectData['SUB_LONG'],
                $subjectData['CLASS'],
                $subjectData['SUB_TYPE'],
                $subjectData['CAMPUS_CODE']
            );
        }
        return new Models\SubjectList($subjects);
    }


    /**
     * @param mixed[] $data
     * @param bool    $contacts
     * @return Models\TimetableSetup
     */
    public function buildTimetableSetup(array $data, bool $contacts): Models\TimetableSetup
    {
        if ($contacts) {
            $timetableContact = new Models\TimetableSetupContact(
                $data['CONTACT_EMAIL'],
                $data['CONTACT_FAX'],
                $data['CONTACT_NAME'],
                $data['CONTACT_PHONE'],
                $data['CONTACT_URL']
            );
        }
        return new Models\TimetableSetup(
            $timetableContact ?? null,
            $data['CAL_FLG'] === 'Y',
            $data['CLASH_FLG'] === 'Y',
            $data['CURR_SEM'],
            $data['CURR_YEAR'],
            $data['INT_VALID_FLG'],
            $data['ISMULTITT'],
            $data['ISUNITISED'],
            $data['MAX_DAYS'],
            $data['MAX_PERIODS'],
            $data['MODE'],
            $data['MULTI_TT_FLG'] === 'Y',
            $data['RECORD_ID'],
            $data['UNIT_FLG'] === 'Y'
        );
    }


    /**
     * Returns an array of items per pattern that match that pattern.
     *
     * @param string[] $udData            An array of data items
     * @param string[] $patterns          An array of regular expression patterns.
     * @param bool     $throwIfNotMatched Set to TRUE if you expect all $udData to match a pattern
     * @return string[][] Indexed by $pattern's array index
     * @throws UdParseException If we expect all items to match a pattern, and an item is found that doesn't match
     */
    private function parseUd(array $udData, array $patterns, bool $throwIfNotMatched): array
    {
        // Ensure 0-indexed
        $patterns = array_values($patterns);
        $udArrays = [];

        // Ensure we return an empty array even if no matches for a pattern
        foreach ($patterns as $idx => $pattern) {
            $udArrays[$idx] = [];
        }

        foreach ($udData as $key => $val) {
            $matchFound = false;
            foreach ($patterns as $idx => $pattern) {
                if (preg_match($pattern, $key, $matches)) {
                    $udArrays[$idx][$matches[1]] = $val;
                    $matchFound = true;
                    break;
                }
            }
            if (!$matchFound && $throwIfNotMatched) {
                // In some cases the $udData contains non-ud-data, so don't throw
                throw new UdParseException("Unrecognised UD. Key: '{$key}', Val: '{$val}'");
            }
        }
        return $udArrays;
    }


    /**
     * @param int[]|string[] $data
     * @return Models\EnrolledStudent
     * @throws UdParseException If cannot parse UD data
     */
    public function buildEnrolledStudent(array $data): Models\EnrolledStudent
    {
        $patterns = ['/^SUD(\d+)_BILL$/', '/^SUD(\d+)_CODE$/', '/^SUD(\d+)_FLG$/', '/^SUD(\d+)_TEXT$/'];
        [$udBills, $udCodes, $udFlags, $udTexts] = $this->parseUd($data, $patterns, false);

        return new Models\EnrolledStudent(
            $this->hydrateDate($data['ACCEPT_DATE'], Alaress_Date_Format::DATABASE_FORMAT_MS),
            $data['ACCEPT_PAID'],
            (string)$data['ALT_ID'],
            $data['ASSESS_DATE'],
            $data['ASSESS_FLG'],
            null === $data['BOARDER'] ? null : ($data['BOARDER'] === 'Y'),
            $data['CAMPUS_CODE'],
            $data['CMPY_CODE'],
            $data['CURR_SCHOOL'],
            (string)$data['CURR_YEAR_GRP'],
            $data['DATE_ARRIVAL'],
            $data['DISPLAYNAME'],
            $this->hydrateDate($data['DOA'], Alaress_Date_Format::DATABASE_FORMAT_MS),
            $this->hydrateDate($data['DOB'], Alaress_Date_Format::DATABASE_FORMAT_MS),
            (string)$data['ENTRY_YGRP'],
            (string)$data['ENTRY_YGRP_DESC'],
            (string)$data['ENTRY_YR'],
            $data['E_MAIL'],
            $data['FOLLOWUP_DATE'],
            $data['FOLLOWUP_FLG'],
            $data['FORM_CLS'],
            (string)$data['FTE'],
            $data['GIVEN_NAME'],
            $data['HOLD_PAID'],
            $data['HOUSE'],
            $this->hydrateDate($data['HPAID_DATE'], Alaress_Date_Format::DATABASE_FORMAT_MS),
            (string)$data['ID'],
            $data['INTERVIEW_DATE'],
            $data['INTERVIEW_FLG'],
            $data['LW_ADD_NUM'],
            $data['MOB_PHONE'],
            $data['MODE'],
            $data['MULTIPAR_FLG'],
            $this->hydrateDate($data['OFFER_DATE'], Alaress_Date_Format::DATABASE_FORMAT_MS),
            (string)$data['PAR_CODE'],
            $data['PCTUT_GRP'],
            $data['PLACE_OFFERED'],
            $data['PREFERRED_NAME'],
            $data['PRI_CODE'],
            $data['PRI_POINTS'],
            $data['PRIVACY_FLG'],
            $this->hydrateDate($data['PROSP_DATE'], Alaress_Date_Format::DATABASE_FORMAT_MS),
            $data['PROSP_FLG'],
            null === $data['READONLY'] ? null : ($data['READONLY'] !== 'No'),
            $data['RELIGION'],
            $data['RESIDENT_STS'],
            $data['SEX'],
            $data['SMS_FLG'],
            (string)$data['STUD_CODE'],
            $data['STUD_ID'],
            $udBills,
            $udCodes,
            $udFlags,
            $udTexts,
            $data['SURNAME'],
            $this->hydrateDate($data['TFER_DATE'], Alaress_Date_Format::DATABASE_FORMAT_MS),
            $data['UPDATE_BY'],
            $this->hydrateDate($data['UPDATE_ON'], Alaress_Date_Format::DATABASE_FORMAT_MS),
            $data['VISA_EXPIRY'],
            $data['VISA_SUBCLASS'],
            (string)$data['YEAR_GRP']
        );
    }


    /**
     * @param int[][][]|string[][][] $data
     * @return Models\EnrolledStudent[]|GenericCollection
     * @throws UdParseException If cannot parse UD data
     */
    public function buildEnrolledStudentList(array $data): GenericCollection
    {
        $studentList = new GenericCollection(Models\EnrolledStudent::class);


        foreach ($data['DATA'] as $idx => $studentData) {
            $studentList[$idx] = $this->buildEnrolledStudent($studentData);
        }
        return $studentList;
    }


    /**
     * @param string[][][]|int[][][] $data
     * @return Models\EnrolledStudent[]|GenericCollection
     * @throws UdParseException If cannot parse UD data
     */
    public function buildClassStudentList(array $data): GenericCollection
    {
        $classStudentList = new GenericCollection(Models\EnrolledStudent::class);
        foreach ($data['CLASSES'] as $idx => $studentData) {
            $classStudentList[$idx] = $this->buildEnrolledStudent($studentData);
        }
        return $classStudentList;
    }


    /**
     * @param string[][][]|int[][][] $data
     * @return Models\Teacher[]|GenericCollection
     * @throws UdParseException If cannot parse UD data
     */
    public function buildClassTeacherList(array $data): GenericCollection
    {
        $classTeacherList = new GenericCollection(Models\Teacher::class);
        foreach ($data['CLASSES'] as $idx => $teacherData) {
            $classTeacherList[$idx] = $this->buildTeacher($teacherData);
        }
        return $classTeacherList;
    }


    /**
     * @param array $data
     * @return Models\Teacher
     * @throws UdParseException If cannot parse UD data
     */
    private function buildTeacher(array $data): Models\Teacher
    {
        [$udText] = $this->parseUd($data, ['/^TUD(\d+)_TEXT$/'], false);

        return new Models\Teacher(
            $data['CLASS'],
            $data['CMPY_CODE'],
            $data['EMP_CODE'],
            $data['E_MAIL'],
            $data['ID'],
            $data['SURNAME'],
            $data['TCH_CODE'],
            $data['TCH_NAME'],
            $udText,
            $data['YEAR_GRP'],
            (string)$data['YEAR_GRP_DESC']
        );
    }


    /**
     * @param mixed[][] $data
     * @return Models\TimetableDefinitions
     */
    public function buildTimetableDefinitions(array $data): Models\TimetableDefinitions
    {
        return new Models\TimetableDefinitions(
            $data['DATA']['DAYMAX'],
            $data['DATA']['DAYS'],
            $data['DATA']['PERIODMAX']
        );
    }


    /**
     * @param int     $timeTableId
     * @param mixed[] $data
     * @return Models\TimetableDefinitions
     * @throws NotFound If no timetable found for the specified ID
     */
    public function buildTimetableDefinition(int $timeTableId, array $data): Models\TimetableDefinitions
    {
        // TODO is $timeTableId relevant in data structure?
        if (!array_key_exists('DATA', $data)) {
            throw new NotFound("No timetable data found for timetable id: '{$timeTableId}'");
        }

        return new Models\TimetableDefinitions(
            $data['DATA']['DAYMAX'],
            $data['DATA']['DAYS'],
            $data['DATA']['PERIODMAX']
        );
    }


    /**
     * @param mixed[][] $data
     * @return Models\StudentTimetable[]
     */
    public function buildStudentTimetable(array $data): array
    {
        $studentTimetable = [];
        foreach ($data['DATA'] as $idx => $datum) {
            $studentTimetable[$idx] = new Models\StudentTimetable(
                $datum['DATE'],
                $datum['DAY_CODE'],
                $datum['PERIODS']
            );
        }
        return $studentTimetable;
    }


    /**
     * @param string[][][]|string[][][][]|int[][][] $data
     * @return Models\TeacherTimetable[][]
     */
    public function buildTeacherTimetable(array $data): array
    {
        $timetables = [];
        // Try to find a numeric key as opposed to known non-timetable-data (eg __invalid, token etc)
        foreach ($data as $key => $val) {
            if (\is_numeric($key)) {
                foreach ($val as $subKey => $subVal) {
                    $timetables[$key][$subKey] = new Models\StudentTimetable(
                        $subVal['DATE'],
                        $subVal['DAY_CODE'],
                        $subVal['PERIODS']
                    );
                }
            }
        }
        return $timetables;
    }


    /**
     * @param mixed[] $data
     * @return Models\DayNumber
     */
    public function buildDayNumber(array $data): Models\DayNumber
    {
        // TODO need to inspect example data - consider scalar data type if possible
        return new Models\DayNumber();
    }


    /**
     * Hydrates a date string into an ADF
     *
     * @param null|string $dateString
     * @param string      $format
     * @return Alaress_Date_Format|null
     */
    private function hydrateDate(?string $dateString, string $format = Alaress_Date_Format::DATABASE_FORMAT): ?Alaress_Date_Format
    {
        if (null === $dateString || '' === trim($dateString)) {
            return null;
        }
        return new Alaress_Date_Format($dateString, $format);
    }
}
