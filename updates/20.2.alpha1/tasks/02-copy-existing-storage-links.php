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

    /* Name of the temporal table */
    private const TEMP_TABLE = '_task_hash_tmp';

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
         */
        $query = "CREATE TABLE " . self::TEMP_TABLE . " (
                        task_id int(11),
                        hash varchar(100),
                        PRIMARY KEY task_hash_tmp_pkey(task_id),
                        INDEX task_hash_tmp_index (hash))
                      SELECT id AS task_id, REPLACE(link, '" . self::STORAGE_LINK_PREFIX . "', '') AS hash
                      FROM task
                      WHERE link LIKE '" . self::STORAGE_LINK_PREFIX . "%'";

        $this->getDatabaseInternal()->executeQuery($query);

        /* Save the hash and the realname into the new task_file relational table
         * This is the last known name of the file and applies to existing storage links
         */
        $insertQuery = 'INSERT INTO task_file (storage_id, task_id, realname, created_at, updated_at)
                        SELECT ' . self::TEMP_TABLE . '.hash, ' . self::TEMP_TABLE . '.task_id, storage.filename, now(), now()
                        FROM ' . self::TEMP_TABLE . ' JOIN storage ON ' . self::TEMP_TABLE . '.hash=storage.hash';

        $this->getDatabaseInternal()->executeQuery($insertQuery);

        /* Clear task.link of any contents that contained STORAGE_LINK_PREFIX
         * since those were supposed to have migrated to task_link
         */
        $updateQuery = "
            UPDATE task
            SET link = ''
            WHERE link LIKE '" . self::STORAGE_LINK_PREFIX . "%'
        ";

        $this->getDatabaseInternal()->executeQuery($updateQuery);

        /* Finally drop the temporal table
        */
        $this->getDatabaseInternal()->executeQuery("DROP TABLE " . self::TEMP_TABLE);
    }
};

$options = [CliTool\Kernel::OPTION_ERRORS => E_ALL,];

/** @noinspection PhpUnhandledExceptionInspection */
(new Schoolbox\Application\CliTool\Kernel($app, $options))->runApp();
