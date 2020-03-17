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

        if($this->config->get('external_learning_plans')) {
            $learningPlanQuery = "(
                            SELECT    COUNT(*)
                            FROM      dbo.IndividualPlans IEP
                            WHERE     IEP.ID = uvStudents.ID AND
                            StartDate <= CURRENT_TIMESTAMP AND
                            (EndDate >= CURRENT_TIMESTAMP OR EndDate IS NULL)
                        ) AS countIndividualEducationPlans";
        } else {
            $learningPlanQuery = "0 AS countIndividualEducationPlans";
        }

        $query =
            "
            SELECT      uvStudents.StudentID,
                        uvStudents.StudentGiven1,
                        uvStudents.StudentSurname,
                        '' AS StudentGender,
                        CONVERT(nvarchar(30), uvStudents.StudentBirthDate, ".self::FORMAT_ISO8601." ) as StudentBirthDate,
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
                        $learningPlanQuery
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

                $student['AlertMessage'] = [
                    $student['AlertMessage']
                ];

            } else {

                // Additional records will only different by their alert message, we just append these to the
                // existing student record

                $student['AlertMessage'][] = $row['AlertMessage'];
            }
        }

        // Implode all of the alert messages down to a single message seperate by line breaks

        if (!empty($student) && is_array($student['AlertMessage'])) {
            $student['AlertMessage'] = array_unique(array_filter($student['AlertMessage']));
        }

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
        $user->setPortrait(null);

        \Schoolbox\UserMapper::getInstance()->save($user);

        return null;
    }
}
