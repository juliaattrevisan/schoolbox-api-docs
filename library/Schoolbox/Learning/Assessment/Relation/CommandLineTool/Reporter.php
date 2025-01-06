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

        $db     = $this->getDatabaseInternal();
        $logger = $this->getLogger();

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

        // Sort consistently
        usort($expectedData, [$this, 'sortCallback']);
        usort($actualData, [$this, 'sortCallback']);

        $keyedExpectedData = $this->groupDataByKey($expectedData);
        $keyedActualData   = $this->groupDataByKey($actualData);

        // Report on duplicates (both expected and actual)
        $this->reportOnDuplicates($keyedExpectedData, 'expected');
        $this->reportOnDuplicates($keyedActualData, 'actual');

        // Report on data expected but not actual, and vice versa
        $expectedRowsNotInActual = $this->reportOnMissingData(
            $keyedExpectedData,
            'expected',
            $keyedActualData,
            'actual',
        );
        $actualRowsNotInExpected = $this->reportOnMissingData(
            $keyedActualData,
            'actual',
            $keyedExpectedData,
            'expected',
        );

        // Report on differences between rows
        $differentRows = $this->reportOnDifferences($keyedActualData, $keyedExpectedData);

        // Raise error code if there was any data difference
        $hasDataIssue =
            count($expectedRowsNotInActual) > 0
            || count($actualRowsNotInExpected) > 0
            || count($differentRows) > 0
        ;

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

    /**
     * Report on duplicates in data.
     *
     * @param array  $keyedData
     * @param string $type
     * @return void Duplicates in data aren't yet considered a problem
     */
    private function reportOnDuplicates(array $keyedData, string $type): void
    {
        $logger     = $this->getLogger();
        $duplicates = $this->findDuplicateAssessments($keyedData);
        if (count($duplicates)) {
            $logger->warning(
                sprintf(
                    '%d %s rows contain duplicates: %s',
                    count($duplicates),
                    $type,
                    implode(',', array_keys($duplicates)),
                )
            );
        } else {
            $logger->notice(sprintf('No %s rows contain duplicates.', $type));
        }
    }

    /**
     * Finds duplicate assessment IDs in data.
     *
     * @param array $keyedData
     * @return array
     */
    private function findDuplicateAssessments(array $keyedData): array
    {
        return array_filter($keyedData, fn($v) => count($v) > 1);
    }

    /**
     * Report on whatever data might be missing.
     *
     * @param array  $keyedBaseData
     * @param string $baseDataType
     * @param array  $keyedComparisonData
     * @param string $comparisonDataType
     * @return array list of rows missing from `$baseData` in `$comparisonData`,
     *               if any.
     */
    private function reportOnMissingData(
        array $keyedBaseData,
        string $baseDataType,
        array $keyedComparisonData,
        string $comparisonDataType,
    ): array {
        $logger                      = $this->getLogger();
        $baseDataNotInComparisonData = array_keys(
            array_diff_key(
                $keyedBaseData,
                $keyedComparisonData,
            )
        );
        if (count($baseDataNotInComparisonData)) {
            $logger->warning(
                sprintf(
                    '%d %s rows not found in %s for assessments: %s',
                    count($baseDataNotInComparisonData),
                    $baseDataType,
                    $comparisonDataType,
                    implode(',', $baseDataNotInComparisonData),
                )
            );
        } else {
            $logger->notice(
                sprintf('No %s rows not found in %s assessments.', $baseDataType, $comparisonDataType)
            );
        }
        return $baseDataNotInComparisonData;
    }

    /**
     * Group data by its assessment ID.
     *
     * @param array $data
     * @return array
     */
    private function groupDataByKey(array $data): array
    {
        $returnData = [];
        foreach ($data as $row) {
            $assessmentId = $row['assessment_id'];
            $returnData[$assessmentId] ??= [];
            $returnData[$assessmentId][] = $row;
        }
        return $returnData;
    }

    /**
     * Report on data for assessments existing in both `$keyedActualData` and
     * `$keyedExpectedData`, but different.
     *
     * @param array $keyedActualData
     * @param array $keyedExpectedData
     * @return array list of assessment IDs in both `$keyedActualData` and
     *               `$keyedExpectedData`, but with different values.
     */
    private function reportOnDifferences(array $keyedActualData, array $keyedExpectedData): array
    {
        $logger        = $this->getLogger();
        $differentRows = [];
        $differ        = new Differ('expected', 'actual');
        $assessmentIds = array_unique(
            array_merge(
                array_keys($keyedActualData),
                array_keys($keyedExpectedData),
            )
        );
        foreach ($assessmentIds as $assessmentId) {
            $actualAssessmentData   = $keyedActualData[$assessmentId] ?? null;
            $expectedAssessmentData = $keyedExpectedData[$assessmentId] ?? null;
            if ((int)$expectedAssessmentData !== (int)$actualAssessmentData) {
                $differentRows[] = $assessmentId;
                $logger->debug(
                    sprintf(
                        "Assessment %d does not match:\nexpected: %s\nactual: %s\ndiff: %s",
                        $assessmentId,
                        json_encode($expectedAssessmentData),
                        json_encode($actualAssessmentData),
                        json_encode($differ->diff($expectedAssessmentData ?? [], $actualAssessmentData ?? [])),
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
        } else {
            $logger->notice('No assessments different in actual and expected.');
        }
        return $differentRows;
    }

    /**
     * Sort callback for results from DB.
     *
     * A little easier (and possibly quicker, believe it or not due to temporary
     * table creation) than sorting in the DB.
     *
     * @param array $a
     * @param array $b
     * @return int
     */
    private function sortCallback(array $a, array $b): int
    {
        $columns = [
            'assessment_id',
            'folder_id',
            'project_assessment_id',
            'course_implementation_id',
            'course_unit_implementation_id',
            'course_specification_id',
            'course_unit_specification_id',
        ];

        // Return the first of these which isn't equal
        foreach ($columns as $column) {
            $aValue = $a[$column];
            $bValue = $b[$column];
            if ($aValue !== $bValue) {
                return $a <=> $b;
            }
        }
        return 0;
    }
}
