<?php

/**
 * @copyright   Copyright 2024, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\Profile\Editor\Cli\Strategy;

use Alaress\Database\Connector\IConnector;
use Alaress\Database\Predicate\Builder;
use Psr\Log\LoggerInterface;
use Schoolbox\L10n\RosettaStone;

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
    private const SYSTEM_DOCUMENT_MANAGEMENT = 'documentmanagement';
    private const SYSTEM_GEOSNAPSHOT = 'geosnapshot';
    private const SYSTEM_EMAIL_STAFF = 'emailstaff';
    private const SYSTEM_EMAIL_PARENTS = 'emailparents';


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
     * @param bool            $perform
     * @param IConnector      $db
     * @param LoggerInterface $log
     * @param RosettaStone    $rosettaStone
     * @param array           $campusIds
     * @param string          $externalStaffPortal
     * @param string          $externalDocumentButtonEnable
     * @param string          $externalDb
     * @param string          $externalStaffPortal
     */
    public function __construct(
        private bool $perform,
        private IConnector $db,
        private LoggerInterface $log,
        private RosettaStone $rosettaStone,
        private array $campusIds,
        private string $externalStaffPortal,
        private string $externalDocumentButtonEnable,
        private string $externalDb,
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

                $profileButtonId = $this->createButton($candidate['row'], $roletype);

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
            VALUES (:profileButtonId, :campusId)
        ");

        $this->campusVisibilityInsertStatement = $this->db->prepare("
            INSERT INTO profile_button_campus_visibility (profile_button_id, campus_id)
            VALUES (:profileButtonId, :campusId)
        ");

        $this->roleAvailabilityInsertStatement = $this->db->prepare("
            INSERT INTO profile_button_role_availability (profile_button_id, role_id)
            VALUES (:profileButtonId, :roleId)
        ");

        $this->roleVisibilityInsertStatement = $this->db->prepare("
            INSERT INTO profile_button_role_visibility (profile_button_id, role_id)
            VALUES (:profileButtonId, :roleId)
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
        /* Cache some commonly-used answers here.
         */

        /* Student profile page used to show buttons to all role types,
         * but each viewer needed to have the View Student Profile perm
         */
        $allRoleIds = $this->fetchRoleIds([
            \Schoolbox\Role::STUDENT,
            \Schoolbox\Role::STAFF,
            \Schoolbox\Role::PARENT
        ]);

        /* Staff profile page used to show only to themselves and other staff
         * Parent details should only be visible to staff, I suppose
         */
        $staffRoleIds = $this->fetchRoleIds([\Schoolbox\Role::STAFF]);

        $candidates = [
            \Schoolbox\Role::STUDENT => [
                [
                    'row' => [
                        'roletype' => \Schoolbox\Role::STUDENT,
                        'type' => self::SYSTEM_EPORTFOLIO,
                        'sort_order' => 1,
                        'title' => 'ePortfolio',
                        'text' => 'ePortfolio',
                        'icon' => 'eportfolio',
                        'url' => '/eportfolio/[profileId]', // named confusingly, this is the viewee's user.id
                        'css' => null,
                        'target' => null,
                    ],
                    'availability' => [
                        'campus' => $this->campusIds,
                        'role' => $allRoleIds
                    ],
                    'visibility' => [
                        'campus' => $this->campusIds,
                        'role' => $allRoleIds
                    ],
                ],
                [
                    'row' => [
                        'roletype' => \Schoolbox\Role::STUDENT,
                        'type' => self::SYSTEM_DUEWORK,
                        'sort_order' => 2,
                        'title' => $this->rosettaStone->translate('dueWork', false, true),
                        'text' => $this->rosettaStone->translate('dueWork', false, true),
                        'icon' => 'due-work',
                        'url' => '', // derived dynamically with a routeLookup
                        'css' => null,
                        'target' => null,
                    ],
                    'availability' => [
                        'campus' => $this->campusIds,
                        'role' => $allRoleIds
                    ],
                    'visibility' => [
                        'campus' => $this->campusIds,
                        'role' => $allRoleIds
                    ],
                ],
                [
                    'row' => [
                        'roletype' => \Schoolbox\Role::STUDENT,
                        'type' => self::SYSTEM_GRADES,
                        'sort_order' => 3,
                        'title' => $this->rosettaStone->translate('grades', false, true),
                        'text' => $this->rosettaStone->translate('grades', false, true),
                        'icon' => 'approve',
                        'url' => '', // derived dynamically with a routeLookup
                        'css' => null,
                        'target' => null,
                    ],
                    'availability' => [
                        'campus' => $this->campusIds,
                        'role' => $allRoleIds
                    ],
                    'visibility' => [
                        'campus' => $this->campusIds,
                        'role' => $allRoleIds
                    ],
                ],
                [
                    'row' => [
                        'roletype' => \Schoolbox\Role::STUDENT,
                        'type' => self::SYSTEM_PASTORAL,
                        'sort_order' => 4,
                        'title' => $this->rosettaStone->translate('pastoralCare', false, true),
                        'text' => $this->rosettaStone->translate('pastoralCare', false, true),
                        'icon' => 'pastoral',
                        'url' => '', // derived dynamically with a routeLookup
                        'css' => null,
                        'target' => null,
                    ],
                    'availability' => [
                        'campus' => $this->campusIds,
                        'role' => $allRoleIds
                    ],
                    'visibility' => [
                        'campus' => $this->campusIds,
                        'role' => $allRoleIds
                    ],
                ],
                [
                    'row' => [
                        'roletype' => \Schoolbox\Role::STUDENT,
                        'type' => self::SYSTEM_CALENDAR,
                        'sort_order' => 5,
                        'title' => $this->rosettaStone->translate('calendar', false, true),
                        'text' => $this->rosettaStone->translate('calendar', false, true),
                        'icon' => 'calendar',
                        'url' => '', // derived dynamically with a routeLookup
                        'css' => null,
                        'target' => null,
                    ],
                    'availability' => [
                        'campus' => $this->campusIds,
                        'role' => $allRoleIds
                    ],
                    'visibility' => [
                        'campus' => $this->campusIds,
                        'role' => $allRoleIds
                    ],
                ],
                [
                    'row' => [
                        'roletype' => \Schoolbox\Role::STUDENT,
                        'type' => self::SYSTEM_TIMETABLE,
                        'sort_order' => 6,
                        'title' => 'Timetable',
                        'text' => 'Timetable',
                        'icon' => 'timetable',
                        'url' => '/timetable/[profileId]',
                        'css' => null,
                        'target' => null,
                    ],
                    'availability' => [
                        'campus' => $this->campusIds,
                        'role' => $allRoleIds
                    ],
                    'visibility' => [
                        'campus' => $this->campusIds,
                        'role' => $allRoleIds
                    ],
                ],
                [
                    'row' => [
                        'roletype' => \Schoolbox\Role::STUDENT,
                        'type' => self::SYSTEM_DOCUMENT_MANAGEMENT,
                        'sort_order' => 8,
                        'title' => $this->rosettaStone->translate('reports', false, true),
                        'text' => $this->rosettaStone->translate('reports', false, true),
                        'icon' => 'live-marks',
                        'url' => '', // derived dynamically with a routeLookup
                        'css' => null,
                        'target' => null,
                    ],
                    'availability' => [
                        'campus' => $this->campusIds,
                        'role' => $allRoleIds
                    ],
                    'visibility' => [
                        'campus' => $this->campusIds,
                        'role' => ('1' === $this->externalDocumentButtonEnable) ? $allRoleIds : []
                    ],
                ],
                [
                    'row' => [
                        'roletype' => \Schoolbox\Role::STUDENT,
                        'type' => self::SYSTEM_GEOSNAPSHOT,
                        'sort_order' => 9,
                        'title' => 'View Photos',
                        'text' => 'View Photos',
                        'icon' => 'geosnapshot',
                        'url' => '/geosnapshot/user/[profileId]', // derived dynamically with a routeLookup in addition
                        'css' => null,
                        'target' => null,
                    ],
                    'availability' => [
                        'campus' => $this->campusIds,
                        'role' => $allRoleIds
                    ],
                    'visibility' => [
                        'campus' => $this->campusIds,
                        'role' => $allRoleIds
                    ],
                ],
                /* There are emailAllStaff and emailAllParents buttons
                 * They're migrated along with everything else...pretty much
                 */
                [
                    'row' => [
                        'roletype' => \Schoolbox\Role::STUDENT,
                        'type' => self::SYSTEM_EMAIL_STAFF,
                        'sort_order' => 10,
                        'title' => 'Email All Teachers',
                        'text' => 'Email All Teachers',
                        'icon' => 'email',
                        'url' => '', // derived dynamically with a routeLookup in addition
                        'css' => null,
                        'target' => null,
                    ],
                    'availability' => [
                        'campus' => $this->campusIds,
                        'role' => $allRoleIds
                    ],
                    'visibility' => [
                        'campus' => $this->campusIds,
                        'role' => $allRoleIds
                    ],
                ],
                [
                    'row' => [
                        'roletype' => \Schoolbox\Role::STUDENT,
                        'type' => self::SYSTEM_EMAIL_PARENTS,
                        'sort_order' => 11,
                        'title' => 'Email Parents',
                        'text' => 'Email Parents',
                        'icon' => 'email',
                        'url' => '', // derived dynamically with a routeLookup in addition
                        'css' => null,
                        'target' => null,
                    ],
                    'availability' => [
                        'campus' => $this->campusIds,
                        'role' => $allRoleIds
                    ],
                    'visibility' => [
                        'campus' => $this->campusIds,
                        'role' => $allRoleIds
                    ],
                ],
            ],
            \Schoolbox\Role::STAFF => [
                [
                    'row' => [
                        'roletype' => \Schoolbox\Role::STAFF,
                        'type' => self::SYSTEM_EPORTFOLIO,
                        'sort_order' => 1,
                        'title' => 'ePortfolio',
                        'text' => 'ePortfolio',
                        'icon' => 'eportfolio',
                        'url' => '/eportfolio/[profileId]', // named confusingly, this is the viewee's user.id
                        'css' => null,
                        'target' => null,
                    ],
                    'availability' => [
                        'campus' => $this->campusIds,
                        'role' => $staffRoleIds
                    ],
                    'visibility' => [
                        'campus' => $this->campusIds,
                        'role' => $staffRoleIds
                    ],
                ],
                [
                    'row' => [
                        'roletype' => \Schoolbox\Role::STAFF,
                        'type' => self::SYSTEM_CALENDAR,
                        'sort_order' => 2,
                        'title' => $this->rosettaStone->translate('calendar', false, true),
                        'text' => $this->rosettaStone->translate('calendar', false, true),
                        'icon' => 'calendar',
                        'url' => '', // derived dynamically with a routeLookup
                        'css' => null,
                        'target' => null,
                    ],
                    'availability' => [
                        'campus' => $this->campusIds,
                        'role' => $staffRoleIds
                    ],
                    'visibility' => [
                        'campus' => $this->campusIds,
                        'role' => $staffRoleIds
                    ],
                ],
                [
                    'row' => [
                        'roletype' => \Schoolbox\Role::STAFF,
                        'type' => self::SYSTEM_TIMETABLE,
                        'sort_order' => 3,
                        'title' => 'Timetable',
                        'text' => 'Timetable',
                        'icon' => 'timetable',
                        'url' => '/timetable/[profileId]',
                        'css' => null,
                        'target' => null,
                    ],
                    'availability' => [
                        'campus' => $this->campusIds,
                        'role' => $staffRoleIds
                    ],
                    'visibility' => [
                        'campus' => $this->campusIds,
                        'role' => $staffRoleIds
                    ],
                ],
            ],
            \Schoolbox\Role::PARENT => [
                [
                    'row' => [
                        'roletype' => \Schoolbox\Role::PARENT,
                        'type' => self::SYSTEM_EPORTFOLIO,
                        'sort_order' => 1,
                        'title' => 'ePortfolio',
                        'text' => 'ePortfolio',
                        'icon' => 'eportfolio',
                        'url' => '/eportfolio/[profileId]/profile', // named confusingly, this is the viewee's user.id
                        'css' => null,
                        'target' => null,
                    ],
                    'availability' => [
                        'campus' => $this->campusIds,
                        'role' => $staffRoleIds
                    ],
                    'visibility' => [
                        'campus' => $this->campusIds,
                        'role' => $staffRoleIds
                    ],
                ],
                [
                    'row' => [
                        'roletype' => \Schoolbox\Role::PARENT,
                        'type' => self::SYSTEM_CALENDAR,
                        'sort_order' => 2,
                        'title' => $this->rosettaStone->translate('calendar', false, true),
                        'text' => $this->rosettaStone->translate('calendar', false, true),
                        'icon' => 'calendar',
                        'url' => '', // derived dynamically with a routeLookup
                        'css' => null,
                        'target' => null,
                    ],
                    'availability' => [
                        'campus' => $this->campusIds,
                        'role' => $staffRoleIds
                    ],
                    'visibility' => [
                        'campus' => $this->campusIds,
                        'role' => $staffRoleIds
                    ],
                ],
            ]
        ];

        /* Synweb was originally hardcoded into our templates, but only applies to a subset
         * of the fleet. Its hardcoding suggests it be classified as a System Button, but
         * its usage classifies it as a Custom Button. There isn't really a convenient place
         * for this transformation to exist, so I'll just append it here.
         *
         * Only spawn a synweb if you're on synergetic,
         * and if you have a meaningful staff portal host value
         */
        if (\External\IConnector::EXTERNAL_TYPE_SYNERGETIC === $this->externalDb && '' !== $this->externalStaffPortal) {
            $candidates[\Schoolbox\Role::STUDENT][] = [
                'row' => [
                    'roletype' => \Schoolbox\Role::STUDENT,
                    'type' => CustomButtons::CUSTOM,
                    'sort_order' => 7,
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
                    'role' => $allRoleIds
                ],
                'visibility' => [
                    'campus' => $this->campusIds,
                    'role' => $allRoleIds
                ],
            ];
        }

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
                $this->campusAvailabilityInsertStatement->execute([
                    'profileButtonId' => $profileButtonId,
                    'campusId' => $campusId
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
                $this->campusVisibilityInsertStatement->execute([
                    'profileButtonId' => $profileButtonId,
                    'campusId' => $campusId
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
                $this->roleAvailabilityInsertStatement->execute([
                    'profileButtonId' => $profileButtonId,
                    'roleId' => $roleId
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
                $this->roleVisibilityInsertStatement->execute([
                    'profileButtonId' => $profileButtonId,
                    'roleId' => $roleId
                ]);
            }
        }
    }


    /**
     * Fetch primary ids for roles, returning them in a stack,
     * corresponding to the roletypes specified in $roleType
     *
     * @param array $roleType
     * @return array
     */
    private function fetchRoleIds(array $roleType): array
    {
        $ids = [];

        $placeholders = Builder::getArrayPlaceholders($roleType, 'roletype');
        $params = Builder::getArrayParameters($roleType, 'roletype');

        $statement = $this->db->prepare("
            SELECT id
            FROM groups
            WHERE group_type IN {$placeholders}
        ");

        $statement->execute($params);

        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $ids[] = $row['id'];
        }

        return $ids;
    }
}
