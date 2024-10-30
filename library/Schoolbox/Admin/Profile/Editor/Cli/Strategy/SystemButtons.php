<?php

/**
 * @copyright   Copyright 2024, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\Profile\Editor\Cli\Strategy;

use Alaress\Database\Connector\IConnector;
use Psr\Log\LoggerInterface;
use Schoolbox\Admin\Profile\Editor\Cli\MigrateButtonsTool;

class SystemButtons
{
    /**
     * Corresponds to a secondary identifier in the schema so that we
     * can figure out custom buttons from system buttons, and also when
     * schools fill their buttons with junk, we'll be able to discern which
     * row is the ePortfolio button and so on.
     *
     * As we add system buttons, we can just add a new row; we don't
     * have to change the table schema, allowing us to make additions
     * in master. Otherwise we would be prohibited due to the proscription
     * on changing db schema in master releases.
     */
    private const SYSTEM_EPORTFOLIO = 'eportfolio';
    private const SYSTEM_DUEWORK = 'duework';
    private const SYSTEM_GRADES = 'grades';
    private const SYSTEM_PASTORAL = 'pastoral';
    private const SYSTEM_CALENDAR = 'calendar';
    private const SYSTEM_TIMETABLE = 'timetable';
    private const SYSTEM_PASTORAL_SYNERGETIC = 'pastoralsynergetic';
    private const SYSTEM_DOCUMENT_MANAGEMENT = 'documentmanagement';
    private const SYSTEM_GEOSNAPSHOT = 'geosnapshot';


    /* Some prepared statements that we'll often reuse
     */
    private \PDOStatement $createButtonStatement;
    private \PDOStatement $campusAvailabilityInsertStatement;
    private \PDOStatement $campusVisibilityInsertStatement;
    private \PDOStatement $roleAvailabilityInsertStatement;
    private \PDOStatement $roleVisibilityInsertStatement;


    /**
     * Constructor.
     *
     * Note that some system buttons depend on config vars
     *
     * @param bool $perform
     * @param IConnector $db
     * @param LoggerInterface $log
     * @param array $campusIds
     * @param array $roleIds
     * @param string $externalStaffPortal
     */
    public function __construct(
        private bool $perform,
        private IConnector $db,
        private LoggerInterface $log,
        private array $campusIds,
        private array $roleIds,
        private string $externalStaffPortal
    ) {
    }


    /**
     * Create system buttons as profile buttons
     *
     * @return void
     */
    public function migrate(): void
    {
        /* Set up some prepared statements, presuming that we'll go ahead with all this
         */
        $this->prepareStatements();

        /* Get a list of buttons to inspect - users can specify --system,
         * --custom, or both
         */
        $candidates = $this->generateCandidates();

        foreach ($candidates as $roletype => $data) {
            foreach ($data as $candidate) {
                $this->log->info(sprintf(
                    'Adding "%s" into %s roletype',
                    $candidate['row']['title'],
                    $roletype
                ));

                if ($this->perform) {
                    $this->createButtonStatement->execute($candidate['row']);

                    $profileButtonId = (int)$this->db->lastInsertId();

                    /* Assign relationships
                     *
                     * Technically speaking, all these hardcoded system buttons would have
                     * been available in every campus for every role, subject to further
                     * checks by routeLookupIfAuthorized calls. Schools have the option to
                     * nip it in the bud if they want to prevent viewers from seeing a
                     * button by unticking their role(s) as per the ui
                     */
                    $this->createCampusAvailability($profileButtonId, $candidate['availability']['campus']);
                    $this->createCampusVisibility($profileButtonId, $candidate['visibility']['campus']);
                    $this->createRoleAvailability($profileButtonId, $candidate['availability']['role']);
                    $this->createRoleVisibility($profileButtonId, $candidate['visibility']['role']);
                }
            }
        }
    }


    /**
     * We'll be reusing these prepared statements a lot
     *
     * @return void
     */
    private function prepareStatements(): void
    {
        $this->createButtonStatement = $this->db->prepare("
            INSERT INTO profile_button (roletype, type, sort_order, title, text, icon, url, css, target)
            VALUES (:roletype, :type, :sortOrder, :title, :text, :icon, :url, :css, :target)
        ");

        $this->campusAvailabilityInsertStatement = $this->db->prepare("
            INSERT INTO profile_button_campus_availability (profile_button_id, campus_id)
            VALUES (?, ?)
        ");

        $this->campusVisibilityInsertStatement = $this->db->prepare("
            INSERT INTO profile_button_campus_visibility (profile_button_id, campus_id)
            VALUES (?, ?)
        ");

        $this->roleAvailabilityInsertStatement = $this->db->prepare("
            INSERT INTO profile_button_role_availability (profile_button_id, role_id)
            VALUES (?, ?)
        ");

        $this->roleVisibilityInsertStatement = $this->db->prepare("
            INSERT INTO profile_button_role_visibility (profile_button_id, role_id)
            VALUES (?, ?)
        ");
    }


    /**
     * These were hardcoded into each of our templates; we'll migrate them into
     * the main profile_button system just like the custom buttons
     *
     * @return array
     */
    private function generateCandidates(): array
    {
        $candidates = [
            MigrateButtonsTool::ROLETYPE_STUDENT => [
                [
                    'row' => [
                        'roletype' => MigrateButtonsTool::ROLETYPE_STUDENT,
                        'type' => self::SYSTEM_EPORTFOLIO,
                        'sortOrder' => 1,
                        'title' => 'ePortfolio',
                        'text' => 'ePortfolio',
                        'icon' => 'eportfolio',
                        'url' => '/eportfolio/[profileId]', // named confusingly, this is the viewee's user.id
                        'css' => null,
                        'target' => null,
                    ],
                    'availability' => [
                        'campus' => $this->campusIds,
                        'role' => $this->roleIds
                    ],
                    'visibility' => [
                        'campus' => $this->campusIds,
                        'role' => $this->roleIds
                    ],
                ],
                [
                    'row' => [
                        'roletype' => MigrateButtonsTool::ROLETYPE_STUDENT,
                        'type' => self::SYSTEM_DUEWORK,
                        'sortOrder' => 2,
                        'title' => 'Due Work',
                        'text' => 'Due Work',
                        'icon' => 'due-work',
                        'url' => '', // derived dynamically with a routeLookup
                        'css' => null,
                        'target' => null,
                    ],
                    'availability' => [
                        'campus' => $this->campusIds,
                        'role' => $this->roleIds
                    ],
                    'visibility' => [
                        'campus' => $this->campusIds,
                        'role' => $this->roleIds
                    ],
                ],
                [
                    'row' => [
                        'roletype' => MigrateButtonsTool::ROLETYPE_STUDENT,
                        'type' => self::SYSTEM_GRADES,
                        'sortOrder' => 3,
                        'title' => 'Grades',
                        'text' => 'Grades',
                        'icon' => 'tick',
                        'url' => '', // derived dynamically with a routeLookup
                        'css' => null,
                        'target' => null,
                    ],
                    'availability' => [
                        'campus' => $this->campusIds,
                        'role' => $this->roleIds
                    ],
                    'visibility' => [
                        'campus' => $this->campusIds,
                        'role' => $this->roleIds
                    ],
                ],
                [
                    'row' => [
                        'roletype' => MigrateButtonsTool::ROLETYPE_STUDENT,
                        'type' => self::SYSTEM_PASTORAL,
                        'sortOrder' => 4,
                        'title' => 'Pastoral Care',
                        'text' => 'Pastoral Care',
                        'icon' => 'pastoral',
                        'url' => '', // derived dynamically with a routeLookup
                        'css' => null,
                        'target' => null,
                    ],
                    'availability' => [
                        'campus' => $this->campusIds,
                        'role' => $this->roleIds
                    ],
                    'visibility' => [
                        'campus' => $this->campusIds,
                        'role' => $this->roleIds
                    ],
                ],
                [
                    'row' => [
                        'roletype' => MigrateButtonsTool::ROLETYPE_STUDENT,
                        'type' => self::SYSTEM_CALENDAR,
                        'sortOrder' => 5,
                        'title' => 'Calendar',
                        'text' => 'Calendar',
                        'icon' => 'calendar',
                        'url' => '', // derived dynamically with a routeLookup
                        'css' => null,
                        'target' => null,
                    ],
                    'availability' => [
                        'campus' => $this->campusIds,
                        'role' => $this->roleIds
                    ],
                    'visibility' => [
                        'campus' => $this->campusIds,
                        'role' => $this->roleIds
                    ],
                ],
                [
                    'row' => [
                        'roletype' => MigrateButtonsTool::ROLETYPE_STUDENT,
                        'type' => self::SYSTEM_TIMETABLE,
                        'sortOrder' => 6,
                        'title' => 'Timetable',
                        'text' => 'Timetable',
                        'icon' => 'timetable',
                        'url' => '', // derived dynamically with a routeLookup
                        'css' => null,
                        'target' => null,
                    ],
                    'availability' => [
                        'campus' => $this->campusIds,
                        'role' => $this->roleIds
                    ],
                    'visibility' => [
                        'campus' => $this->campusIds,
                        'role' => $this->roleIds
                    ],
                ],
                [
                    'row' => [
                        'roletype' => MigrateButtonsTool::ROLETYPE_STUDENT,
                        'type' => self::SYSTEM_PASTORAL_SYNERGETIC,
                        'sortOrder' => 7,
                        'title' => 'Synweb',
                        'text' => 'Synweb',
                        'icon' => 'synergetic',
                        'url' => sprintf(
                            '%s/stud.aspx?id=[profileExternalId]',
                            $this->externalStaffPortal
                        ),
                        'css' => null,
                        'target' => '_blank',
                    ],
                    'availability' => [
                        'campus' => $this->campusIds,
                        'role' => $this->roleIds
                    ],
                    'visibility' => [
                        'campus' => $this->campusIds,
                        'role' => $this->roleIds
                    ],
                ],
                [
                    'row' => [
                        'roletype' => MigrateButtonsTool::ROLETYPE_STUDENT,
                        'type' => self::SYSTEM_DOCUMENT_MANAGEMENT,
                        'sortOrder' => 8,
                        'title' => 'Documents',
                        'text' => 'Documents',
                        'icon' => 'document',
                        'url' => '', // derived dynamically with a routeLookup
                        'css' => null,
                        'target' => null,
                    ],
                    'availability' => [
                        'campus' => $this->campusIds,
                        'role' => $this->roleIds
                    ],
                    'visibility' => [
                        'campus' => $this->campusIds,
                        'role' => $this->roleIds
                    ],
                ],
                [
                    'row' => [
                        'roletype' => MigrateButtonsTool::ROLETYPE_STUDENT,
                        'type' => self::SYSTEM_GEOSNAPSHOT,
                        'sortOrder' => 9,
                        'title' => 'GeoSnapShot',
                        'text' => 'GeoSnapShot',
                        'icon' => 'geosnapshot',
                        'url' => '/geosnapshot/user/[profileId]', // derived dynamically with a routeLookup in addition
                        'css' => null,
                        'target' => null,
                    ],
                    'availability' => [
                        'campus' => $this->campusIds,
                        'role' => $this->roleIds
                    ],
                    'visibility' => [
                        'campus' => $this->campusIds,
                        'role' => $this->roleIds
                    ],
                ],
                /* TODO There are emailAllStaff and emailAllParents buttons
                 *  Not really sure how I'll deal wtih those just yet
                 */
            ],
            MigrateButtonsTool::ROLETYPE_STAFF => [],
            MigrateButtonsTool::ROLETYPE_PARENT => [],
        ];

        return $candidates;
    }


    /**
     * Creates a new button in profile_button, associated with $roletype,
     * returning you its autoincremented id so you can re-establish relationships
     * with this new button
     *
     * @param array $candidate
     * @param string $roletype
     * @return int|null
     */
    private function createButton(array $candidate, string $roletype): ?int
    {
        $this->log->info(sprintf(
            'Migrating "%s" to %s roletype',
            $candidate['title'],
            $roletype
        ));

        if ($this->perform) {
            $this->createButtonStatement->execute([
                'roletype' => $candidate['roletype'],
                'type' => $candidate['type'],
                'sortOrder' => $candidate['sort_order'],
                'title' => $candidate['title'],
                'text' => $candidate['text'],
                'icon' => $candidate['icon'],
                'url' => $candidate['url'],
                'css' => $candidate['css'],
                'target' => $candidate['target'],
            ]);

            /* The new button's id - we just made this a moment ago
             */
            return (int)$this->db->lastInsertId();
        }

        return null;
    }


    /**
     * Recreate a relationship using $candidate's existing nodes and this new $profileButtonId
     *
     * @param int|null $profileButtonId
     * @param array $campusIds
     * @return void
     */
    private function createCampusAvailability(?int $profileButtonId, array $campusIds): void
    {
        if ($this->perform) {
            foreach ($campusIds as $campusId) {
                /* TODO make this a key-value tuple
                 */
                $this->campusAvailabilityInsertStatement->execute([
                    $profileButtonId, $campusId
                ]);
            }
        }
    }


    /**
     * Recreate a relationship using $candidate's existing nodes and this new $profileButtonId
     *
     * @param int|null $profileButtonId
     * @param array $campusIds
     * @return void
     */
    private function createCampusVisibility(?int $profileButtonId, array $campusIds): void
    {
        if ($this->perform) {
            foreach ($campusIds as $campusId) {
                /* TODO make this a key-value tuple
                 */
                $this->campusVisibilityInsertStatement->execute([
                    $profileButtonId, $campusId
                ]);
            }
        }
    }


    /**
     * Recreate a relationship using $candidate's existing nodes and this new $profileButtonId
     *
     * @param int|null $profileButtonId
     * @param array $roleIds
     * @return void
     */
    private function createRoleAvailability(?int $profileButtonId, array $roleIds): void
    {
        if ($this->perform) {
            foreach ($roleIds as $roleId) {
                /* TODO make this a key-value tuple
                 */
                $this->roleAvailabilityInsertStatement->execute([
                    $profileButtonId, $roleId
                ]);
            }
        }
    }


    /**
     * Recreate a relationship using $candidate's existing nodes and this new $profileButtonId
     *
     * @param int|null $profileButtonId
     * @param array $roleIds
     * @return void
     */
    private function createRoleVisibility(?int $profileButtonId, array $roleIds): void
    {
        if ($this->perform) {
            foreach ($roleIds as $roleId) {
                /* TODO make this a key-value tuple
                 */
                $this->roleVisibilityInsertStatement->execute([
                    $profileButtonId, $roleId
                ]);
            }
        }
    }
}
