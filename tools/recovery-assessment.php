#!/usr/bin/php
<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

use Schoolbox\Application\CliTool;

require_once __DIR__ . '/../conf/core/require.php';

/**
 * Tool to recover lost data related to a migration of rubric capability mark types
 * This will be run on staging servers that have a backup restored, and will provide the SQL need to fix the live server
 */
$app = new class extends CliTool\App
{
    /** @inheritDoc */
    public function run(): void
    {
        $db      = $this->getDatabaseInternal();
        $assessmentQuery = $db->prepareQuery('SELECT id, rubric_capability_mark_type_id, rubric_capability_grade_group_id FROM submission_box WHERE use_rubric = 1 AND _rubric_mark_per_capability=1');
        $assessmentQuery->execute();

        while ($row = $assessmentQuery->fetch()) {
            if ($row['rubric_capability_grade_group_id']) {
                printf("UPDATE submission_box SET rubric_capability_mark_type_id = %d, rubric_capability_grade_group_id = %d WHERE id = %d;\n", $row['rubric_capability_mark_type_id'], $row['rubric_capability_grade_group_id'], $row['id']);
            } else {
                printf("UPDATE submission_box SET rubric_capability_mark_type_id = %d WHERE id = %d;\n", $row['rubric_capability_mark_type_id'], $row['id']);
            }
        }
    }
};

$kernel = new CliTool\Kernel($app, [CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::INFO]);
$kernel->runApp();
