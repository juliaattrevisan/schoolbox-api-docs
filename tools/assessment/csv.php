#!/usr/bin/php
<?php
/**
 * @author    Doug MacKenzie <doug.mackenzie@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

use Schoolbox\Learning\Assessment\Exporter\Exporter;
use Schoolbox\MapperFactory;
use Schoolbox\Filter;
use Schoolbox\Learning\Assessment\DataModel as Assessment;
use Schoolbox\Application\CliTool;

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
         * @var Assessment\MapperProxy $assessmentDataSource
         * @var MapperFactory          $mapperFactory
         * @var Filter\Mapper          $filterMapper
         */
        $mapperFactory        = $this->getDi()->get('mapperFactory');
        $filterMapper         = $mapperFactory->get(Filter\Mapper::class);
        $assessmentMapper     = $mapperFactory->get(Assessment\Mapper::class);
        $idFinder             = Filter\Filters\Assessment\IdFinder::getInstance($filterMapper, $assessmentMapper);
        $assessmentDataSource = $this->getDi()->get('assessmentDataSource');

        $exporter = new Exporter($idFinder, $assessmentDataSource);
        $exporter->generate($this->getOutStream());
    }
};

$kernel = new CliTool\Kernel($app);
$kernel->runApp();
