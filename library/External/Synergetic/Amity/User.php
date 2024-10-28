<?php

/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

class External_Synergetic_Amity_User extends External_Synergetic_User
{

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

        if (!$validExternalIds) {
            return [];
        }

        if ($this->config->get('external_synergetic_student_contact_types') === '') {
            // No relationships were found, use the default values we had before introducing this feature
            $studentContactTypes = ['SC1', 'SC2', 'SC3'];
        } else {
            $studentContactTypes = array_map('trim', explode(',', $this->config->get('external_synergetic_student_contact_types')));
        }

        $query =
            "
            SELECT          a.StudentContactID,
                            a.StudentContactTitle,
                            a.StudentContactPreferred,
                            a.StudentContactSurname,
                            a.StudentContactAddress1,
                            a.StudentContactAddress2,
                            a.StudentContactAddress3,
                            a.StudentContactSuburb,
                            a.StudentContactPostCode,
                            a.StudentContactState,
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
            FROM            dbo.vStudentContactAllAddress a
            LEFT JOIN       Relationships r ON r.id = a.StudentID AND r.RelatedID = a.StudentContactID
            LEFT JOIN       Relationships rs ON rs.id = a.StudentID AND rs.RelatedID = a.StudentContactSpouseID
            JOIN            FileSemesters Semester ON
                                Semester.FileSemester = a.FileSemester AND
                                Semester.FileYear = a.FileYear AND
                                Semester.SystemCurrentFlag = 1
            WHERE           StudentID IN ('" . implode("','", $validExternalIds) . "')
        ";

        $statement = $this->getConnector()->executeQuery($query);

        $contacts = $this->clearInvalidValues($statement->fetchAll());
        $contactIdList = [];
        $contactReturn = [];

        /* Get a contact tuple containing up to two people and bust it out
         * into individual data, where applicable
         *
         * Each person has their own set of array keys because
         * in synergetic, contacts arrive with their speece in a tuple
         *
         * Contact Person A might have a spouse Person B,
         * but Contact Person B might have their spouse Person A,
         * and both sets are linked to a student
         *
         * This leads to a duped set of dudes, where Person A appears twice
         * and Person B appears twice
         */
        foreach ($contacts as $contactTuple) {
            if (!in_array($contactTuple['StudentContactType'], $studentContactTypes)) {
                continue;
            }

            /* Find the main contact's fields
             */
            $contact1 = [
                'StudentContactID'              => (string)$contactTuple['StudentContactID'],
                'StudentContactTitle'           => (string)$contactTuple['StudentContactTitle'],
                'StudentContactPreferred'       => (string)$contactTuple['StudentContactPreferred'],
                'StudentContactSurname'         => (string)$contactTuple['StudentContactSurname'],
                'StudentContactDefaultEmail'    => (string)$contactTuple['StudentContactDefaultEmail'],
                'StudentContactMobilePhone'     => (string)$contactTuple['StudentContactMobilePhone'],
                'StudentContactOccupPhone'      => (string)$contactTuple['StudentContactOccupPhone'],
                'StudentContactRelation'        => (string)$contactTuple['StudentContactRelation'],
                'StudentContactUseEmail'        => (string)$contactTuple['StudentContactUseEmail'],

                //When we deprecate spouse from the API this can be removed
                'StudentContactSpouseID'            => '',
                'StudentContactSpouseTitle'         => '',
                'StudentContactSpousePreferred'     => '',
                'StudentContactSpouseSurname'       => '',
                'StudentContactSpouseDefaultEmail'  => '',
                'StudentContactSpousePhoneActual'   => '',
                'StudentContactSpouseMobilePhone'   => '',
                'StudentContactSpouseOccupPhone'    => '',

                /* Address pieces (speece are in the same Euclidean geometry coordinates as their contact)
                 */
                'StudentContactAddress1' => (string)$contactTuple['StudentContactAddress1'],
                'StudentContactAddress2' => (string)$contactTuple['StudentContactAddress2'],
                'StudentContactAddress3' => (string)$contactTuple['StudentContactAddress3'],
                'StudentContactSuburb'   => (string)$contactTuple['StudentContactSuburb'],
                'StudentContactPostCode' => (string)$contactTuple['StudentContactPostCode'],
                'StudentContactState'    => (string)$contactTuple['StudentContactState'],

                /* PhoneActual, SilentPhoneFlag, PrimaryOnlyFlag, ContactType
                 * fields don't manifest for speece
                 */
                'StudentContactPhoneActual'     => (string)$contactTuple['StudentContactPhoneActual'],
                'StudentContactPrimaryOnlyFlag' => (string)$contactTuple['StudentContactPrimaryOnlyFlag'],
                'StudentContactSilentPhoneFlag' => (string)$contactTuple['StudentContactSilentPhoneFlag'],
                'StudentContactType'            => (string)$contactTuple['StudentContactType'],
            ];

            /* Convert speece into a contact format otherwise they won't appear in profile page
             * (speece must come with a contact) because speece are treated differently there
             */
            $contact2 = [
                'StudentContactID'              => (string)$contactTuple['StudentContactSpouseID'],
                'StudentContactTitle'           => (string)$contactTuple['StudentContactSpouseTitle'],
                'StudentContactPreferred'       => (string)$contactTuple['StudentContactSpousePreferred'],
                'StudentContactSurname'         => (string)$contactTuple['StudentContactSpouseSurname'],
                'StudentContactDefaultEmail'    => (string)$contactTuple['StudentContactSpouseDefaultEmail'],
                'StudentContactMobilePhone'     => (string)$contactTuple['StudentContactSpouseMobilePhone'],
                'StudentContactOccupPhone'      => (string)$contactTuple['StudentContactSpouseOccupPhone'],
                'StudentContactRelation'        => (string)$contactTuple['StudentContactSpouseRelation'],
                'StudentContactUseEmail'        => (string)$contactTuple['StudentContactSpouseUseEmail'],

                //When we deprecate spouse from the API this can be removed
                'StudentContactSpouseID'            => '',
                'StudentContactSpouseTitle'         => '',
                'StudentContactSpousePreferred'     => '',
                'StudentContactSpouseSurname'       => '',
                'StudentContactSpouseDefaultEmail'  => '',
                'StudentContactSpousePhoneActual'   => '',
                'StudentContactSpouseMobilePhone'   => '',
                'StudentContactSpouseOccupPhone'    => '',

                /* Address pieces
                 */
                'StudentContactAddress1' => (string)$contactTuple['StudentContactAddress1'],
                'StudentContactAddress2' => (string)$contactTuple['StudentContactAddress2'],
                'StudentContactAddress3' => (string)$contactTuple['StudentContactAddress3'],
                'StudentContactSuburb'   => (string)$contactTuple['StudentContactSuburb'],
                'StudentContactPostCode' => (string)$contactTuple['StudentContactPostCode'],
                'StudentContactState'    => (string)$contactTuple['StudentContactState'],

                /* PhoneActual, SilentPhoneFlag, PrimaryOnlyFlag, ContactType
                 * fields don't manifest for speece
                 */
                'StudentContactPhoneActual'     => '',
                'StudentContactPrimaryOnlyFlag' => '',
                'StudentContactSilentPhoneFlag' => (string)$contactTuple['StudentContactSilentPhoneFlag'],
                'StudentContactType'            => '',
            ];

            /* If we haven't seen this contact's id before, stack 'em up
             */
            foreach ([$contact1, $contact2] as $contact) {
                if (!isset($contactIdList[$contact['StudentContactID']]) && '' !== trim($contact['StudentContactID'])) {
                    $contactReturn[] = $contact;

                    $contactIdList[$contact['StudentContactID']] = true;
                }
            }
        }

        return $contactReturn;
    }

    /**
     * @inheritDoc
     */
    public function getParent(string $externalUserId): array
    {
        if (!$this->isExternalIdValid($externalUserId)) {
            return [];
        }

        $query =
            "
            SELECT      Contact.Title,
                        Contact.Preferred,
                        Contact.Surname,
                        CASE
                          WHEN Contact.DefaultEmailCode = 'O' THEN Contact.OccupEmail
                          ELSE Contact.Email
                        END AS Email,
                        Contact.OccupEmail,
                        Contact.DefaultEmailCode,
                        CASE
                            WHEN Addresses.SilentPhoneFlag = 0 THEN Addresses.Phone
                            ELSE ''
                        END AS HomePhone,
                        CASE
                            WHEN Addresses.SilentPhoneFlag = 0 THEN Contact.OccupPhone
                            ELSE ''
                        END AS WorkPhone,
                        CASE
                            WHEN Contact.SilentMobilePhoneFlag = 0 THEN Contact.MobilePhone
                            ELSE ''
                        END AS MobilePhone,
                        Contact.DirectoryIncludeFlag,
                        CASE
                            WHEN Spouse.DirectoryIncludeFlag = 1 THEN Contact.SpouseID
                            ELSE ''
                        END AS SpouseID,
                        CASE
                            WHEN Spouse.DirectoryIncludeFlag = 1 THEN Spouse.Title
                            ELSE ''
                        END AS SpouseTitle,
                        CASE
                            WHEN Spouse.DirectoryIncludeFlag = 1 THEN Spouse.Preferred
                            ELSE ''
                        END AS SpousePreferred,
                        CASE
                            WHEN Spouse.DirectoryIncludeFlag = 1 THEN Spouse.Surname
                            ELSE ''
                        END AS SpouseSurname,
                        CASE
                          WHEN Spouse.DirectoryIncludeFlag = 1 AND Spouse.DefaultEmailCode = 'O' THEN Spouse.OccupEmail
                          WHEN Spouse.DirectoryIncludeFlag = 1 AND Spouse.DefaultEmailCode != 'O' THEN Spouse.Email
                          ELSE ''
                        END AS SpouseEmail,
                        CASE
                            WHEN Spouse.DirectoryIncludeFlag = 1 THEN Spouse.OccupEmail
                            ELSE ''
                        END AS SpouseOccupEmail,
                        Spouse.DefaultEmailCode SpouseDefaultEmailCode,
                        CASE
                            WHEN Spouse.DirectoryIncludeFlag = 1 THEN Spouse.MobilePhone
                            ELSE ''
                        END AS SpouseMobilePhone,
                        CASE
                            WHEN Spouse.DirectoryIncludeFlag = 1 THEN Spouse.OccupPhone
                            ELSE ''
                        END AS SpouseWorkPhone,
                        Spouse.DirectoryIncludeFlag SpouseDirectoryIncludeFlag,
                   -- the following columns are extra for Amity
                        Addresses.Address1,
                        Addresses.Address2,
                        Addresses.Address3,
                        Addresses.Suburb,
                        Addresses.PostCode,
                        Addresses.State
            FROM        dbo.Community Contact
            LEFT JOIN   dbo.Addresses Addresses ON Contact.AddressID = Addresses.AddressID
            LEFT JOIN   dbo.Community Spouse ON Contact.SpouseID = Spouse.ID
            WHERE       Contact.ID = :externalId
        ";

        $parentQuery = $this->getConnector()->executeQuery($query, ['externalId' => $externalUserId]);

        // Try and fetch the requested parent

        if ($parent = $parentQuery->fetch()) {
            return $this->clearInvalidValues($parent);
        }

        // Couldn't find the parent

        return [];
    }
}
