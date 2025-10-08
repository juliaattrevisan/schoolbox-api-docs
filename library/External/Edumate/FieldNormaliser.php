<?php

namespace External\Edumate;

/**
 * A $student record consists of multiple (key, value) tuples
 * where the keys are in all uppercase, like this:
 *
    [FIRSTNAME] => Paul
    [PREFERREDNAME] =>
    [LASTNAME] => Muad'dib
    [SYNERGY_ID] => 12345
    [YEARLEVEL] => 07
    [HOUSE] => Atreides
    [STUDENTMEDICALALERT] =>
    [STUDENTSPECIALNEEDSFLAG] =>
    [ENGLISHSECONDFLAG] =>
    [STUDENTTUTORDESCRIPTION] =>

 * This is totally inconsistent with the rest of the world, because db2
 * works in a special way. To make it consistent with the rest of the world
 * we need to change the case back to camelcase manually.
 *
 * In Edumate/Class.php we give out specifically-lowercased array keys and have
 * been doing so for a long time, causing other features to rely on it being in
 * lowercase rather than camelcased, adding to this db2 nastyness. I standardised
 * both as best I could.
 */

class FieldNormaliser
{
    /* These are case-sensitive fields from literally everywhere
     */
    private const CASE_SENSITIVE_FIELDS = [
        'StudentID',
        'StudentGiven1',
        'StudentSurname',
        'StudentBirthDate',
        'StudentHouseDescription',
        'StudentYearLevel',
        'TeacherFormID',
        'StudentTutor',
        'StudentTutorDescription',
        'StudentHeadOfHouse',
        'StudentForm',
        'StudentFormStaffName',
        'StudentEmergencyName',
        'StudentEmergencyPhone',
        'StudentMobilePhone',
        'EnglishSecondFlag',
        'ParentsSeparatedFlag',
        'StudentMedicalAlert',
        'StudentSpecialNeedsFlag',
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
        'StudentContactType',
    ];


    /**
     * Convert uppercase array keys to camelcase array keys from a known list
     *
     * @param array $record
     * @return array
     */
    public function normalise(array $record): array
    {
        foreach (self::CASE_SENSITIVE_FIELDS as $field) {
            $upper = strtoupper($field);
            $lower = strtolower($field);

            /* Accommodate db2 nastiness due to lowercased expectations in Edumate/Class.php
             * that we've built upon for a long time
             */
            if (array_key_exists($upper, $record)) {
                $record[$field] = $record[$upper];
                unset($record[$upper]);
            }

            if (array_key_exists($lower, $record)) {
                $record[$field] = $record[$lower];
                unset($record[$lower]);
            }
        }

        return $record;
    }
}
