#!/usr/bin/php
<?php

/**
 * @author    Doug MacKenzie <doug.mackenzie@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

use Schoolbox\Learning\Assessment\Exporter\Exporter;
use Schoolbox\Filter;
use Schoolbox\Learning\Assessment\DataModel as Assessment;
use Schoolbox\Application\CliTool;
use Schoolbox\UserMapper;

require_once __DIR__ . '/../../conf/core/require.php';

/**
 * Export assessment data for Accellerus import
 */
$app = new class extends CliTool\App
{
    // --help: print help
    private const OPT_LONG_HELP = 'help';


    /** @inheritdoc */
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

Exports assessment results into a csv format containing:
(student's external id, folder code, assessment name, assessment item code, result)

Contents relate only to assessments due in the current year.

Usage: csv.php


HELP;
            exit;
        }

        /**
         * @var Filter\Filters\Assessment\IdFinder $idFinder
         * @var Assessment\MapperProxy             $assessmentDataSource
         */
        $idFinder             = $this->getDi()->get('filters.assessmentIdFinder');
        $assessmentDataSource = $this->getDi()->get('assessmentDataSource');

        $exporter = new Exporter(
            $idFinder,
            $assessmentDataSource,
            UserMapper::getInstance()->findByUsername(UserMapper::USERNAME_ALARESS_ADMIN),
        );
        $exporter->generate($this->getOutStream());
    }
};

$kernel = new CliTool\Kernel($app);
$kernel->runApp();
