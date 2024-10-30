<?php

/**
 *
 * @author      James Leckie <jleckie@schoolbox.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

use Alaress\Database\Predicate\Builder as PredicateBuilder;

class External_Isams_Cispl_User extends External_Isams_User
{
    /**
     * @inheritdoc
     */
    public function getStudentContactList(array $externalUserId): array
    {
        if (empty($externalUserId)) {
            return [];
        }

        $results              = [];
        $externalUserIdChunks = array_chunk($externalUserId, \Alaress\Database\Broker\Base::MAX_BIND_NUMBER);

        foreach ($externalUserIdChunks as $externalUserIds) {
            $predicate = PredicateBuilder::getArrayBindings($externalUserIds, 'studentIds');

            $query   = "
            SELECT
                        V_Parent.ParentID             AS StudentContactID,
                        ''                            AS StudentContactTitle,
                        V_Parent.ParentFirstname      AS StudentContactPreferred,
                        V_Parent.ParentSurname        AS StudentContactSurname,
                        V_Parent.PersonalEmail        AS StudentContactDefaultEmail,
                        1                             AS ValidAddressFlag,
                        V_Parent.Address1             AS StudentContactAddress1,
                        V_Parent.Address2             AS StudentContactAddress2,
                        V_Parent.Address3             AS StudentcontactAddress3,
                        ''                            AS StudentContactSuburb,
                        V_Parent.PostCode             AS StudentContactPostCode,
                        ''                            AS StudentContactState,
                        V_Parent.Country              AS StudentContactCountryCode,
                        V_Parent.Mobile               AS StudentContactMobilePhone,
                        ''                            AS StudentContactOccupPhone,
                        ''                            AS StudentContactPhoneActual,
                        V_Parent.Relationship         AS StudentContactRelation,
                        ''                            AS StudentContactSpouseID,
                        ''                            AS StudentContactSpouseTitle,
                        ''                            AS StudentContactSpousePreferred,
                        ''                            AS StudentContactSpouseSurname,
                        ''                            AS StudentContactSpouseMobilePhone,
                        ''                            AS StudentContactSpouseOccupPhone,
                        ''                            AS StudentContactSpouseDefaultEmail
            FROM        V_Student
            JOIN        V_Parent ON V_Parent.StudentID = V_Student.StudentID
            WHERE       V_Student.StudentID IN ({$predicate['placeholders']})
        ";
            $result  = $this->getConnector()->executeQuery($query, $predicate['bindings'])->fetchAll();
            $results = array_merge($results, $result);
        }
        return $results;
    }


    /**
     * @inheritDoc
     */
    public function getStudent(string $externalUserId): array
    {
        $query = "
            SELECT TOP 1
                        StudentID               AS StudentID,
                        StudentFirstname        AS StudentGiven1,
                        StudentSurname          AS StudentSurname,
                        ''                      AS StudentGender,
                        CONVERT(nvarchar(30), DOB, " . self::FORMAT_ISO8601 . " ) AS StudentBirthDate,
                        ''                      AS StudentTutorName,
                        ''                      AS StudentHeadOfHouse,
                        ''                      AS HeadOfHouseID,
                        ''                      AS StudentFormStaffName,
                        ''                      AS StudentTutorDescription,
                        ''                      AS TeacherFormID,
                        ''                      AS StudentForm,
                        ''                      AS TeacherTutorID,
                        AcademicHouse           AS StudentHouseDescription,
                        ''                      AS StudentHouse,
                        Grade                   AS StudentYearLevel,
                        ''                      AS StudentEmergencyName,
                        ''                      AS StudentEmergencyPhone,
                        ''                      AS StudentEmergencyPhoneAlt,
                        txtadditionalHealth     AS StudentMedicalAlert,
                        ''                      AS CourtOrderType,
                        ''                      AS StudentMobilePhone,
                        ''                      AS StudentSpecialNeedsFlag,
                        ''                      AS EnglishSecondFlag,
                        ''                      AS StudentStudiesCode,
                        ''                      AS StudentBoarder,
                        ''                      AS StudentIBFlag,
                        ''                      AS IndigenousFlag,
                        ''                      AS StudentTSIFlag,
                        ''                      AS ParentsSeparatedFlag,
                        EnrolmentDate           AS StudentEnrolmentDate,
                        LeavingDate             AS StudentLeavingDate,
                        0 AS countIndividualEducationPlans
            FROM        V_Student
            WHERE       StudentID = :external_user_id
        ";

        /* Forcibly obey External_UserInterface
         */
        $student = $this->getConnector()->executeQuery($query, ['external_user_id' => $externalUserId])->fetch();

        /* Not sure if this was ever able to return null _and_ empty array
         * under some weird circumstances, but whatever...init it as an empty
         * array regardless
         */
        if (empty($student)) {
            $student = [];
        }

        /* Forcibly re-evaluate boarderlyness using this connector's specific interpretation
         */
        $student['StudentBoarder'] = $this->determineBoarderType((string)$student['StudentBoarder']);

        $student['AlertMessage'] = [];
        $student['AlertLegal'] = [];

        return $student;
    }
}
