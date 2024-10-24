<?php

/**
 * @copyright   Copyright 2024, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\Profile\Editor\Cli;

use Schoolbox\Application\CliTool;

class DefaultSettingsTool extends CliTool\App
{
    /**
     * Make sure to add your locking script to the Lock Inspector tool
     * const LOCK_NAMES in Inspector.php
     */
    public const MY_TOOL_LOCK_NAME = 'profileEditorDefaults';


    /**
     * Decide how long this should be based on your usage
     * You might have to adjust it, but it should cover the cost of an iteration
     */
    private const LOCK_TTL = 10;


    /**
     * --help
     *
     * Print help text for those who don't know how to use this tool
     */
    private const OPT_LONG_HELP = 'help';


    /**
     * Consts used - including the "self" pseudo-roletype
     */
    private const ROLETYPE_SELF = \Schoolbox\Admin\Profile\Editor\Payload::ROLETYPE_SELF;
    private const ROLETYPE_STUDENT = \Schoolbox\Role::STUDENT;
    private const ROLETYPE_PARENT = \Schoolbox\Role::PARENT;
    private const ROLETYPE_STAFF = \Schoolbox\Role::STAFF;


    /**
     * @inheritdoc
     */
    public function getLongOpts(): array
    {
        return [
            self::OPT_LONG_HELP,
        ];
    }


    /**
     * Run the cli tool
     */
    public function run(): void
    {
        if ($this->getOpt(self::OPT_LONG_HELP)) {
            echo <<<HELP

Profile Editor Default Availability/Visibility

Adds some default settings for the availability and visibility checkboxes that are
as close to the original profile's settings as I can infer.

Usage: php profileEditorDefaults.php -vvv --perform


HELP;
            return;
        }

        /* Decide how loudly to announce lock acquisition failure
         *
         * Simply using $lock = $this->acquireLock() will keep you safe, even if you don't
         * specifically check for acquisition
         */
        try {
            $lock = $this->acquireLock(self::MY_TOOL_LOCK_NAME, self::LOCK_TTL);
        } catch (\RuntimeException $e) {
            return;
        }

        try {
            $this->getLogger()->info('Profile Editor Default Settings.');
            $this->getLogger()->info('');

            $settingsIndex = $this->buildSettingsIndex();
            $fieldsIndex = $this->buildFieldsIndex();

            /* Prepare some oft-used queries
             */
            $availabilityCampusStatement = $this->getDatabaseInternal()->prepare("
                INSERT INTO profile_field_campus_availability
                (profile_field_id, campus_id)
                VALUES (:profileFieldId, :campusId)
            ");

            $visibilityCampusStatement = $this->getDatabaseInternal()->prepare("
                INSERT INTO profile_field_roletype_visibility
                (profile_field_id, roletype)
                VALUES (:profileFieldId, :roletype)
            ");

            /* Some meaningful diagnostic stats
             */
            $stats = [
                self::ROLETYPE_STUDENT => [
                    'availability' => 0,
                    'visibility' => 0
                ],
                self::ROLETYPE_STAFF => [
                    'availability' => 0,
                    'visibility' => 0
                ],
                self::ROLETYPE_PARENT => [
                    'availability' => 0,
                    'visibility' => 0
                ],
            ];

            /* For each field, insert their own special blend of Availability and Visibility
             */
            $totalFields = 0;

            foreach ($settingsIndex as $roletype => $fieldSettings) {
                $n = \count($fieldSettings);

                $totalFields += $n;

                $this->getLogger()->info(sprintf(
                    'Found %s %s elements to process.',
                    $n,
                    $roletype
                ));

                foreach ($fieldSettings as $fieldName => $fieldSetting) {
                    $this->getLogger()->debug('Refreshing lock.');
                    $this->refreshLock($lock);

                    $fieldId = $fieldsIndex[$roletype][$fieldName]['id'];
                    $fieldDisplayAs = $fieldsIndex[$roletype][$fieldName]['displayAs'];

                    /* We'll need an outer loop to insert new Availability rules for each campus
                     * for the roletypes listed in SA-18688
                     */
                    foreach ($fieldSetting['availability'] as $campusId) {
                        $this->getLogger()->debug('Refreshing lock.');
                        $this->refreshLock($lock);

                        if ($this->perform()) {
                            $availabilityCampusStatement->execute([
                                'profileFieldId' => $fieldId,
                                'campusId' => $campusId,
                            ]);
                        }

                        $this->getLogger()->debug(sprintf(
                            'Profile Field %s (%s): added availability for campus id %s',
                            $fieldDisplayAs,
                            $fieldName,
                            $campusId
                        ));

                        $stats[$roletype]['availability']++;
                    }

                    /* We'll need another outer loop to insert new Visibility rules for each field
                     */
                    foreach ($fieldSetting['visibility'] as $roletypeVisibility) {
                        $this->getLogger()->debug('Refreshing lock.');
                        $this->refreshLock($lock);

                        if ($this->perform()) {
                            $visibilityCampusStatement->execute([
                                'profileFieldId' => $fieldId,
                                'roletype' => $roletypeVisibility,
                            ]);
                        }

                        $this->getLogger()->debug(sprintf(
                            'Profile Field %s (%s): added visibility for roletype %s',
                            $fieldDisplayAs,
                            $fieldName,
                            $roletypeVisibility
                        ));

                        $stats[$roletype]['visibility']++;
                    }
                }
            }

            /* Dump out the stats
             */
            $this->getLogger()->info('');
            $this->getLogger()->info('Statistics');

            $this->getLogger()->info(sprintf(
                '    Total: %s fields itemised.',
                $totalFields
            ));

            $this->getLogger()->info('');

            foreach ([self::ROLETYPE_STUDENT, self::ROLETYPE_STAFF, self::ROLETYPE_PARENT] as $roletype) {
                foreach ($stats[$roletype] as $key => $value) {
                    $this->getLogger()->info(sprintf(
                        '    %s %s: %s',
                        $roletype,
                        $key,
                        $value
                    ));
                }
            }

            $this->getLogger()->info('');
        } catch (\Alaress\Lock\LockRefreshException $e) {
            /* Lock TTL expired; just leave politely
             */
            return;
        }

        /* Announce conclusion
         */
        if (!$this->perform()) {
            $this->getLogger()->info('Planning finished.');
        }

        /* Announce lock release
          */
        $this->getLogger()->debug('Releasing lock');

        $lock->release();
    }


    /**
     * Build a hash lookup of (roletype, settings) tuples for every campus in your instance
     *
     * @return array[]
     * @throws \PDOException
     */
    private function buildSettingsIndex(): array
    {
        /* Most fields are available for All Campuses or No Campuses.
         * We might as well collect a list of campus ids to start with.
         */
        $statement = $this->getDatabaseInternal()->prepare('SELECT id FROM luCampus');
        $statement->execute();

        $campusIds = [];

        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $campusIds[] = $row['id'];
        }

        /* A list of (roletype, field name, visibility) tuples
         * and (roletype, field name, availability)
         *
         * lol this is a chunky lookup
         */
        $roletypes = [
            self::ROLETYPE_STUDENT => [
                'portrait' => [
                    'availability' => $campusIds,
                    'visibility' => [self::ROLETYPE_SELF, self::ROLETYPE_PARENT, self::ROLETYPE_STAFF],
                ],
                /* NOTE: Decided by protect_medicalspecial_profile
                 * By default it's visible to nobody
                 */
                'StudentSpecialNeedsFlag' => [
                    'availability' => $campusIds,
                    'visibility' =>  [],
                ],
                'EnglishSecondFlag' => [
                    'availability' => $campusIds,
                    'visibility' =>  [self::ROLETYPE_STAFF],
                ],
                /* NOTE: Decided by protect_medicalspecial_profile
                 * By default it's visible to nobody
                 */
                'StudentMedicalAlert' => [
                    'availability' => $campusIds,
                    'visibility' =>  [],
                ],
                'ParentsSeparatedFlag' => [
                    'availability' => $campusIds,
                    'visibility' =>  [self::ROLETYPE_STAFF],
                ],
                /* NOTE: Decided by synergetic_community_alerts
                 * By default it's visible to nobody
                 */
                'AlertMessages' => [
                    'availability' => $campusIds,
                    'visibility' =>  [],
                ],
                /* AlertLegal: originally no visibility and ideally force schools to opt-in to reveal
                 * Historically speaking though, AlertLegal used to be classified the same way as
                 * AlertMessages so in this context we're just going to inherit the same idea as above
                 */
                'AlertLegal' => [
                    'availability' => $campusIds,
                    'visibility' =>  [],
                ],
                'countIndividualEducationPlans' => [
                    'availability' => $campusIds,
                    'visibility' =>  [self::ROLETYPE_STAFF],
                ],
                /* NOTE: Decided by synergetic_student_comment
                 * By default it's visible to nobody
                 */
                'Comment' => [
                    'availability' => $campusIds,
                    'visibility' =>  [],
                ],
                'positionTitle' => [
                    'availability' => $campusIds,
                    'visibility' =>  [self::ROLETYPE_SELF, self::ROLETYPE_STUDENT, self::ROLETYPE_PARENT, self::ROLETYPE_STAFF],
                ],
                'pronoun' => [
                    'availability' => $campusIds,
                    'visibility' =>  [self::ROLETYPE_SELF, self::ROLETYPE_STUDENT, self::ROLETYPE_PARENT, self::ROLETYPE_STAFF],
                ],
                'email' => [
                    'availability' => $campusIds,
                    'visibility' =>  [self::ROLETYPE_SELF, self::ROLETYPE_STUDENT, self::ROLETYPE_PARENT, self::ROLETYPE_STAFF],
                ],
                'externalId' => [
                    'availability' => $campusIds,
                    'visibility' =>  [self::ROLETYPE_SELF, self::ROLETYPE_PARENT, self::ROLETYPE_STAFF],
                ],
                'role' => [
                    'availability' => $campusIds,
                    'visibility' =>  [self::ROLETYPE_SELF, self::ROLETYPE_STUDENT, self::ROLETYPE_PARENT, self::ROLETYPE_STAFF],
                ],
                'StudentBirthDate' => [
                    'availability' => $campusIds,
                    'visibility' =>  [self::ROLETYPE_SELF, self::ROLETYPE_STAFF],
                ],
                'formTeacher' => [
                    'availability' => $campusIds,
                    'visibility' =>  [self::ROLETYPE_SELF, self::ROLETYPE_PARENT, self::ROLETYPE_STAFF],
                ],
                'yearLevel' => [
                    'availability' => $campusIds,
                    'visibility' =>  [self::ROLETYPE_SELF, self::ROLETYPE_PARENT, self::ROLETYPE_STAFF],
                ],
                'tutorTeacher' => [
                    'availability' => $campusIds,
                    'visibility' =>  [self::ROLETYPE_SELF, self::ROLETYPE_PARENT, self::ROLETYPE_STAFF],
                ],
                'coordTeacher' => [
                    'availability' => $campusIds,
                    'visibility' =>  [self::ROLETYPE_SELF, self::ROLETYPE_PARENT, self::ROLETYPE_STAFF],
                ],
                'StudentHouseDescription' => [
                    'availability' => $campusIds,
                    'visibility' =>  [self::ROLETYPE_SELF, self::ROLETYPE_PARENT, self::ROLETYPE_STAFF],
                ],
                'StudentTutorDescription' => [
                    'availability' => $campusIds,
                    'visibility' =>  [self::ROLETYPE_SELF, self::ROLETYPE_PARENT, self::ROLETYPE_STAFF],
                ],
                'hohTeacher' => [
                    'availability' => $campusIds,
                    'visibility' =>  [self::ROLETYPE_SELF, self::ROLETYPE_PARENT, self::ROLETYPE_STAFF],
                ],
                'residentialHouse' => [
                    'availability' => $campusIds,
                    'visibility' =>  [self::ROLETYPE_SELF, self::ROLETYPE_PARENT, self::ROLETYPE_STAFF],
                ],
                'campusCollection' => [
                    'availability' => $campusIds,
                    'visibility' =>  [self::ROLETYPE_SELF, self::ROLETYPE_PARENT, self::ROLETYPE_STAFF],
                ],
                'StudentForm' => [
                    'availability' => $campusIds,
                    'visibility' =>  [self::ROLETYPE_SELF, self::ROLETYPE_PARENT, self::ROLETYPE_STAFF],
                ],
                'boarderType' => [
                    'availability' => $campusIds,
                    'visibility' =>  [self::ROLETYPE_SELF, self::ROLETYPE_PARENT, self::ROLETYPE_STAFF],
                ],
                'StudentGovernmentStudentNumber' => [
                    'availability' => $campusIds,
                    'visibility' =>  [self::ROLETYPE_SELF, self::ROLETYPE_PARENT, self::ROLETYPE_STAFF],
                ],
                'StudentStudiesCode' => [
                    'availability' => $campusIds,
                    'visibility' =>  [self::ROLETYPE_SELF, self::ROLETYPE_PARENT, self::ROLETYPE_STAFF],
                ],
                'pastoralCounts' => [
                    'availability' => $campusIds,
                    'visibility' =>  [self::ROLETYPE_SELF, self::ROLETYPE_PARENT, self::ROLETYPE_STAFF],
                ],
            ],
            self::ROLETYPE_STAFF => [
                /* NOTE: Decided by protect_teacher_photo
                 * By default it's visible to nobody
                 */
                'portrait' => [
                    'availability' => $campusIds,
                    'visibility' =>  [],
                ],
                'externalId' => [
                    'availability' => [],
                    'visibility' =>  [],
                ],
                'yearLevel' => [
                    'availability' => [],
                    'visibility' =>  [],
                ],
                'SchoolStaffCode' => [
                    'availability' => [],
                    'visibility' =>  [],
                ],
                'positionTitle' => [
                    'availability' => $campusIds,
                    'visibility' =>  [self::ROLETYPE_SELF, self::ROLETYPE_STUDENT, self::ROLETYPE_PARENT, self::ROLETYPE_STAFF],
                ],
                'pronoun' => [
                    'availability' => $campusIds,
                    'visibility' =>  [self::ROLETYPE_SELF, self::ROLETYPE_STUDENT, self::ROLETYPE_PARENT, self::ROLETYPE_STAFF],
                ],
                'email' => [
                    'availability' => $campusIds,
                    'visibility' =>  [self::ROLETYPE_SELF, self::ROLETYPE_STAFF],
                ],
                'role' => [
                    'availability' => [],
                    'visibility' =>  [],
                ],
                /* NOTE: Decided by report_staff_access
                 * By default it's visible to self, staff
                 */
                'StaffDepartment' => [
                    'availability' => $campusIds,
                    'visibility' =>  [self::ROLETYPE_SELF, self::ROLETYPE_STAFF],
                ],
                /* NOTE: Decided by report_staff_access
                 * By default it's visible to self, staff
                 */
                'StaffCategoryDescription' => [
                    'availability' => $campusIds,
                    'visibility' =>  [self::ROLETYPE_SELF, self::ROLETYPE_STAFF],
                ],
                'StaffBuilding' => [
                    'availability' => $campusIds,
                    'visibility' =>  [self::ROLETYPE_SELF, self::ROLETYPE_STAFF],
                ],
                'StaffRoom' => [
                    'availability' => $campusIds,
                    'visibility' =>  [self::ROLETYPE_SELF, self::ROLETYPE_STAFF],
                ],
                /* NOTE: Decided by report_staff_access
                 * By default it's visible to self, staff
                 */
                'StaffExtension' => [
                    'availability' => $campusIds,
                    'visibility' =>  [self::ROLETYPE_SELF, self::ROLETYPE_STAFF],
                ],
                /* NOTE: Decided by report_staff_access
                 * By default it's visible to self, staff
                 */
                'StaffDefaultMobilePhone' => [
                    'availability' => $campusIds,
                    'visibility' =>  [self::ROLETYPE_SELF, self::ROLETYPE_STAFF],
                ],
                /* NOTE: Decided by report_staff_access
                 * By default it's visible to self, staff
                 */
                'campusCollection' => [
                    'availability' => $campusIds,
                    'visibility' =>  [self::ROLETYPE_SELF, self::ROLETYPE_STAFF],
                ],
                'schoolHouse' => [
                    'availability' => [],
                    'visibility' =>  [],
                ],
                'residentialHouse' => [
                    'availability' => [],
                    'visibility' =>  [],
                ],
            ],
            self::ROLETYPE_PARENT => [
                'portrait' => [
                    'availability' => $campusIds,
                    'visibility' =>  [self::ROLETYPE_SELF, self::ROLETYPE_STUDENT, self::ROLETYPE_PARENT, self::ROLETYPE_STAFF],
                ],
                'externalId' => [
                    'availability' => [],
                    'visibility' =>  [],
                ],
                'positionTitle' => [
                    'availability' => $campusIds,
                    'visibility' =>  [self::ROLETYPE_SELF, self::ROLETYPE_STUDENT, self::ROLETYPE_PARENT, self::ROLETYPE_STAFF],
                ],
                'pronoun' => [
                    'availability' => $campusIds,
                    'visibility' =>  [self::ROLETYPE_SELF, self::ROLETYPE_STUDENT, self::ROLETYPE_PARENT, self::ROLETYPE_STAFF],
                ],
                /* NOTE: Decided by restrict_parent_emails
                 * By default it's visible to self, staff
                 */
                'email' => [
                    'availability' => $campusIds,
                    'visibility' =>  [self::ROLETYPE_SELF, self::ROLETYPE_STAFF],
                ],
                'campusCollection' => [
                    'availability' => [],
                    'visibility' =>  [],
                ],
                /* NOTE: Decided by restrict_parent_emails
                 * By default it's visible to self, staff
                 */
                'role' => [
                    'availability' => [],
                    'visibility' =>  [self::ROLETYPE_SELF, self::ROLETYPE_STAFF],
                ],
                'HomePhone' => [
                    'availability' => $campusIds,
                    'visibility' =>  [self::ROLETYPE_SELF, self::ROLETYPE_STAFF],
                ],
                'MobilePhone' => [
                    'availability' => $campusIds,
                    'visibility' =>  [self::ROLETYPE_SELF, self::ROLETYPE_STAFF],
                ],
                'WorkPhone' => [
                    'availability' => $campusIds,
                    'visibility' =>  [self::ROLETYPE_SELF, self::ROLETYPE_STAFF],
                ],
                'SpouseEmail' => [
                    'availability' => $campusIds,
                    'visibility' =>  [self::ROLETYPE_SELF, self::ROLETYPE_STAFF],
                ],
                'SpouseMobilePhone' => [
                    'availability' => $campusIds,
                    'visibility' =>  [self::ROLETYPE_SELF, self::ROLETYPE_STAFF],
                ],
                'spouseName' => [
                    'availability' => $campusIds,
                    'visibility' =>  [self::ROLETYPE_SELF, self::ROLETYPE_STAFF],
                ],
            ]
        ];

        /* Config var equivalents
         *
         * These configs ultimately get superseded by the Profile Editor
         * but we'll use them to craft some idiosyncratic defaults
         */

        /* synergetic_community_alerts and protect_student_contacts
         *
         * The combined effect of these two config vars determines who can see stuff.
         * See SA-18747 for a truth table (note this might move to confluence soon)
         *
         * AlertLegal has now been separated from AlertMessages.
         * This used to be thrown together with all the other alerts, but we've split them in Profile Editor
         * for more granular control. As far as defaults go, use the same ones as above
         */
        $protectStudentContacts = $this->getConfig()->get('protect_student_contacts');
        $synergeticCommunityAlerts = $this->getConfig()->get('synergetic_community_alerts');

        /* sigh $protectStudentContacts is a string numeral,
         * but $synergeticCommunityAlerts is a boolean.
         */
        if ('0' === $protectStudentContacts) {
            if ($synergeticCommunityAlerts) {
                $roletypes[self::ROLETYPE_STUDENT]['AlertMessages']['visibility'] = [self::ROLETYPE_STAFF, self::ROLETYPE_PARENT];
                $roletypes[self::ROLETYPE_STUDENT]['AlertLegal']['visibility'] = [self::ROLETYPE_STAFF, self::ROLETYPE_PARENT];
            } else {
                $roletypes[self::ROLETYPE_STUDENT]['AlertMessages']['visibility'] = [];
                $roletypes[self::ROLETYPE_STUDENT]['AlertLegal']['visibility'] = [];
            }
        } elseif ('1' === $protectStudentContacts) {
            if ($synergeticCommunityAlerts) {
                $roletypes[self::ROLETYPE_STUDENT]['AlertMessages']['visibility'] = [self::ROLETYPE_STAFF];
                $roletypes[self::ROLETYPE_STUDENT]['AlertLegal']['visibility'] = [self::ROLETYPE_STAFF];
            } else {
                $roletypes[self::ROLETYPE_STUDENT]['AlertMessages']['visibility'] = [];
                $roletypes[self::ROLETYPE_STUDENT]['AlertLegal']['visibility'] = [];
            }
        } elseif ('2' === $protectStudentContacts) {
            if ($synergeticCommunityAlerts) {
                $roletypes[self::ROLETYPE_STUDENT]['AlertMessages']['visibility'] = [self::ROLETYPE_STAFF, self::ROLETYPE_PARENT];
                $roletypes[self::ROLETYPE_STUDENT]['AlertLegal']['visibility'] = [self::ROLETYPE_STAFF, self::ROLETYPE_PARENT];
            } else {
                $roletypes[self::ROLETYPE_STUDENT]['AlertMessages']['visibility'] = [];
                $roletypes[self::ROLETYPE_STUDENT]['AlertLegal']['visibility'] = [];
            }
        }

        /* protect_medicalspecial_profile
         *
         * Be careful with the double negative here: !protect_medicalspecial_profile allows people to see
         * all the stuff. protect_medicalspecial_profile prevents them from seeing all that stuff.
         *
         * If falsy, show medical alerts to staff
         * If falsy, also show special needs to staff (idk why Special Needs historically classified as medical...)
         * Otherwise, hide it from everyone
         */
        if (!$this->getConfig()->get('protect_medicalspecial_profile')) {
            $roletypes[self::ROLETYPE_STUDENT]['StudentMedicalAlert']['visibility'] = [self::ROLETYPE_STAFF];
            $roletypes[self::ROLETYPE_STUDENT]['StudentSpecialNeedsFlag']['visibility'] = [self::ROLETYPE_STAFF];
        }

        /* synergetic_student_comment
         *
         * If truthy, show student comments to staff
         * Otherwise, hide it from everyone
         */
        if ($this->getConfig()->get('synergetic_student_comment')) {
            $roletypes[self::ROLETYPE_STUDENT]['Comment']['visibility'] = [self::ROLETYPE_STAFF];
        }

        /* report_staff_access
         *
         * If truthy, parents can see staff's email address, mobile, department, phone, campus
         * Otherwise only show them to staff
         */
        if ($this->getConfig()->get('report_staff_access')) {
            $roletypes[self::ROLETYPE_STAFF]['StaffExtension']['visibility'][] = self::ROLETYPE_PARENT;
            $roletypes[self::ROLETYPE_STAFF]['campusCollection']['visibility'][] = self::ROLETYPE_PARENT;
            $roletypes[self::ROLETYPE_STAFF]['StaffDepartment']['visibility'][] = self::ROLETYPE_PARENT;
            $roletypes[self::ROLETYPE_STAFF]['StaffCategoryDescription']['visibility'][] = self::ROLETYPE_PARENT;
            $roletypes[self::ROLETYPE_STAFF]['StaffDefaultMobilePhone']['visibility'][] = self::ROLETYPE_PARENT;
        }

        /* restrict_parent_emails
         *
         * Be careful with the double negative here: !restrict_parent_emails allows people to see
         * all the stuff. restrict_parent_emails prevents them from seeing all that stuff.
         *
         * If falsy, parents can see role, mail addresses, contact numbers, and speece, on another parent's profile
         * If falsy, parents can see their own home phone, mobile, work phone
         * Otherwise, only show them to self and staff
         */
        if (!$this->getConfig()->get('restrict_parent_emails')) {
            $roletypes[self::ROLETYPE_PARENT]['email']['visibility'][] = self::ROLETYPE_PARENT;
            $roletypes[self::ROLETYPE_PARENT]['role']['visibility'][] = self::ROLETYPE_PARENT;

            /* imho these should be hidden from other parents, regardless of this config setting
             */
            $roletypes[self::ROLETYPE_PARENT]['HomePhone']['visibility'][] = self::ROLETYPE_PARENT;
            $roletypes[self::ROLETYPE_PARENT]['WorkPhone']['visibility'][] = self::ROLETYPE_PARENT;
            $roletypes[self::ROLETYPE_PARENT]['MobilePhone']['visibility'][] = self::ROLETYPE_PARENT;

            /* Spouse stuff - further constrained by SpouseDirectoryIncludeFlag and getHideContacts
             * but those are implemented further away
             */
            $roletypes[self::ROLETYPE_PARENT]['spouseName']['visibility'][] = self::ROLETYPE_PARENT;
            $roletypes[self::ROLETYPE_PARENT]['SpouseEmail']['visibility'][] = self::ROLETYPE_PARENT;
            $roletypes[self::ROLETYPE_PARENT]['SpouseMobilePhone']['visibility'][] = self::ROLETYPE_PARENT;
        }

        /* protect_student_photo
         *
         * Note that Portrait.php already interprets this on its own so even if you chose different
         * visibility settings in the Profile Editor, you're still beholden to that config var.
         * That's a bit weird, but the defaults will indicate what that config means, even though
         * it won't override it.
         *
         * See Portrait::canViewImage()
         *
         * Anyway;
         * If truthy, students can't see other student's photos.
         * Otherwise, they can.
         */
        if (!$this->getConfig()->get('protect_student_photo')) {
            $roletypes[self::ROLETYPE_STUDENT]['portrait']['visibility'][] = self::ROLETYPE_STUDENT;
        }

        /* protect_teacher_photo
         *
         * Note that Portrait.php already interprets this on its own so even if you chose different
         * visibility settings in the Profile Editor, you're still beholden to that config var.
         * That's a bit weird, but the defaults will indicate what that config means, even though
         * it won't override it.
         *
         * Interestingly this config decides _from whom_ a staff profile picture is hidden.
         *
         * See Portrait::canViewImage()
         *
         * Anyway;
         * If truthy, find out how truthy it is.
         *    0 (none): visible to students, staff and parents
         *    1 (all): visible to nobody
         *    2 (students): visible to staff and parents only
         */
        if ('0' === $this->getConfig()->get('protect_teacher_photo')) {
            /* Protected from nobody; everyone can see it
             */
            $roletypes[self::ROLETYPE_STAFF]['portrait']['visibility'] = [self::ROLETYPE_SELF, self::ROLETYPE_STUDENT, self::ROLETYPE_PARENT, self::ROLETYPE_STAFF];
        } elseif ('1' === $this->getConfig()->get('protect_teacher_photo')) {
            /* Protected from EVERYBODY; nobody can see it (this is also the default case I've assigned)
             */
            $roletypes[self::ROLETYPE_STAFF]['portrait']['visibility'] = [];
        } elseif ('2' === $this->getConfig()->get('protect_teacher_photo')) {
            /* Protected from students only; staff and parents can see it.
             */
            $roletypes[self::ROLETYPE_STAFF]['portrait']['visibility'] = [self::ROLETYPE_SELF, self::ROLETYPE_PARENT, self::ROLETYPE_STAFF];
        }

        return $roletypes;
    }


    /**
     * Build a hash lookup of (roletype, field) tuples that we use for the field's
     * id and display name, for logging purposes
     *
     * @return array
     * @throws \PDOException
     */
    private function buildFieldsIndex(): array
    {
        /* Find all the fields' ids to build a relation; derive that from
         * the array key which its its name in the mega array below
         */
        $statement = $this->getDatabaseInternal()->prepare('SELECT id, roletype, field, display_as FROM profile_field');
        $statement->execute();

        $fields = [];

        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $fields[$row['roletype']][$row['field']] = [
                'id' => $row['id'],
                'displayAs' => $row['display_as']
            ];
        }

        return $fields;
    }


    /**
     * Does not require special file permissions
     *
     * @return bool|null
     */
    public function runAsRoot(): ?bool
    {
        return null;
    }
}
