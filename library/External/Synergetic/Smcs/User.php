<?php

/**
 *
 * @author      Doug MacKenzie <doug.mackenzie@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

use Schoolbox\Resource\Storage;
use Schoolbox\User;
use Alaress\Database\Predicate\Builder;
use Schoolbox\External\Connector\AllMeaningfulValues;

class External_Synergetic_Smcs_User extends External_Synergetic_User
{
    /**
     * @inheritdoc
     */
    public function getStudent(string $externalUserId): array
    {
        if (!$this->isExternalIdValid($externalUserId)) {
            return [];
        }

        $query =
            "
            SELECT      uvStudents.StudentID,
                        uvStudents.StudentGiven1,
                        uvStudents.StudentSurname,
                        '' AS StudentGender,
                        CONVERT(nvarchar(30), uvStudents.StudentBirthDate, " . self::FORMAT_ISO8601 . " ) as StudentBirthDate,
                        uvStudents.StudentTutorName,
                        uvStudents.StudentHeadOfHouse,
                        House.HeadOfHouseID,
                        uvStudents.StudentFormStaffName,
                        uvStudents.StudentTutorDescription,
                        luForm.ID AS TeacherFormID,
                        uvStudents.StudentForm,
                        luTutor.ID AS TeacherTutorID,
                        uvStudents.StudentHouseDescription,
                        uvStudents.StudentHouse,
                        uvStudents.StudentYearLevel,
                        uvStudents.StudentYearLevelCoordinator,
                        luYearLevel.YearLevelCoordID,
                        uvStudents.StudentEmergencyName,
                        uvStudents.StudentEmergencyPhone,
                        uvStudents.StudentEmergencyPhoneAlt,
                        uvStudents.StudentEmergency2Name,
                        uvStudents.StudentEmergency2Phone,
                        uvStudents.StudentEmergency2PhoneAlt,
                        uvStudents.StudentEmergency3Name,
                        uvStudents.StudentEmergency3Phone,
                        uvStudents.StudentEmergency3PhoneAlt,
                        uvStudents.StudentMedicalAlert,
                        '' AS StudentMobilePhone,
                        uvStudents.StudentSpecialNeedsFlag,
                        uvStudents.StudentStudiesCode,
                        AlertMessage,
                        '' AS CourtOrderType,
                        uvStudents.StudentBoarder,
                        uvStudents.StudentsComment as Comment,
                        '' AS StudentEnrolmentDate,
                        '' AS StudentLeavingDate,
                        '' AS StudentIBFlag,
                        '' AS EnglishSecondFlag,
                        (
                            SELECT    COUNT(*)
                            FROM      dbo.IndividualPlans IEP
                            WHERE     IEP.ID = uvStudents.ID AND
                            StartDate <= CURRENT_TIMESTAMP AND
                            (EndDate >= CURRENT_TIMESTAMP OR EndDate IS NULL)
                        ) AS countIndividualEducationPlans
            FROM        dbo.uvStudents uvStudents
            LEFT JOIN   CommunityAlerts ON
                            CommunityAlerts.ID = StudentID AND
                            (
                                CommunityAlerts.AlertDateFrom IS NULL OR
                                CommunityAlerts.AlertDateFrom <= CAST(GETDATE() AS DATE)
                            ) AND
                            (
                                CommunityAlerts.AlertDateTo IS NULL OR
                                CommunityAlerts.AlertDateTo >= CAST(GETDATE() AS DATE)
                            )
            LEFT JOIN   luForm ON uvStudents.StudentForm = luForm.Code
            LEFT JOIN   luTutor ON uvStudents.StudentTutor = luTutor.Code
            LEFT JOIN   luYearLevel ON
                            uvStudents.StudentCampus = luYearLevel.Campus AND
                            uvStudents.StudentYearLevel = luYearLevel.Code
            LEFT JOIN   luHouse House ON
                            StudentCampus = House.Campus AND
                            StudentHouse = House.Code
            JOIN        FileSemesters Semester ON
                            Semester.FileSemester = uvStudents.FileSemester AND
                            Semester.FileYear = uvStudents.FileYear AND
                            Semester.SystemCurrentFlag = 1
            WHERE       uvStudents.StudentID = :externalId
        ";

        $student = [];

        $iteratorStudents = $this->getConnector()->getQueryIterator($query, ['externalId' => $externalUserId]);

        foreach ($iteratorStudents as $row) {
            // There might be multiple records, if this is the first one populate the bulk of the details

            if (empty($student)) {
                $student = $row;

                if ($student['StudentBirthDate'] && $birthDate = strtotime($student['StudentBirthDate'])) {
                    $student['StudentBirthDate'] = $birthDate;
                } else {
                    $student['StudentBirthDate'] = false;
                }

                /* Forcibly re-evaluate boarderlyness using this connector's specific interpretation
                 */
                $student['StudentBoarder'] = $this->determineBoarderType((string)$student['StudentBoarder']);

                /* Initialise stacks
                 */
                $student['AlertMessage'] = [];
                $student['AlertLegal'] = [];
            }

            /* Legal Alerts are distinguished by their CourtOrderType, it is not related to AlertMessage.
             * If CourtOrderType is meaningful, then we add only it's type to the LegalAlert stack.
             */
            $courtOrderType = trim((string)($row['CourtOrderType'] ?? null));

            if ('' !== $courtOrderType) {
                /* A specially-crafted AlertLegal message about legal matters.
                 */
                $student['AlertLegal'][] = sprintf(
                    '%s - %s',
                    \External_UserInterface::COURT_ORDER_PREFIX,
                    $courtOrderType
                );
            }

            /* Some kind of generic alert message
             */
            $student['AlertMessage'][] = $row['AlertMessage'];
        }

        /* Uniquify both AlertMessage and AlertLegal nodes since they might dupe
         * as a result of those left joins in the mega query
         */
        $student['AlertMessage'] = array_unique(AllMeaningfulValues::find($student['AlertMessage']));
        $student['AlertLegal'] = array_unique(AllMeaningfulValues::find($student['AlertLegal']));

        // Remove Synergetic's annoying 'Not Selected' default value for lookups

        return $this->clearInvalidValues($student);
    }


    /**
     * @inheritdoc
     * @param User $user
     * @return Storage\Model|null
     * @throws InvalidArgumentException
     * @throws PDOException
     * @throws UnexpectedValueException
     * @throws \Schoolbox\Mapper\Exception
     */
    public function getPortrait(User $user): ?Storage\Model
    {
        // Make sure a valid external ID was passed

        if (!$this->isExternalIdValid($user->getExternalId())) {
            return null;
        }

        // Do a query to get the photos modification date in the SIS. We don't pull the actual photo at this time

        $queryPhoto = $this->getConnector()->executeQuery(
            'SELECT uvPhotos.ModifiedDate
            FROM dbo.uvPhotos uvPhotos
            WHERE uvPhotos.ID = :externalId',
            ['externalId' => $user->getExternalId()]
        );

        // Check if we found a photo in the SIS
        if ($photo = $queryPhoto->fetch()) {
            /* If this $user doesn't have an image, or if the SIS picture is more recent than the one we
             * currently have, shank it down and save it into brownhole
             *
             * (the latter occurs when the sis gets updated with a new picture and we need to update our storage)
             */
            if ((null === $user->getPortrait()) || $user->getPortrait()->getCreatedAt()->lt(\Alaress_Date_Format::newFromTimestamp(strtotime($photo['ModifiedDate'])))) {
                // Do a query to retrieve the actual photo data from the database
                unset($queryPhoto);

                $queryPhoto = $this->getConnector()->executeQuery(
                    'SELECT uvPhotos.Photo
                    FROM dbo.uvPhotos uvPhotos
                    WHERE uvPhotos.ID = :externalId',
                    ['externalId' => $user->getExternalId()]
                );

                $photo = $queryPhoto->fetch();

                /* Save it into brownhole
                 */
                $portrait = Storage\Mapper::getInstance()->acceptStream($user->getExternalId() . '.jpg', $photo['Photo']);

                /* Update user with latest version
                 */
                $user->setPortrait($portrait);

                \Schoolbox\UserMapper::getInstance()->save($user);
            }

            /* Then return the latest Storage\Model instance
             */
            return $user->getPortrait();
        }

        // There was no photo in the SIS, so we don't want to display the cached image either- delete it from
        // disk so it doesn't magically appear later after they'd specifically deleted it

        /* Unlink from user
         */
        if (!empty($user->getPortrait())) {
            $user->setPortrait(null);
            \Schoolbox\UserMapper::getInstance()->save($user);
        }

        return null;
    }

    /**
     * @inheritdoc
     */
    public function getStudentContactList(array $externalUserId): array
    {
        if (empty($externalUserId)) {
            return [];
        }

        /**
         * Return valid external ID's only and convert it into a string delimited by comma (,) to be used in the
         * query below using an IN statement.
         */
        $validExternalIds = array_filter($externalUserId, function ($externalUserId) {
            return $this->isExternalIdValid($externalUserId);
        });

        $predicate = Builder::getArrayBindings($validExternalIds, 'studentIds');

        if (!$validExternalIds) {
            return [];
        }

        $contactTypes = $this->getStudentContactTypeBindings();

        $query =
            "
            SELECT          a.StudentContactID,
                            a.StudentContactTitle,
                            a.StudentContactPreferred,
                            a.StudentContactSurname,
                            a.StudentContactDefaultEmail,
                            a.StudentContactPhoneActual,
                            a.StudentContactSilentPhoneFlag,
                            a.StudentContactMobilePhone,
                            a.StudentContactOccupPhone,
                            a.StudentContactRelation,
                            a.StudentContactPrimaryOnlyFlag,
                            CASE WHEN a.StudentContactPrimaryOnlyFlag = 1 THEN '' ELSE a.StudentContactSpouseID END AS StudentContactSpouseID,
                            CASE WHEN a.StudentContactPrimaryOnlyFlag = 1 THEN '' ELSE a.StudentContactSpouseTitle END AS StudentContactSpouseTitle,
                            CASE WHEN a.StudentContactPrimaryOnlyFlag = 1 THEN '' ELSE a.StudentContactSpousePreferred END AS StudentContactSpousePreferred,
                            CASE WHEN a.StudentContactPrimaryOnlyFlag = 1 THEN '' ELSE a.StudentContactSpouseSurname END AS StudentContactSpouseSurname,
                            CASE WHEN a.StudentContactPrimaryOnlyFlag = 1 THEN '' ELSE a.StudentContactSpouseMobilePhone END AS StudentContactSpouseMobilePhone,
                            CASE WHEN a.StudentContactPrimaryOnlyFlag = 1 THEN '' ELSE a.StudentContactSpouseOccupPhone END AS StudentContactSpouseOccupPhone,
                            CASE WHEN a.StudentContactPrimaryOnlyFlag = 1 THEN '' ELSE a.StudentContactSpouseDefaultEmail END AS StudentContactSpouseDefaultEmail,
                            a.StudentContactType,
                            CASE WHEN a.StudentContactPrimaryOnlyFlag = 1 THEN '' ELSE a.StudentContactSpouseRelation END AS StudentContactSpouseRelation,
                            r.UseEmail AS StudentContactUseEmail,
                            CASE WHEN a.StudentContactPrimaryOnlyFlag = 1 THEN '' ELSE rs.UseEmail END AS StudentContactSpouseUseEmail
            FROM            dbo.uvStudentContactAllAddress a
            LEFT JOIN       Relationships r ON r.id = a.StudentID AND r.RelatedID = a.StudentContactID
            LEFT JOIN       Relationships rs ON rs.id = a.StudentID AND rs.RelatedID = a.StudentContactSpouseID
            JOIN            FileSemesters Semester ON
                                Semester.FileSemester = a.FileSemester AND
                                Semester.FileYear = a.FileYear AND
                                Semester.SystemCurrentFlag = 1
            WHERE           StudentID IN ({$predicate['placeholders']}) AND
                            StudentContactType IN ({$contactTypes['placeholders']})
        ";

        $statement = $this->getConnector()->executeQuery(
            $query,
            array_merge(
                $contactTypes['bindings'],
                $predicate['bindings']
            )
        );

        $contacts = $this->clearInvalidValues($statement->fetchAll());
        $contactIdList = [];
        $contactReturn = [];
        //Dedupe contacts when mother and father are both contacts
        foreach ($contacts as $contact) {
            if (isset($contactIdList[$contact['StudentContactID']])) {
                continue;
            }

            $contactReturn[] = $contact;

            if ($contact['StudentContactID']) {
                $contactIdList[$contact['StudentContactID']] = true;
            }
        }

        return $contactReturn;
    }
}
