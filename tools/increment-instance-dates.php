#!/usr/bin/php
<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

use Schoolbox\Application\CliTool;

require_once __DIR__ . '/../conf/core/require.php';

/**
 * Tool to increment data dates for sandbox instances
 */
$app = new class extends CliTool\App {
    // Columns to increment by year
    private const WHITELISTED_COLUMNS_YEAR = [
        'sis_timetable_cycle'      => ['finish', 'start', 'title'],
        'sis_user_profile_student' => ['date_of_birth'],
        'sportsfixtures_season'    => ['start', 'end', 'name'],
        'term'                     => ['start', 'actual_finish', 'display_finish', 'title', 'code'],
    ];

    // Columns to increment
    private const WHITELISTED_COLUMNS = [
        'assess'                 => ['finish', 'start'],
        'assessment'             => ['close_date', 'due_date', 'open_date', 'publish_date'],
        'assessment_participant' => ['due_date', 'open_date'],
        'assessment_view'        => ['last_visit'],
        'assess_attempt'         => ['finish', 'reviewed_at,', 'start'],
        'assess_response'        => ['updated_at'],
        'badge_user'             => ['awarded_at', 'expires_at', 'viewed_on'],
        'blog'                   => ['created_at', 'updated_at'],
        'blog_comment'           => ['created_at', 'updated_at'],
        'chat'                   => ['time'],
        'chat_poll'              => ['time'],
        'comms_news_article'     => [
            'accepted_at',
            'created_at',
            'expires_at',
            'publish_at',
            'rejected_at',
            'submitted_at',
            'updated_at',
            'occurs_at',
        ],

        'comms_news_article_statistics_cumulative' => ['date'],
        'course_unit_implementation'               => ['due_date', 'open_date'],
        'discussion_thread'                        => ['created_at', 'updated_at'],
        'discussion_thread_comment'                => ['created_at', 'updated_at'],
        'eportfolio_component_blog'                => ['created_at', 'updated_at'],
        'eportfolio_component_blog_comment'        => ['created_at', 'updated_at'],
        'event'                                    => [
            'created_at',
            'ends',
            'end_datetime',
            'execute_at',
            'last_executed',
            'publish_date',
            'starts',
            'start_datetime',
            'updated_at',
        ],
        'evidence'                                 => ['created_at', 'updated_at'],
        'file'                                     => ['modified', 'submitted'],
        'forumPost'                                => ['date', 'modified'],
        'forumThread'                              => ['date', 'last_post_date'],
        'homepage_log'                             => ['datetime'],
        'homepage_visits'                          => ['date'],
        'link'                                     => ['modified', 'submitted'],
        'participant_plan'                         => ['close_date', 'due_date', 'open_date', 'publish_date'],
        'pastoral'                                 => ['created_at', 'updated_at'],
        'pastoral_audit'                           => ['created_at'],
        'pastoral_comment'                         => ['created_at', 'updated_at'],
        'pastoral_revision'                        => ['created_at', 'updated_at'],
        'poll'                                     => ['close'],
        'sis_event'                                => ['finish_at', 'start_at'],
        'sis_user_profile_student'                 => ['enrolment_date', 'leaving_date'],
        'socialstream_post'                        => ['created_at', 'updated_at'],
        'sportsfixtures_match'                     => ['finish_at', 'start_at'],
        'sportsfixtures_match_itineraryitem'       => ['start_at'],
        'student_submission'                       => ['created_at', 'submitted_at', 'updated_at'],
        'student_submission_file'                  => ['created_at', 'updated_at'],
        'submission_box'                           => ['date', 'late_date', 'open_date', 'publish_date'],
        'submission_return'                        => ['created_at', 'published_at', 'updated_at'],
        'submission_return_file'                   => ['created_at', 'updated_at'],
        'task_completion'                          => ['completion_date'],
        'task_file'                                => ['created_at', 'updated_at'],
    ];

    public function runAsRoot(): ?bool
    {
        return null;
    }

    /** @inheritDoc */
    public function run(): void
    {
        // Get epoch date increment config date_increment_epoch
        $query = $this->getDatabaseInternal()->prepareQuery("SELECT value FROM config WHERE name = :configName");
        $query->execute(['configName' => 'date_increment_epoch']);
        $epoch_config = $query->fetchAll(PDO::FETCH_COLUMN)[0];

        if (!$epoch_config) {
            $this->getLogger()->notice("'date_increment_epoch' not set. Exiting.");
            exit;
        }
        $epochDate = new Alaress_Date_Format($epoch_config, Alaress_Date_Format::DATABASE_DATE_FORMAT);

        // If date_increment_epoch is more than 7 days ago, increment dates by week(s)
        // I don't think we ever want to set a date in the future to this config?
        $diffDays = floor(($epochDate->diffInDays(Alaress_Date_Format::now(), false) / 7)) * 7;
        if ($diffDays <= 0) {
            $this->getLogger()->notice("Day diff: $diffDays. There's nothing to do here just yet. Exiting.");
            exit;
        }
        $this->getLogger()->notice("Incrementing dates by $diffDays days.");
        $this->getDatabaseInternal()->beginTransaction();
        try {
            $this->incrementDates((int)$diffDays);
        } catch (\Throwable $e) {
            $this->getLogger()->error("Exception caught. Rolling back transaction.");
            $this->getDatabaseInternal()->rollBack();
            throw $e;
        }
        $this->getDatabaseInternal()->commit();
    }


    /**
     * Increments all dates in database by specified number
     */
    private function incrementDates(int $diffDays): void
    {
        // Fetch all tables
        $query        = $this->getDatabaseInternal()->executeQuery(
            "
                SELECT c.TABLE_NAME, c.COLUMN_NAME
                FROM INFORMATION_SCHEMA.TABLES t
                JOIN INFORMATION_SCHEMA.COLUMNS c ON c.TABLE_NAME = t.TABLE_NAME
                WHERE t.TABLE_SCHEMA = :schema
                AND TABLE_TYPE = 'BASE TABLE'
                AND c.DATA_TYPE IN ('date','datetime')
                ORDER BY c.TABLE_NAME, c.COLUMN_NAME
            ",
            ['schema' => $this->getDatabaseInternal()->getDatabase()],
        );
        $tableColumns = [];
        foreach ($query->fetchAll(PDO::FETCH_NUM) as [$table, $column]) {
            $tableColumns[$table]   ??= [];
            $tableColumns[$table][] = $column;
        }

        $diffYears = (int)floor($diffDays / 365);

        foreach ($tableColumns as $tableName => $columns) {
            $this->incrementColumn($tableName, $columns, $diffDays);
            if ($diffYears > 0 && isset(self::WHITELISTED_COLUMNS_YEAR[$tableName])) {
                $this->incrementYear($tableName, $columns, $diffYears);
            }
        }

        $this->getDatabaseInternal()->executeQuery(
            "
                UPDATE config
                SET value = DATE_ADD(value, INTERVAL :diff DAY)
                WHERE name = :configName
            ",
            [
                'diff'       => $diffDays,
                'configName' => 'date_increment_epoch',
            ],
        );
    }


    /**
     * Increments column by specified number of days
     *
     * @param string $tableName
     * @param array  $dbColumns
     * @param int    $diffDays
     */
    private function incrementColumn(string $tableName, array $dbColumns, int $diffDays): void
    {
        if (!isset(self::WHITELISTED_COLUMNS[$tableName])) {
            return;
        }

        $columns = array_intersect(self::WHITELISTED_COLUMNS[$tableName], $dbColumns);
        if (empty($columns)) {
            return;
        }

        $whereNotDeleted = '';
        if (in_array('deleted_at', $dbColumns)) {
            $whereNotDeleted = ' WHERE deleted_at IS NULL ';
        }

        foreach ($columns as $column) {
            // If column is part of index we need to avoid creating a temporary collision.
            // If increasing the value, need to update the largest first.
            // Else decreasing the value, need to update the smallest first.
            $order = $diffDays > 0
                ? 'DESC'
                : 'ASC';

            $this->getLogger()->info("Incrementing dates for: $tableName.$column");
            $updateQuery = $this->getDatabaseInternal()->prepareQuery(
                "
            UPDATE $tableName
            SET $column = DATE_ADD($column, INTERVAL :diff DAY)
            $whereNotDeleted
            ORDER BY $column $order
        "
            );
            $updateQuery->execute([':diff' => $diffDays]);
        }
    }

    /**
     * Increments column by specified number of days
     *
     * @param string $tableName
     * @param array  $dbColumns
     * @param int    $diffYears
     */
    private function incrementYear(string $tableName, array $dbColumns, int $diffYears): void
    {
        $dateColumns = array_intersect(self::WHITELISTED_COLUMNS_YEAR[$tableName], $dbColumns);
        $nameColumns = array_diff(self::WHITELISTED_COLUMNS_YEAR[$tableName], $dbColumns);
        if (empty($dateColumns)) {
            return;
        }

        $whereNotDeleted = '';
        if (in_array('deleted_at', $dbColumns)) {
            $whereNotDeleted = ' WHERE deleted_at IS NULL ';
        }

        // update name first
        if ($diffYears > 0 && count($nameColumns)) {
            foreach ($nameColumns as $column) {
                $rows = $this->getDatabaseInternal()->executeQuery(
                    "SELECT
                       REPLACE(`$column`, YEAR(`$dateColumns[0]`), YEAR(`$dateColumns[0]`) + $diffYears) AS new_name,
                       `$column` AS old_name
                     FROM $tableName
                     WHERE `$dateColumns[0]` IS NOT NULL
                     ORDER BY `$dateColumns[0]` DESC"
                )->fetchAll();
                foreach ($rows as $row) {
                    $this->getDatabaseInternal()->executeQuery(
                        "UPDATE $tableName SET `$column` = :newName WHERE `$column` = :oldName",
                        [
                            'newName' => $row['new_name'],
                            'oldName' => $row['old_name'],
                        ]
                    );
                }
            }
        }

        foreach ($dateColumns as $column) {
            $this->getLogger()->info("Incrementing year for: $tableName.$column");
            // name column

            $updateQuery = $this->getDatabaseInternal()->prepareQuery(
                "
            UPDATE $tableName
            SET $column = DATE_ADD($column, INTERVAL :diff DAY)
            $whereNotDeleted
            ORDER BY $column DESC
        "
            );
            $updateQuery->execute([':diff' => $diffYears * 365]);
        }
    }
};

$kernel = new CliTool\Kernel($app, [CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::INFO]);
$kernel->runApp();
