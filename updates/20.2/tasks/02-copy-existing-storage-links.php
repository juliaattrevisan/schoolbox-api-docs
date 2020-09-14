#!/usr/bin/php
<?php
/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

use Schoolbox\Application\CliTool;

require_once __DIR__ . '/../../../conf/core/require.php';

/**
 * Copy hash strings from task.link into task_files, going into storage table
 * to get the hash's original name and bringing that in as well
 *
 * This only applies to task.link where it matches the /storage/fetch.php prefix and
 * only connects with hashes that exist in the storage table, via the inner join
 */
$app = new class extends CliTool\App {

    /* Links to stuff in our brownhole is determined by this url prefix
     */
    private const STORAGE_LINK_PREFIX = '/storage/fetch.php?hash=';

    /** @inheritDoc */
    public function runAsRoot(): ?bool
    {
        // Doesn't matter, not writing any files
        return null;
    }

    /** @inheritDoc */
    public function run(): void
    {
        /* Find all the task ids which had a storage link in it
         * Extract the hash
         * Find the realname of this hash
         */
        $query = "
            SELECT
                id,
                link,
                REPLACE(link, '" . self::STORAGE_LINK_PREFIX . "', '') AS hash,
                `storage`.filename
            FROM task
            INNER JOIN `storage` ON `storage`.`hash` = REPLACE(link, '". self::STORAGE_LINK_PREFIX . "', '')
            WHERE link LIKE '". self::STORAGE_LINK_PREFIX . "%'
        ";

        $statement = $this->getDatabaseInternal()->executeQuery($query);

        /* Save the hash and the realname into the new task_file relational table
         * This is the last known name of the file and applies to existing storage links
         */
        $query = '
            INSERT INTO task_file (storage_id, task_id, realname)
            VALUES (:storageId, :taskId, :realname)
        ';

        $insertStatement = $this->getDatabaseInternal()->prepareQuery($query);

        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
            $insertStatement->execute([
                'storageId' => $row['hash'],
                'taskId' => $row['id'],
                'realname' => $row['filename']
            ]);

            $this->getLogger()->notice(sprintf(
                'Assigning (%s, %s, %s) to task id %s',
                $row['hash'],
                $row['id'],
                $row['filename'],
                $row['id']
            ));
        }
    }
};

$options = [CliTool\Kernel::OPTION_ERRORS => E_ALL,];

/** @noinspection PhpUnhandledExceptionInspection */
(new Schoolbox\Application\CliTool\Kernel($app, $options))->runApp();
