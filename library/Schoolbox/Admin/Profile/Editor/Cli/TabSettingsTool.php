<?php

/**
 * @copyright   Copyright 2024, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\Profile\Editor\Cli;

use Schoolbox\Application\CliTool;

class TabSettingsTool extends CliTool\App
{
    /**
     * Make sure to add your locking script to the Lock Inspector tool
     * const LOCK_NAMES in Inspector.php
     */
    public const LOCK_NAME = 'profileEditorTabDefaults';


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

Profile Editor Tabs: Default Availability/Visibility

Adds some default settings for the availability and visibility checkboxes that are
as close to the original profile's settings as I can infer.

Usage: php profileEditorTabDefaults.php -vvv --perform


HELP;
            return;
        }

        /* Decide how loudly to announce lock acquisition failure
         *
         * Simply using $lock = $this->acquireLock() will keep you safe, even if you don't
         * specifically check for acquisition
         */
        try {
            $lock = $this->acquireLock(self::LOCK_NAME, self::LOCK_TTL);
        } catch (\RuntimeException $e) {
            return;
        }

        try {
            $this->getLogger()->info('Profile Editor Tabs: Default Settings.');
            $this->getLogger()->info('');

            $settingsIndex = $this->buildSettingsIndex();
            $tabsIndex = $this->buildTabsIndex();

            /* Prepare some oft-used queries
             */
            $availabilityCampusStatement = $this->getDatabaseInternal()->prepare("
                INSERT INTO profile_tab_campus_availability
                (profile_tab_id, campus_id)
                VALUES (:profileTabId, :campusId)
            ");

            $visibilityCampusStatement = $this->getDatabaseInternal()->prepare("
                INSERT INTO profile_tab_roletype_visibility
                (profile_tab_id, roletype)
                VALUES (:profileTabId, :roletype)
            ");

            /* Some meaningful diagnostic stats
             */
            $stats = [
                self::ROLETYPE_STUDENT => [
                    'availability' => 0,
                    'visibility' => 0
                ],
            ];

            /* For each tab, insert their own special blend of Availability and Visibility
             */
            $totalFields = 0;

            foreach ($settingsIndex as $roletype => $tabSettings) {
                $n = \count($tabSettings);

                $totalFields += $n;

                $this->getLogger()->info(sprintf(
                    'Found %s %s elements to process.',
                    $n,
                    $roletype
                ));

                foreach ($tabSettings as $tabName => $tabSetting) {
                    $this->getLogger()->debug('Refreshing lock.');
                    $this->refreshLock($lock);

                    $tabId = $tabsIndex[$roletype][$tabName]['id'];
                    $tabDisplayAs = $tabsIndex[$roletype][$tabName]['displayAs'];

                    /* We'll need an outer loop to insert new Availability rules for each campus
                     * for the roletypes listed in SA-18688
                     */
                    foreach ($tabSetting['availability'] as $campusId) {
                        $this->getLogger()->debug('Refreshing lock.');
                        $this->refreshLock($lock);

                        if ($this->perform()) {
                            $availabilityCampusStatement->execute([
                                'profileTabId' => $tabId,
                                'campusId' => $campusId,
                            ]);
                        }

                        $this->getLogger()->debug(sprintf(
                            'Profile Tab %s (%s): added availability for campus id %s',
                            $tabDisplayAs,
                            $tabName,
                            $campusId
                        ));

                        $stats[$roletype]['availability']++;
                    }

                    /* We'll need another outer loop to insert new Visibility rules for each field
                     */
                    foreach ($tabSetting['visibility'] as $roletypeVisibility) {
                        $this->getLogger()->debug('Refreshing lock.');
                        $this->refreshLock($lock);

                        if ($this->perform()) {
                            $visibilityCampusStatement->execute([
                                'profileTabId' => $tabId,
                                'roletype' => $roletypeVisibility,
                            ]);
                        }

                        $this->getLogger()->debug(sprintf(
                            'Profile Tab %s (%s): added visibility for roletype %s',
                            $tabDisplayAs,
                            $tabName,
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
                '    Total: %s tabs itemised.',
                $totalFields
            ));

            $this->getLogger()->info('');

            foreach ($stats[self::ROLETYPE_STUDENT] as $key => $value) {
                $this->getLogger()->info(sprintf(
                    '    %s %s: %s',
                    $roletype,
                    $key,
                    $value
                ));
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

        /* A list of (roletype, tab, visibility) tuples
         * and (roletype, tab, availability)
         */
        return [
            self::ROLETYPE_STUDENT => [
                'activity' => [
                    'availability' => $campusIds,
                    'visibility' => [self::ROLETYPE_SELF, self::ROLETYPE_PARENT, self::ROLETYPE_STAFF],
                ],
                'goals' => [
                    'availability' => $campusIds,
                    'visibility' => [self::ROLETYPE_SELF, self::ROLETYPE_PARENT, self::ROLETYPE_STAFF],
                ],
                'achievements' => [
                    'availability' => $campusIds,
                    'visibility' => [self::ROLETYPE_SELF, self::ROLETYPE_PARENT, self::ROLETYPE_STAFF],
                ],
                'classes' => [
                    'availability' => $campusIds,
                    'visibility' => [self::ROLETYPE_SELF, self::ROLETYPE_PARENT, self::ROLETYPE_STAFF],
                ],
                'groups' => [
                    'availability' => $campusIds,
                    'visibility' => [self::ROLETYPE_SELF, self::ROLETYPE_PARENT, self::ROLETYPE_STAFF],
                ],
                'contacts' => [
                    'availability' => $campusIds,
                    'visibility' => [self::ROLETYPE_SELF, self::ROLETYPE_PARENT, self::ROLETYPE_STAFF],
                ],
            ],
        ];
    }


    /**
     * Build a hash lookup of (roletype, tab) tuples that we use for the tab's
     * id and display name, for logging purposes
     *
     * @return array
     * @throws \PDOException
     */
    private function buildTabsIndex(): array
    {
        /* Find all the tabs' ids to build a relation; derive that from
         * the array key which its its name in the mega array below
         */
        $statement = $this->getDatabaseInternal()->prepare('SELECT id, roletype, tab, display_as FROM profile_tab');
        $statement->execute();

        $tabs = [];

        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $tabs[$row['roletype']][$row['tab']] = [
                'id' => $row['id'],
                'displayAs' => $row['display_as']
            ];
        }

        return $tabs;
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
