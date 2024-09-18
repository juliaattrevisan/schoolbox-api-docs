#!/usr/bin/php
<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

use Schoolbox\Application\CliKernelAbstract;
use Schoolbox\Application\CliTool;

require_once __DIR__ . '/../../conf/core/require.php';

$app = new class extends CliTool\App {
    public function run(): void
    {
        $dbc = $this->getDatabaseInternal();
        $log = $this->getLogger();

        // Iterate over the user database and find all users with the password 'donotchange', then change it to NULL
        $rows = $dbc->executeQuery('SELECT `id`, `password` FROM user WHERE `password` IS NOT NULL AND `password` != ""')->fetchAll();
        $rowCount = count($rows);
        $log->info(sprintf('Processing %d users', $rowCount));
        foreach ($rows as $i => $row) {
            if (($i + 1) % 50 === 0) {
                $log->info(sprintf('Processing user %d/%d', $i + 1, $rowCount));
            }
            $userId = $row['id'];

            if (password_verify('donotchange', $row['password'])) {
                $dbc->prepareQuery('UPDATE `user` SET `password` = NULL WHERE id = ?', [$userId])->execute();
                $log->info("Changed password for user $userId");
            }
        }
        $log->info('All users processed.');
    }
};

/** @noinspection PhpUnhandledExceptionInspection */
(new CliTool\Kernel($app, [CliKernelAbstract::OPTION_LEVEL => Monolog\Logger::INFO], true))->runApp();
