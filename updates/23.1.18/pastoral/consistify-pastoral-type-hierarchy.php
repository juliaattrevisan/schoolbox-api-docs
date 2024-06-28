<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

use Schoolbox\Application\CliKernelAbstract;
use Schoolbox\Application\CliTool;

require_once __DIR__ . '/../../../conf/core/require.php';

/**
 * if staging, dev or trial, clear intellischool secret config.
 */
$app = new class extends CliTool\App {
    const STOPWORDS_TABLE_NAME = 'innodb_ft_stopword';

    public function run(): void
    {
        $dbc = $this->getDatabaseInternal();
        $log = $this->getLogger();
        $quoter = $dbc->getQuoter();

        // Psuedocode
        // ----------
        // Get current pastoral subtype/type pairs
        // Foreach pair,
        //  * find whether any pastoral records exist with the same subtype but different type
        //  * if any found do exist,
        //     * update pastoral record type to match type from pair
    }
};

/** @noinspection PhpUnhandledExceptionInspection */
(new CliTool\Kernel($app, [CliKernelAbstract::OPTION_LEVEL => Monolog\Logger::INFO]))->runApp();
