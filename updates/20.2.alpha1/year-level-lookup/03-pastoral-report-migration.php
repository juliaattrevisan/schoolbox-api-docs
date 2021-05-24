<?php

/**
 * @author      Steve Chan <steve.chan@schoolbox.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        http://www.schoolbox.com.au/
 */

declare(strict_types=1);

use Schoolbox\Application\CliTool;

require_once __DIR__ . '/../../../conf/core/require.php';

$app = new class extends CliTool\App {

    /**
     * Migrate pastoral_report.years values into lu_year_level.id equivalent csv strings
     */
    public function run(): void
    {
        $db = $this->getDatabaseInternal();

        /* Find a list of all the (lu_year_level.name, lu_year_level.id) tuples,
         * indexed by lu_year_level.name; we're gonna use that as a lookup
         */
        $yearLevelsStatement = $db->prepareQuery('
            SELECT id, name
            FROM lu_year_level
            ORDER BY name
        ');

        $yearLevelsStatement->execute();

        /* Set up lookup
         */
        $yearLevels = $yearLevelsStatement->fetchRowArray('name');

        /* Select all the pastoral report rows where pastoral_report.years is not '' and not null
         * These are the reports that need their year levels updated
         */
        $pastoralReportStatement = $db->prepareQuery("
            SELECT id, years
            FROM pastoral_report
            WHERE years IS NOT NULL
            AND years != ''
        ");

        $pastoralReportStatement->execute();

        /* Set up update statement
         */
        $updateStatement = $db->prepareQuery('
            UPDATE pastoral_report
            SET years = :years
            WHERE id = :id
        ');

        /* For every row, explode pastoral_report.years by comma, into its separate pieces
         */
        $reports = $pastoralReportStatement->fetchAll(PDO::FETCH_ASSOC);

        foreach ($reports as $report) {
            $yearNames = explode(',', $report['years']);

            /* Use the (name => id) tuples to find out what year_id these values were
             * then concat them back into a csv string
             */
            $newYearNames = [];

            foreach ($yearNames as $yearName) {
                $newYearNames[] = $yearLevels[$yearName]['id'] ?? null;
            }

            /* Filter out any non-matching data
             * Implode back into csv
             */
            $newYearIds = implode(',', array_values(array_filter($newYearNames)));

            /* Ram it back into the pastoral_report row and save it
             */
            if ('' !== $newYearIds) {
                $updateStatement->execute([
                    'years' => $newYearIds,
                    'id'    => $report['id']
                ]);
            }
        }
    }
};

$options = [CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::DEBUG, CliTool\Kernel::OPTION_ERRORS => E_ALL,];

/** @noinspection PhpUnhandledExceptionInspection */
(new Schoolbox\Application\CliTool\Kernel($app, $options))->runApp();
