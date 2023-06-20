<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\Relation\CommandLineTool;

use Alaress\Diff\Differ;
use Schoolbox\Application\CliKernelAbstract;
use Schoolbox\Application\CliTool\App;

/**
 * Tool for reporting on the consistency of the data within the assessment
 * relation materialized view `mv_assessment_relation`.
 *
 * Examines the contents of the materialized view, and compares those contents
 * to the data which populates it.
 */
class Reporter extends App
{
    /**
     * Help options
     */
    private const OPT_SHORT_HELP = 'h';
    private const OPT_LONG_HELP  = 'help';

    /**
     * @inheritdoc
     */
    public function getShortOpts(): array
    {
        return [self::OPT_SHORT_HELP];
    }

    /**
     * @inheritdoc
     */
    public function getLongOpts(): array
    {
        return [self::OPT_LONG_HELP];
    }

    /**
     * @inheritdoc
     *
     * @return never-return exit `CliKernelAbstract::EXIT_STATUS_APP_ERROR` if
     *                      there's a data issue detected, otherwise exits with
     *                      `CliKernelAbstract::EXIT_STATUS_OK`
     */
    public function run(): void
    {
        // Help output
        if ($this->getOpt(self::OPT_SHORT_HELP) || $this->getOpt(self::OPT_LONG_HELP)) {
            $this->outputHelp();
            exit(CliKernelAbstract::EXIT_STATUS_OK);
        }

        $db           = $this->getDatabaseInternal();
        $logger       = $this->getLogger();
        $hasDataIssue = false;

        // Collect expected rows
        $logger->notice('Fetching expected mv_assessment_relation data...');
        $selectExpectedDataQuery = $db->prepareQuery(
            'SET STATEMENT max_statement_time=0 FOR '
            . Reloader::SELECT_ASSESSMENT_RELATION_SQL,
        );
        $selectExpectedDataQuery->execute();
        $expectedData = $selectExpectedDataQuery->fetchAll();
        $logger->info(sprintf('Fetched expected data: %d rows', count($expectedData)));

        // Collect actual rows
        $logger->info('Fetching actual mv_assessment_relation data...');
        $selectActualDataQuery = $db->prepareQuery('SELECT * FROM mv_assessment_relation');
        $selectActualDataQuery->execute();
        $actualData = $selectActualDataQuery->fetchAll();
        $logger->info(sprintf('Fetched actual data: %d rows', count($actualData)));

        // Report on duplicates (both expected and actual)
        $expectedDuplicates = array_filter(
            array_count_values(array_column($expectedData, 'assessment_id')),
            fn ($v) => $v > 1,
        );
        if (count($expectedDuplicates)) {
            $logger->warning(
                sprintf(
                    '%d expected rows contain duplicates: %s',
                    count($expectedDuplicates),
                    implode(',', array_keys($expectedDuplicates)),
                )
            );
        } else {
            $logger->notice('No expected rows contain duplicates.');
        }

        $actualDuplicates = array_filter(
            array_count_values(array_column($actualData, 'assessment_id')),
            fn ($v) => $v > 1,
        );
        if (count($actualDuplicates)) {
            $logger->warning(
                sprintf(
                    '%d actual rows contain duplicates: %s',
                    count($actualDuplicates),
                    implode(',', array_keys($actualDuplicates)),
                )
            );
        } else {
            $logger->notice('No actual rows contain duplicates.');
        }

        // Report on data expected but not actual
        $expectedRowsNotInActual = array_udiff(
            $expectedData,
            $actualData,
            fn ($a, $b) => $a === $b ? 0 : 1,
        );
        if (count($expectedRowsNotInActual)) {
            $logger->warning(
                sprintf(
                    '%d expected rows not found in actual for assessments: %s',
                    count($expectedRowsNotInActual),
                    implode(
                        ',',
                        array_unique(array_column($expectedRowsNotInActual, 'assessment_id')),
                    ),
                )
            );
            $hasDataIssue = true;
        } else {
            $logger->notice('No expected rows not found in actual assessments.');
        }

        // Report on actual but unexpected data
        $actualRowsNotInExpected = array_udiff(
            $actualData,
            $expectedData,
            fn ($a, $b) => $a === $b ? 0 : 1,
        );
        if (count($actualRowsNotInExpected)) {
            $logger->warning(
                sprintf(
                    '%d actual assessments not found in expected for assessments: %s',
                    count($actualRowsNotInExpected),
                    implode(
                        ',',
                        array_unique(array_column($actualRowsNotInExpected, 'assessment_id')),
                    ),
                )
            );
            $hasDataIssue = true;
        } else {
            $logger->notice('No actual assessments not found in expected assessments.');
        }

        // Report on differences
        $differentRows = [];
        $differ        = new Differ('expected', 'actual');
        foreach ($actualData as $assessmentId => $actualAssessmentData) {
            $expectedAssessmentData = $expectedData[$assessmentId] ?? null;
            if (!isset($expectedAssessmentData)) {
                continue;
            }
            if ($expectedAssessmentData !== $actualAssessmentData) {
                $differentRows[] = $assessmentId;
                $logger->debug(
                    sprintf(
                        "Assessment %d does not match:\nexpected: %s\nactual: %s\ndiff: %s",
                        $assessmentId,
                        json_encode($expectedAssessmentData),
                        json_encode($actualAssessmentData),
                        json_encode($differ->diff($expectedAssessmentData, $actualAssessmentData))
                    )
                );
            }
        }
        if (count($differentRows)) {
            $logger->warning(
                sprintf(
                    '%d assessments different in actual and expected: %s',
                    count($differentRows),
                    implode(',', $differentRows),
                )
            );
            $hasDataIssue = true;
        } else {
            $logger->notice('No assessments different in actual and expected.');
        }

        // Raise error code if there was any data difference
        exit($hasDataIssue ? CliKernelAbstract::EXIT_STATUS_APP_ERROR : CliKernelAbstract::EXIT_STATUS_OK);
    }

    /**
     * Output help text
     *
     * @return void
     */
    private function outputHelp(): void
    {
        $file   = $this->getToolFilename();
        $green  = self::COLOUR_GREEN;
        $white  = self::COLOUR_WHITE;
        $yellow = self::COLOUR_YELLOW;
        echo <<<HELP

{$white}NAME:
    Assessment Relations Reporter

SYNOPSIS:
    $green{$file} {$yellow}[OPTION]...$white

DESCRIPTION
    This tool reports on assessment relations, as stored in the
    `mv_assessment_relation` table.

    If the number of expected and actual assessment relations do not match,
    there is likely to be a data issue: this tool will report on the differences
    between the expected and actual data.

OPTIONS
    $yellow-h, --help$white
        Output this help text and exit.

    $yellow-v, -vv, -vvv$white
        Control verbosity.

        If run with -vvv, this will report on the exact differences between the
        expected and actual data.


HELP;
    }
}
