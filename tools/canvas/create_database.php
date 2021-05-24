#!/usr/bin/php
<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au
 */

declare(strict_types=1);

use Schoolbox\Application\CliTool;
use External\Canvas\Sync\CliDatabaseCreator;

require_once __DIR__ . '/../../conf/core/require.php';

/**
 * Creates a local canvas staging database named canvas_data_[INSTANCE_ID].
 *
 * This can be run as root or non-root
 * This attempts to connect to database host using credentials at /root/.my.cnf.
 * If these cannot be read (either lack perm, or file not exists), this will prompt the user for them.
 * Database host is the same as schoolbox database.
 * Tool grants all perms on this database to the schoolbox user (eg schoolbox or schoolboxdev)
 * If database already exists, this warns user and exits, it does not overwrite it.
 */
$kernel = new CliTool\Kernel(new CliDatabaseCreator(), [CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::INFO, CliTool\Kernel::OPTION_ERRORS => E_ALL]);
$kernel->runApp();
