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
     * @inheritdoc
     *
     * @return never-return exit `CliKernelAbstract::EXIT_STATUS_APP_ERROR` if
     *                      there's a data issue detected, otherwise exits with
     *                      `CliKernelAbstract::EXIT_STATUS_OK`
     */
    public function run(): void
    {
        $db           = $this->getDatabaseInternal();
        $logger       = $this->getLogger();
        $hasDataIssue = false;

        // Collect expected rows
        $logger->notice('Fetching expected mv_assessment_relation data...');
        $selectExpectedDataQuery = $db->prepareQuery(Reloader::SELECT_ASSESSMENT_RELATION_SQL);
        $selectExpectedDataQuery->execute();
        $expectedData = $selectExpectedDataQuery->fetchRowArray('assessment_id');
        $logger->info(sprintf('Fetched expected data: %d rows', count($expectedData)));

        // Collect actual rows
        $logger->info('Fetching actual mv_assessment_relation data...');
        $selectActualDataQuery = $db->prepareQuery('SELECT * FROM mv_assessment_relation');
        $selectActualDataQuery->execute();
        $actualData = $selectActualDataQuery->fetchRowArray('assessment_id');
        $logger->info(sprintf('Fetched actual data: %d rows', count($actualData)));

        // Report on data expected but not actual
        $expectedRowsNotInActual = array_diff_key($expectedData, $actualData);
        if (count($expectedRowsNotInActual)) {
            $logger->warning(
                sprintf(
                    '%d expected assessments not found in actual: %s',
                    count($expectedRowsNotInActual),
                    implode(',', array_keys($expectedRowsNotInActual)),
                )
            );
            $hasDataIssue = true;
        } else {
            $logger->notice('No expected assessments not found in actual assessments.');
        }

        // Report on actual but unexpected data
        $actualRowsNotInExpected = array_diff_key($actualData, $expectedData);
        if (count($actualRowsNotInExpected)) {
            $logger->warning(
                sprintf(
                    '%d actual assessments not found in expected: %s',
                    count($actualRowsNotInExpected),
                    implode(',', array_keys($actualRowsNotInExpected)),
                )
            );
            $hasDataIssue = true;
        } else {
            $logger->notice('No actual assessments not found in expected assessments.');
        }

        // Report on differences
        $differentRows = [];
        $differ        = new Differ();
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
}
