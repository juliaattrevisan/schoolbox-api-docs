<?php

/**
 * External_Schoolpro_User
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
*/

declare(strict_types=1);

use Schoolbox\External\Normalise\TupleStack;
use Schoolbox\Resource\Storage;
use Schoolbox\User;

class External_Schoolpro_User implements External_UserInterface
{
    use External_MssqlConnector;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->setupDatabase();
    }

    /**
     * @inheritDoc
     */
    public function getParent(string $externalUserId): array
    {
        if (!is_numeric($externalUserId)) {
            return [];
        }

        $vars = [
            'sbGetParent',
            [
                'pUserName' => 'alaress',
                'pUserPass' => 'alaress',
                'pSchoolNum' => $externalUserId,
            ]
        ];

        $currentParent = $this->getConnector()->executeStoredProcedure($vars[0], $vars[1])->fetch();

        if ($currentParent) {
            return [
                'ContactID'            => $currentParent['ID'],
                'ContactPreferred'     => $currentParent['FirstName'] ?? '',
                'ContactSurname'       => $currentParent['Surname'] ?? '',
                'ContactEmail'         => $currentParent['Email'] ?? '',
                'ContactMobile'        => $currentParent['Mobile'] ?? '',
                'SpouseID'             => $currentParent['SpouseID'],
                'SpousePreferred'      => $currentParent['SpouseFirstName'] ?? '',
                'SpouseSurname'        => $currentParent['SpouseSurname'] ?? '',
                'SpouseEmail'          => $currentParent['SpouseEmail'] ?? '',
                'SpouseMobile'         => $currentParent['SpouseMobile'] ?? '',
                'DirectoryIncludeFlag' => 1
            ];
        }

        return [];
    }

    /**
     * @inheritDoc
     */
    public function getParentEmails(array $externalUserIds): array
    {
        $emails = [];

        if (\count($externalUserIds)) {
            foreach ($externalUserIds as $studentId) {
                if (!is_numeric($studentId)) {
                    continue;
                }
                /* Get the contacts for $studentId
                 */
                $vars = [
                    'sbGetStudentContact',
                    [
                        'pUserName' => 'alaress',
                        'pUserPass' => 'alaress',
                        'pSchoolNum' => $studentId,
                    ]
                ];

                $currentStudentContact = $this->getConnector()->executeStoredProcedure($vars[0], $vars[1])->fetch();

                if ($currentStudentContact) {
                    /* Get this contact's email
                     */
                    $emails[] = $currentStudentContact['email'];

                    /* Also get the spouse's email
                     */
                    $spouseVars = [
                        'sbGetParent',
                        [
                            'pUserName' => 'alaress',
                            'pUserPass' => 'alaress',
                            'pSchoolNum' => $currentStudentContact['SpouseID'],
                        ]
                    ];

                    $currentStudentSpouse = $this->getConnector()->executeStoredProcedure($spouseVars[0], $spouseVars[1])->fetch();

                    /* Ironically the Spouse has their Contact's email as well, in their record
                     * but the Contact doesn't have the Spouse's email ><
                     */
                    if ($currentStudentSpouse) {
                        $emails[] = $currentStudentSpouse['email'] ?? '';
                    }
                }
            }
        }

        /* Ditch any busted emails, then return them
         */
        return (new TupleStack())->normaliseValues($emails);
    }

    /**
     * @inheritDoc
     */
    public function getStudent(string $externalUserId): array
    {
        if (!is_numeric($externalUserId)) {
            return [];
        }

        $medicalNoteStr = '';

        $vars = [
            'sbGetStudent',
            [
                'pUserName' => 'alaress',
                'pUserPass' => 'alaress',
                'pSchoolNum' => $externalUserId,
            ]
        ];

        $currentStudent = $this->getConnector()->executeStoredProcedure($vars[0], $vars[1])->fetch();

        if ($currentStudent) {
            /* Get the medical data for him too
             */
            $medicalVars = [
                'sbGetStudentMedicalConditions',
                [
                    'pUserName' => 'alaress',
                    'pUserPass' => 'alaress',
                    'pSchoolNum' => $externalUserId,
                ]
            ];

            $medicalResult = $this->getConnector()->getStoredProcedureIterator($medicalVars[0], $medicalVars[1]);

            /* These arrive in a variety of formats
             */
            foreach ($medicalResult as $currentCondition) {
                /* Only include medical issues that are Applicable == 1
                 * Just use a basic message to hide the sensitive data,
                 * and bust out of the loop after the first positive result
                 */
                if ($currentCondition['Applicable']) {
                    $medicalNoteStr = 'This student has medical conditions.';
                    break;
                }
            }

            return [
                'StudentID'               => $currentStudent['ID'],
                'StudentTitle'            => '',
                'StudentGiven1'           => $currentStudent['Firstname'],
                'StudentSurname'          => $currentStudent['Surname'],
                'StudentGender'           => '',
                'StudentBirthDate'        => $currentStudent['Birthdate'],
                'StudentHouse'            => $currentStudent['HeadOfHouse'],
                'StudentYearLevel'        => $currentStudent['YearLevel'],
                'StudentTutorDescription' => $currentStudent['TutorName'],
                'StudentTutorName'        => $currentStudent['TutorStaffName'],
                'StudentForm'             => $currentStudent['FormName'],
                'StudentFormStaffName'    => $currentStudent['FormStaffName'],
                'StudentEmergencyName'    => $currentStudent['EmergencyContactName'],
                'StudentEmergencyPhone'   => $currentStudent['EmergencyContactPhone'],
                'StudentStudiesCode'      => '',
                'StudentMedicalAlert'     => $medicalNoteStr,
                'StudentMobilePhone'      => '',
                'StudentSpecialNeedsFlag' => '',
                'EnglishSecondFlag'       => '',
                'AlertMessage'            => [],
                'AlertLegal'              => [],
                'CourtOrderType'          => '',
                'Comment'                 => '',
                'StudentEnrolmentDate'    => '',
                'StudentLeavingDate'      => '',
                'StudentIBFlag'           => '',
                'IndigenousFlag'          => '',
                'StudentTSIFlag'          => '',
                'ParentsSeparatedFlag'    => '',
            ];
        }

        return [];
    }

    /**
     * @inheritDoc
     */
    public function getStaff(string $externalUserId): array
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    public function getStudentContactList(array $externalUserId): array
    {
        if (empty($externalUserId)) {
            return [];
        }

        $studentContactList = [];

        foreach ($externalUserId as $userId) {
            if (!is_numeric($userId)) {
                continue;
            }
            $vars = [
                'sbGetStudentContact',
                [
                    'pUserName' => 'alaress',
                    'pUserPass' => 'alaress',
                    'pSchoolNum' => $userId,
                ]
            ];

            $currentStudentContact = $this->getConnector()->executeStoredProcedure($vars[0], $vars[1])->fetch();

            if ($currentStudentContact) {
                $studentContact = [

                    /* This should be separated into constituent parts
                     */
                    'StudentContactPreferred'          => $currentStudentContact['FullName'],
                    'StudentContactSpousePreferred'    => $currentStudentContact['SpouseName'],
                    'StudentContactID'                 => $currentStudentContact['ID'],
                    'StudentContactDefaultEmail'       => $currentStudentContact['email'],
                    'StudentContactPhoneActual'        => $currentStudentContact['HomePhone'],
                    'StudentContactSilentPhoneFlag'    => 0,
                    'StudentContactMobilePhone'        => $currentStudentContact['Mobile'],
                    'StudentContactOccupPhone'         => $currentStudentContact['BusinessPhone'],
                    'StudentContactRelation'           => '',
                    'StudentContactSpouseID'           => $currentStudentContact['SpouseID'],
                    'StudentContactSpouseMobilePhone'  => $currentStudentContact['SpouseMobile'],
                    'StudentContactSpouseOccupPhone'   => $currentStudentContact['SpouseBusinessPhone'],
                    'StudentContactSpouseDefaultEmail' => '',
                    'StudentContactType'               => '',
                    'StudentContactSpouseRelation'     => '',
                ];

                // Ensure all values are strings to respect interface
                $studentContactList[] = array_map(static function ($value) {
                    return (string)$value;
                }, $studentContact);
            }
        }

        return $studentContactList;
    }


    /**
     * @inheritDoc
     */
    public function getStudentContactListExtended(string $yearLevel, string $name, string $boardingStatus, string $form): array
    {
        return [];
    }

    public function getCurrentYear(string $externalUserId = null): ?string
    {
        return date('Y');
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
    public function getSubjectList(User $user): array
    {
        $externalId = $user->getExternalId();

        if (!is_numeric($externalId)) {
            return [];
        }

        if ($user->getRole()->isStudent()) {
            $vars = [
                'sbGetStudentSubjectList',
                [
                    'pUserName' => 'alaress',
                    'pUserPass' => 'alaress',
                    'pSchoolNum' => $externalId,
                ]
            ];

            $result = $this->getConnector()->getStoredProcedureIterator($vars[0], $vars[1]);

            $subjectList = array();

            foreach ($result as $currentSubject) {
                $subject = array(
                    'code' => (string)$currentSubject['ClassID'],
                    'name' => $currentSubject['ItemName'],
                    'classType'  => '',
                    'campusCode' => '',
                );

                $subjectList[] = $subject;
            }

            return $subjectList;
        } else if ($user->getRole()->isStaff()) {
            $vars = [
                'sbGetStaffSubjectList',
                [
                    'pUserName' => 'alaress',
                    'pUserPass' => 'alaress',
                    'pSchoolNum' => $externalId,
                ]
            ];

            $result = $this->getConnector()->getStoredProcedureIterator($vars[0], $vars[1]);

            $subjectList = array();

            /* TODO: Make stored proc consistent with the StudentSubjectList one
             * Have to confer with Scoolpro to do that.
             */
            foreach ($result as $currentSubject) {
                $subject = array(
                    'code' => (string)$currentSubject['ID'],
                    'name' => $currentSubject['ItemName'],
                    'classType'  => '',
                    'campusCode' => '',
                );

                $subjectList[] = $subject;
            }

            return $subjectList;
        } else {
            return [];
        }
    }

    /**
     * @inheritDoc
     */
    public function getEnrolledStudents(string $externalUserId): array
    {
        if (!is_numeric($externalUserId)) {
            return [];
        }

        $vars = [
            'sbGetEnrolledStudents',
            [
                'pUserName' => 'alaress',
                'pUserPass' => 'alaress',
                'pSchoolNum' => $externalUserId,
            ]
        ];

        $result = $this->getConnector()->getStoredProcedureIterator($vars[0], $vars[1]);

        $allStudents = array();

        foreach ($result as $currentStudent) {
            $allStudents[] = ['RelatedID' => $currentStudent['RelationSchoolNum']];
        }

        return $allStudents;
    }

    /**
     * Returns a local path to the user's portrait
     *
     * @param User $user
     * @return Storage\Model|null
     * @throws InvalidArgumentException
     * @throws PDOException
     * @throws UnexpectedValueException
     * @throws \Schoolbox\Mapper\Exception
     */
    public function getPortrait(User $user): ?Storage\Model
    {
        if (!is_numeric($user->getExternalId())) {
            return $user->getPortrait();
        }

        $vars = [
            'sbGetPortraitLastUpdated',
            [
                'pUserName' => 'alaress',
                'pUserPass' => 'alaress',
                'pSchoolNum' => $user->getExternalId(),
            ]
        ];

        $photo = $this->getConnector()->executeStoredProcedure($vars[0], $vars[1])->fetch();

        /* Find the last updated datetime
         */
        if ($photo) {
            // TODO: delete photos on rollover

            /* If this $user doesn't have an image, or if the SIS picture is more recent than the one we
             * currently have, shank it down and save it into brownhole
             *
             * (the latter occurs when the sis gets updated with a new picture and we need to update our storage)
             */
            if ((null === $user->getPortrait()) || $user->getPortrait()->getCreatedAt()->lt(\Alaress_Date_Format::newFromTimestamp(strtotime($photo['CaptureDate'])))) {
                $vars = [
                    'sbGetPortraitFile',
                    [
                        'pUserName' => 'alaress',
                        'pUserPass' => 'alaress',
                        'pSchoolNum' => $user->getExternalId(),
                    ]
                ];

                $photo = $this->getConnector()->executeStoredProcedure($vars[0], $vars[1])->fetch();

                /* Get the binary data and dump it into a file
                 */
                if ($photo) {
                    /* Save it into brownhole
                     */
                    $portrait = Storage\Mapper::getInstance()->acceptStream($user->getExternalId() . '.jpg', $photo['RawData']);

                    /* Update user with latest version
                     */
                    $user->setPortrait($portrait);

                    \Schoolbox\UserMapper::getInstance()->save($user);
                }
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
     * @inheritDoc
     */
    public function determineBoarderType(?string $boarderValue): string
    {
        return '';
    }

    /**
     * @inheritDoc
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
    public function getStaffContactListExtended(): array
    {
        // TODO: Implement getStaffContactListExtended() method.
        return array();
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
     * {@inheritDoc}
     * @return array
     */
    public function getPhotoRedactionList(): array
    {
        return [];
    }
}
