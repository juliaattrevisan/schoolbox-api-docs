#!/usr/bin/php
<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

use Schoolbox\Application\CliTool;
use Schoolbox\Discussion\Test\DataGenerator;

require_once __DIR__ . '/../../../conf/core/require.php';

/**
 * Generate test data for Comments in News
 */
$app = new class extends CliTool\App
{
    // --help: print help
    private const OPT_LONG_HELP = 'help';


    /** @inheritdoc */
    public function getLongOpts(): array
    {
        return [
            'seed:',
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

Mass-generate test (fake) comments.
This script will soft-delete any news article comments which already exist.

Usage: generateCommentTestData.php [--seed=1234]


HELP;
            exit;
        }

        // "Are you sure?"
        echo "This script will soft-delete any news article comments which exist.\n";
        $answer = '';
        while (!in_array($answer, ['y', 'n'])) {
            $answer = strtolower($this->readLine("Do you still wish to run it? [y/n] "));
        }
        if ($answer !== 'y') {
            exit;
        }

        $dataGenerator = new DataGenerator(
            $this->getLogger(),
            Faker\Factory::create(),
            Schoolbox\UserMapper::getInstance(),
            Schoolbox\Comms\News\Mapper::getInstance(),
            Schoolbox\Discussion\Thread\Mapper::getInstance(),
            Schoolbox\Discussion\Comment\Mapper::getInstance()
        );

        $dataGenerator->generateTestData(
            filter_var($this->getOpt('seed:'), FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE)
        );
    }
};

$options = [CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::DEBUG, CliTool\Kernel::OPTION_ERRORS => E_ALL,];

/** @noinspection PhpUnhandledExceptionInspection */
(new Schoolbox\Application\CliTool\Kernel($app, $options))->runApp();
