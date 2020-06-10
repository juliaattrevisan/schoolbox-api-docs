#!/usr/bin/php
<?php
/**
 * Pastoral report's severity_id used to be order of severity.
 * This script is to replace it with actual severity id to support reordering.
 *
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

use Schoolbox\Application\CliTool;

require_once __DIR__ . '/../../conf/core/require.php';

$app = new class extends CliTool\App {
    public function run(): void
    {
        $db = $this->getDatabaseInternal();

        $severityListQuery   = $db->prepareQuery('SELECT id, sequence FROM lu_pastoral_severity WHERE deleted_at IS NULL');
        $reportListQuery     = $db->prepareQuery('SELECT id, severity_id FROM pastoral_report WHERE severity_id IS NOT NULL');
        $updateSeverityQuery = $db->prepareQuery('UPDATE pastoral_report SET severity_id = :severityId WHERE id = :reportId');

        $severityListQuery->execute();
        $sequenceToId = array_column($severityListQuery->fetchAll(), 'id', 'sequence');

        $reportListQuery->execute();
        $reports = $reportListQuery->fetchAll();
        foreach ($reports as $report) {
            $updateSeverityQuery->execute([
                'severityId' => $sequenceToId[$report['severity_id']] ?? null,
                'reportId'   => $report['id'],
            ]);
        }
    }
};

$options = [CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::DEBUG, CliTool\Kernel::OPTION_ERRORS => E_ALL,];

/** @noinspection PhpUnhandledExceptionInspection */
(new Schoolbox\Application\CliTool\Kernel($app, $options))->runApp();
