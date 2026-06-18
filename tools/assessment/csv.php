#!/usr/bin/env php
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
$app = new class extends CliTool\BaseApp
{
    protected function getName(): string
    {
        return 'csv';
    }

    protected function getDescription(): string
    {
        return <<<DESC
            Exports assessment results into a csv format containing:
            (student's external id, folder code, assessment name, assessment item code, result)

            Contents relate only to assessments due in the current year.
            DESC;
    }

    protected function setOptions(): self
    {
        return $this;
    }

    protected function hasPerformMode(): bool
    {
        return false;
    }

    /**
     * Run the cli tool
     */
    public function run(): void
    {
        /** @var Filter\Filters\Assessment\IdFinder $idFinder */
        $idFinder = $this->getDi()->get('filters.assessmentIdFinder');
        /** @var Assessment\MapperProxy $assessmentDataSource */
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
