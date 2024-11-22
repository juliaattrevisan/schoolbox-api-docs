<?php

/**
 * @copyright   Copyright 2024, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\Profile\Editor\Cli;

use Schoolbox\Admin\Profile\Editor\Cli\Strategy\CustomButtons;
use Schoolbox\Admin\Profile\Editor\Cli\Strategy\SystemButtons;
use Schoolbox\Admin\Profile\Editor\Payload;
use Schoolbox\Application\CliTool;

class MigrateButtonsTool extends CliTool\App
{
    /**
     * Make sure to add your locking script to the Lock Inspector tool
     * const LOCK_NAMES in Inspector.php
     */
    public const LOCK_NAME = 'profileEditorMigrateButtons';


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
     * --system
     *
     * Migrate our previously hardcoded system buttons into their corresponding roletype(s)
     */
    private const OPT_LONG_TYPE_SYSTEM = 'system';


    /**
     * --custom
     *
     * Migrate custom buttons into their corresponding roletype(s)
     */
    private const OPT_LONG_TYPE_CUSTOM = 'custom';


    /**
     * Used to resequence System Buttons before Custom Buttons
     *
     * @var \PDOStatement
     */
    private \PDOStatement $resequenceStatement;

    /**
     * @inheritdoc
     */
    public function getLongOpts(): array
    {
        return [
            self::OPT_LONG_HELP,
            self::OPT_LONG_TYPE_SYSTEM,
            self::OPT_LONG_TYPE_CUSTOM,
        ];
    }


    /**
     * Run the cli tool
     */
    public function run(): void
    {
        if ($this->getOpt(self::OPT_LONG_HELP)) {
            echo <<<HELP

Profile Editor System/Custom Button Migration

This creates Profile Button equivalents for our previously-hardcoded system buttons.
It also migrates System and Custom buttons into their corresponding roletypes based
on whatever role(s) were enabled for them.

Custom buttons are always processed first.

Usage: php migrate-buttons.php -vvv --custom --perform
Usage: php migrate-buttons.php -vvv --system --perform
Usage: php migrate-buttons.php -vvv --system --custom --perform


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
            $this->getLogger()->info('Profile Editor Button Migration.');
            $this->getLogger()->info('');

            /* Figure out which roletype corresponds to each role
             */
            $roleTypeLookup = $this->generateRoleTypeLookup();

            /* Get a list of buttons to inspect - users can specify --system,
             * --custom, or both
             */
            $strats = [];

            if ($this->getOpt(self::OPT_LONG_TYPE_CUSTOM)) {
                $strats[] = new CustomButtons(
                    $this->perform(),
                    $this->getDatabaseInternal(),
                    $this->getLogger(),
                    $roleTypeLookup
                );
            }

            if ($this->getOpt(self::OPT_LONG_TYPE_SYSTEM)) {
                $strats[] = new SystemButtons(
                    $this->perform(),
                    $this->getDatabaseInternal(),
                    $this->getLogger(),
                    $this->getDi()->get('l10n'),
                    $this->fetchCampusIds(),
                    trim((string)$this->getConfig()->get('external_staff_portal')),
                    trim((string)$this->getConfig()->get('external_document_button_enable')),
                    trim((string)$this->getConfig()->get('external_type')),
                );
            }

            /* Slam them in if we chose some
             * Otherwise tell the user
             */
            if (!count($strats)) {
                $this->getLogger()->info('Please select --system and/or --custom.');
                return;
            }

            foreach ($strats as $strat) {
                $strat->migrate();
            }

            /* If you imported *both* System and Custom Buttons,
             * force Custom Buttons to come after System Buttons.
             */
            if ($this->getOpt(self::OPT_LONG_TYPE_CUSTOM) && $this->getOpt(self::OPT_LONG_TYPE_SYSTEM)) {
                $this->resequenceAll();
            }
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
     * @return array
     */
    public function generateRoleTypeLookup(): array
    {
        $db = $this->getDatabaseInternal();

        $query = "
            SELECT id, name, group_type
            FROM `groups`
        ";

        $statement = $db->prepare($query);
        $statement->execute();

        $lookup = [];

        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $lookup[$row['id']] = $row['group_type'];
        }

        return $lookup;
    }

    /**
     * Resequence buttons so that System Buttons always come first.
     * Custom Buttons always comes after System Buttons.
     *
     * Executes for all role types.
     *
     * @return void
     */
    private function resequenceAll(): void
    {
        $this->getLogger()->info('Preparing all buttons for resequencing...');

        if ($this->perform()) {
            $this->resequenceStatement = $this->getDatabaseInternal()->prepare("
                UPDATE profile_button
                SET sort_order = sort_order +
                    (SELECT COUNT(*)
                    FROM profile_button
                    WHERE roletype = :roleType
                    AND type != :custom)
                WHERE roleType = :roleType
                AND type = :custom
            ");
        }

        foreach (array_keys(Payload::CONFIG_ROLETYPE) as $roleType) {
            $this->resequenceOne($roleType);
        }
    }


    /**
     * Resequence buttons so that System Buttons always come first.
     * Custom Buttons always comes after System Buttons.
     *
     * Executes for the specified role type.
     *
     * @param string $roleType
     * @return void
     */
    private function resequenceOne(string $roleType): void
    {
        $this->getLogger()->info(sprintf(
            'Resequencing %s system and custom buttons.',
            $roleType
        ));

        if ($this->perform()) {
            $this->resequenceStatement->execute([
                'roleType' => $roleType,
                'custom' => \Schoolbox\Profile\Button\Model::BUTTON_CUSTOM
            ]);
        }
    }


    /**
     * Fetch primary ids for campuses, returning them in a stack
     *
     * @return array
     */
    private function fetchCampusIds(): array
    {
        $ids = [];

        $statement = $this->getDatabaseInternal()->prepare('SELECT id FROM luCampus');
        $statement->execute();

        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $ids[] = $row['id'];
        }

        return $ids;
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
