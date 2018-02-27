<?php
/**
 *
 * @author      Doug MacKenzie <doug.mackenzie@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

class External_Smcs_User_Class extends External_Synergetic_User
{
    /**
     * @inheritdoc
     */
    public function getStudent($synID)
    {
        if (!$this->isExternalIdValid($synID)) {
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
                        CONVERT(nvarchar(30), uvStudents.StudentBirthDate, ".self::FORMAT_ISO8601." ) as StudentBirthDate,
                        uvStudents.StudentTutorName,
                        uvStudents.StudentHeadOfHouse,
                        House.HeadOfHouseID,
                        uvStudents.StudentFormStaffName,
                        uvStudents.StudentTutorDescription,
                        luForm.ID AS TeacherFormID,
                        uvStudents.StudentForm,
                        luTutor.ID AS TeacherTutorID,
                        uvStudents.StudentTutor,
                        luForm.Description AS FormDescription,
                        uvStudents.StudentHouseDescription,
                        uvStudents.StudentHouse,
                        uvStudents.StudentYearLevel,
                        uvStudents.StudentYearLevelCoordinator,
                        luYearLevel.YearLevelCoordID,
                        uvStudents.StudentLockerBook,
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
                        uvStudents.StudentSpecialNeedsFlag,
                        uvStudents.StudentStudiesCode,
                        AlertMessage,
                        uvStudents.StudentBoarder,
                        uvStudents.StudentsComment as Comment,                        
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

        $iteratorStudents = $this->getConnector()->getQueryIterator($query, ['externalId' => $synID]);

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
            $student['AlertMessage'] = implode("\n", array_unique(array_filter($student['AlertMessage'])));
        }

        // Remove Synergetic's annoying 'Not Selected' default value for lookups

        return $this->clearInvalidValues($student);
    }

    /**
     * @inheritdoc
     */
    public function getStudentUserAccountInfo()
    {
        $query =
            "
            SELECT      uvStudents.StudentID,
                        NetworkLogin,
                        uvStudents.StudentTitle,
                        uvStudents.StudentPreferred,
                        uvStudents.StudentSurname,
                        StudentGender,
                        StudentEmail,
                        StudentOccupEmail,
                        uvStudents.StudentYearLevel,
                        uvStudents.StudentHouse,
                        uvStudents.StudentBoarder,
                        uvStudents.StudentBoardingHouse,
                        StudentCampus
            FROM        dbo.uvStudents uvStudents
            JOIN        FileSemesters Semester ON
                            Semester.FileSemester = uvStudents.FileSemester AND
                            Semester.FileYear = uvStudents.FileYear AND
                            Semester.SystemCurrentFlag = 1
            LEFT JOIN   Community ON Community.ID = StudentID
        ";

        return $this->getConnector()->executeQuery($query)->fetchAll();
    }

    /**
     * @inheritdoc
     */
    public function getPortrait($externalUserId)
    {
        // Make sure a valid external ID was passed

        if (!$this->isExternalIdValid($externalUserId)) {
            return false;
        }

        // Get path on Schoolbox server to cached photos. These stupidly have a .JPG extension regardless of what
        // they really are- so don't pay much attention that the type

        $photoPath = realpath($this->config->get('portrait_dir')) . "/{$externalUserId}.jpg";

        // Check if the configuration options have SIS photo imporing disabled

        if ($this->config->get('photo_sis_import') == 0) {

            // Not allowed to import photos. If one exists locally we can use it, otherwise we will just fail

            if (file_exists($photoPath)) {
                return $photoPath;
            } else {
                return false;
            }
        }

        // Do a query to get the photos modification date in the SIS. We don't pull the actual photo at this time

        $queryPhoto = $this->getConnector()->executeQuery(
            "SELECT uvPhotos.ModifiedDate FROM dbo.uvPhotos uvPhotos WHERE uvPhotos.ID = :externalId",
            ['externalId' => $externalUserId]
        );

        // Check if we found a photo in the SIS

        if ($photo = $queryPhoto->fetch()) {

            // Check we can read the file

            if (is_readable($photoPath)) {

                // If the file is empty, or does not contain a valid image we want to replace it regardless of its date

                if (filesize($photoPath) == 0 || !exif_imagetype($photoPath)) {

                    $downloadImage = true;

                } else {

                    // File on disk was a valid image- check the modification date to see if we want to redownload

                    $downloadImage = filemtime($photoPath) < strtotime($photo['ModifiedDate']);
                }
            } else {

                // File was not readable

                $downloadImage = true;
            }

            if ($downloadImage) {

                // Do a query to retrieve the actual photo from the database
                unset($queryPhoto);
                $queryPhoto = $this->getConnector()->executeQuery(
                    "SELECT uvPhotos.Photo FROM dbo.uvPhotos uvPhotos WHERE uvPhotos.ID = :externalId",
                    ['externalId' => $externalUserId]
                );

                $photo = $queryPhoto->fetch();

                // The cache has expired, overwrite it with the one from the SIS

                $photoHandle = fopen($photoPath, "w");
                fwrite($photoHandle, $photo['Photo']);
                fclose($photoHandle);
            }

            return $photoPath;
        }

        // There was no photo in the SIS, so we don't want to display the cached image either- delete it from
        // disk so it doesn't magically appear later after they'd specifically deleted it from Synergetic

        if (file_exists($photoPath)) {
            unlink($photoPath);
        }

        return false;
    }
}