#!/usr/bin/env php
<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

use Alaress\Database\Connector\IConnector;
use Alaress\Redis;
use Schoolbox\Admin\Usage\Regenerate;
use Schoolbox\Application\CliTool;

require_once __DIR__ . '/../../conf/core/require.php';

/**
 * Re generates usage cache for /adminv2/usage
 */
$app = new class extends CliTool\App {
// --help: print help
    private const OPT_LONG_HELP = 'help';


    /** @inheritdoc */
    public function getLongOpts(): array
    {
        return [
            self::OPT_LONG_HELP,
        ];
    }

    /** @inheritdoc */
    public function runAsRoot(): ?bool
    {
        return false;
    }


    /**
     * Run the cli tool
     */
    public function run(): void
    {
        if ($this->getOpt(self::OPT_LONG_HELP)) {
            echo <<<HELP
Re Calculates the storage usage and stores the calculations in redis

Usage: tools/usageCache.php [-v] [-p]
        -v Verbose mode (may be provided up to 3 times for increasing verbosity)

HELP;
            exit;
        }


        /** @var IConnector $database */
        $database = $this->getDi()->get('databaseInternalRead');

        $regenerate = new Regenerate(Redis::getCacheInstance(), $database);
        $regenerate->regenerateUsage($this->getLogger());
    }
};

$options = [CliTool\Kernel::OPTION_ERRORS => E_ALL,];

/** @noinspection PhpUnhandledExceptionInspection */
(new Schoolbox\Application\CliTool\Kernel($app, $options))->runApp();
