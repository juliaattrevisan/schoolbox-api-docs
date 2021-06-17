#!/usr/bin/php
<?php
/**
 * @author      Steve Chan <steve.chan@schoolbox.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        http://www.schoolbox.com.au/
 */
declare(strict_types=1);

use Schoolbox\Application\CliTool;


require_once __DIR__ . '/../../../../conf/core/require.php';

/**
 * Wesley College Melbourne Pastoral Visibility updater
 *
 * Updates pastoral records where access has been incorrectly set. Non-deleted pastoral records
 * set to Teachers/Deny will be upgraded to Teachers/General.
 *
 * To redirect log output to a file(s):
 *     STDOUT is file descriptor 1
 *     STDERR is file descriptor 2; our logger output ends up going here
 *
 *     Example: php example.php 2> log.txt
 */
$app = new class extends CliTool\App {

    /**
     * --help
     *
     * Print help text for those who don't know how to use this tool
     */
    private const OPT_LONG_HELP = 'help';


    /**
     * Role id for the Wisedev role "Teachers"
     */
    private const ROLE_ID = 2;


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
Updates pastoral records where access has been incorrectly set. Non-deleted pastoral_access records
set to Teachers/Deny will be upgraded to Teachers/General. 

Existing pastoral_access records are backed up into _pastoral_access after the first Perform.
Subsequent runs won't reinitialise this table. You'll have to do that manually if you want
to reuse this tool in the future. 

HELP;
            exit;
        }

        $db = $this->getDatabaseInternal();

        if (!$this->perform()) {
            /* Have a look to see how many records would get changed
             */
            $result = $db->executeQuery('
                SELECT pastoral.id, pastoral.heading
                FROM pastoral_access
                INNER JOIN pastoral ON pastoral.id = pastoral_access.pastoral_id
                WHERE pastoral_access.access = 0
                AND pastoral_access.role_id = :roleId
                AND pastoral_access.deleted_at IS NULL
            ', ['roleId' => self::ROLE_ID])->fetchAll();

            foreach ($result as $row) {
                $this->getLogger()->info(sprintf(
                    'Pastoral record "%s" (%s) will be updated.',
                    $row['heading'],
                    $row['id']
                ));
            }

            $this->getLogger()->info(sprintf(
                '%s record(s) found.',
                \count($result)
            ));

            $this->getLogger()->info('Planning finished.');
        } else {
            $this->getLogger()->info('Backing up pastoral_access.');

            /* Create table only if it doesn't already exist
             */
            $db->executeQuery('CREATE TABLE IF NOT EXISTS _pastoral_access LIKE pastoral_access');

            /* Create a backup of pastoral_access into _pastoral_access
             * but only if _pastoral_access is empty
             *
             * https://stackoverflow.com/questions/5307164/execute-insert-if-table-is-empty
             */
            $db->executeQuery('
                INSERT INTO _pastoral_access 
                SELECT * FROM pastoral_access 
                WHERE NOT EXISTS (SELECT * FROM _pastoral_access)
            ');

            /* Update all pastoral_access records which were set to Teachers/Deny
             * Teachers is specifically role id 2 in Wisedev
             */
            $this->getLogger()->info('Updating records...');

            $db->executeQuery("
                UPDATE pastoral_access
                SET access = 1
                WHERE access = 0
                AND role_id = :roleId
                AND deleted_at IS NULL
            ", ['roleId' => self::ROLE_ID]);

            $this->getLogger()->info('Done.');
        }
    }


    /**
     * FIXME Decide whether this script must be run as root
     *  By default, this returns false (see CliTool\App) but if you're using methods that require
     *  access to special folders (such as when you're creating files) you need to run this as root.
     *  Otherwise it'll fail with a generic error that might mislead you
     *
     * @return bool|null
     */
    public function runAsRoot(): ?bool
    {
        return parent::runAsRoot();
    }
};

/* OPTION_ERRORS => E_ALL causes all notices and errors to fatally crash your script
 *
 * You should generally try to fix those, even though in some special cases php will throw notices
 * for its own expected behaviours which will inadvertently cause this to fail
 */
$options = [ CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::DEBUG, CliTool\Kernel::OPTION_ERRORS => E_ALL, ];

/** @noinspection PhpUnhandledExceptionInspection */
(new Schoolbox\Application\CliTool\Kernel($app, $options))->runApp();
