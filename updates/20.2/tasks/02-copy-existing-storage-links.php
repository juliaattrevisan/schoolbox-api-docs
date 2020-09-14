#!/usr/bin/php
<?php
/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

use Schoolbox\Application\CliTool;

require_once __DIR__ . '/../../conf/core/require.php';

$app = new class extends CliTool\App {

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
         */
        $query = "
            SELECT
                id,
                link
            FROM `task`
            WHERE link LIKE '/storage/fetch%'
        ";

        /* Extract the hash
         */

        /* Find the realname of this hash
         */

        /* Save the hash and the realname into the new task_file relational table
         * This is the last known name of the file and applies to existing storage links
         */

        $this->getDatabaseInternal()->executeQuery($query);

//            $this->getLogger()->notice();
    }
};

$options = [CliTool\Kernel::OPTION_ERRORS => E_ALL,];

/** @noinspection PhpUnhandledExceptionInspection */
(new Schoolbox\Application\CliTool\Kernel($app, $options))->runApp();
