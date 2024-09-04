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
 * This tool runs regularly on the demo instance. It's currently scheduled to run every night to refresh its data,
 * ensuring that dates maintain relevancy around the current datetime, preventing data stagnating and/or
 * consequently disappearing from view.
 *
 * (It's not listed in the repo cron nor is it particularly obvious where this occurs, but this tool wasn't really intended
 * for regular human interactivity)
 */
$app = new class extends CliTool\App {
    /* This data should only have their Year date components updated,
     * leaving month and day unchanged. Typically represents a "rollover"
     * style situation where you want to keep the same dates and months,
     * only changing the year to something relevant.
     * Some columns aren't specifically date-related, such as term titles, but
     * in our demo data such columns -contain- a year which needs updating,
     * even if it's only for nonfunctional purposes in the ui e.g. "Semester 1, 2015"
     */
    private const WHITELISTED_COLUMNS_YEAR = [
        'sis_timetable_cycle'      => ['finish', 'start', 'title'],
        'sis_user_profile_student' => ['date_of_birth'],
        'sportsfixtures_season'    => ['start', 'end', 'name'],
        'term'                     => ['start', 'actual_finish', 'display_finish', 'title', 'code'],
    ];

    /* These columns can have any of their date components adjusted
     * by incrementation - days, months, years...can all change.
     *
     * Note: 'deleted_at' columns should not be added to the list below.
     * There are so many of these, and the benefit of incrementing them is so small,
     * that incrementing them is not worth the effort.
     */
    private const WHITELISTED_COLUMNS = [
        'assess'                 => ['finish', 'start'],
        'assessment'             => ['close_date', 'due_date', 'open_date', 'publish_date'],
        'assessment_participant' => ['due_date', 'open_date'],
        'assessment_view'        => ['last_visit'],
        'assess_attempt'         => ['finish', 'reviewed_at,', 'start'],
        'assess_response'        => ['updated_at'],
        'badge_user'             => ['awarded_at', 'expires_at', 'viewed_on', 'reasoned_at'],
        'blog'                   => ['created_at', 'updated_at'],
        'blog_comment'           => ['created_at', 'updated_at'],
        'chat'                   => ['time'],
        'chat_poll'              => ['time'],
        'cmsLog'                 => ['time'],
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
        'eotc_event'                               => ['end_datetime', 'start_datetime'],
        'eportfolio_component_blog'                => ['created_at', 'updated_at'],
        'eportfolio_component_blog_comment'        => ['created_at', 'updated_at'],
        'eportfolio_log'                           => ['datetime'],
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
        'evidence'                           => ['created_at', 'updated_at'],
        'file'                               => ['modified', 'submitted'],
        'forumPost'                          => ['date', 'modified'],
        'forumThread'                        => ['date', 'last_post_date'],
        'folder'                             => ['created_at', 'updated_at'],
        'homepage_log'                       => ['datetime'],
        'homepage_visits'                    => ['date'],
        'link'                               => ['modified', 'submitted'],
        'login_log'                          => ['date'],
        'mobile_app_registry'                => ['created_at', 'touched_at'],
        'myFile'                             => ['published'],
        'participant'                        => ['created_at', 'updated_at', 'excluded_at'],
        'participant_plan'                   => ['close_date', 'due_date', 'open_date', 'publish_date'],
        'pastoral'                           => ['created_at', 'updated_at', 'occurred_on'],
        'pastoral_audit'                     => ['created_at'],
        'pastoral_comment'                   => ['created_at', 'updated_at'],
        'pastoral_revision'                  => ['created_at', 'updated_at', 'occurred_on'],
        'plagscan_document'                  => ['privatised_at', 'created_at', 'updated_at'],
        'poll'                               => ['close', 'created'],
        'pollLog'                            => ['created'],
        'reportcard_comment'                 => ['created', 'modified'],
        'sis_event'                          => ['finish_at', 'start_at'],
        'sis_user_profile_student'           => ['enrolment_date', 'leaving_date'],
        'socialstream_post'                  => ['created_at', 'updated_at'],
        'sportsfixtures_match'               => ['finish_at', 'start_at'],
        'sportsfixtures_match_itineraryitem' => ['start_at'],
        'sportsfixtures_season'              => ['archived_at', 'created_at', 'updated_at'],
        'storage'                            => ['expired_at', 'last_accessed', 'uploaded_on'],
        'student_submission'                 => ['created_at', 'submitted_at', 'updated_at'],
        'student_submission_file'            => ['created_at', 'updated_at'],
        'student_submission_file_kami'       => ['kami_created_at', 'created_at', 'updated_at'],
        'submission_log'                     => ['datetime'],
        'submission_return'                  => ['created_at', 'published_at', 'updated_at'],
        'submission_return_file'             => ['created_at', 'updated_at'],
        'task_completion'                    => ['completion_date'],
        'task_file'                          => ['created_at', 'updated_at'],
        'textBox'                            => ['created_at', 'updated_at'],
        'user'                               => ['created_at', 'updated_at'],
        'user_activity_log'                  => ['date'],
        'user_link'                          => ['created_on'],
        'user_professionaldevelopment'       => ['accredited_at', 'created_at', 'updated_at'],
        'video_queue'                        => ['createdOn', 'modifiedOn'],
        'wiki'                               => ['created'],
        'wikiRevision'                       => ['created'],
    ];

    /**
     * These are the names of columns whose date or datetime values should always be in the past.
     */
    private const HISTORIC_COLUMN_NAMES_AS_KEYS = [
        // Column names in this list can optionally be prefixed with the table name.
        // The tableName.columnName format should be used where the column name might refer to future dates in other tables.
        'accepted_at'             => null,
        'accredited_at'           => null,
        'archived_at'             => null,
        'awarded_at'              => null,
        'cmsLog.time'             => null,
        'completion_date'         => null,
        'created'                 => null,
        'createdOn'               => null,
        'created_at'              => null,
        'created_on'              => null,
        'date_of_birth'           => null,
        'deleted'                 => null,
        'deleted_at'              => null,
        'eportfolio_log.datetime' => null,
        'excluded_at'             => null,
        'expired_at'              => null,
        'forumPost.date'          => null,
        'forumThread.date'        => null,
        'homepage_log.datetime'   => null,
        'kami_created_at'         => null,
        'last_accessed'           => null,
        'last_post_date'          => null,
        'last_visit'              => null,
        'login_log.date'          => null,
        'modified'                => null,
        'modifiedOn'              => null,
        'modified_at'             => null,
        'occurred_on'             => null,
        'privatised_at'           => null,
        'published'               => null,
        'published_at'            => null,
        'reasoned_at'             => null,
        'rejected_at'             => null,
        'reviewed_at'             => null,
        'submission_log.datetime' => null,
        'submitted'               => null,
        'submitted_at'            => null,
        'touched_at'              => null,
        'updated_at'              => null,
        'uploaded_on'             => null,
        'user_activity_log.date'  => null,
        'viewed_on'               => null,
    ];

    private \Alaress\Database\Connector\IConnector $database;

    public function runAsRoot(): ?bool
    {
        return null;
    }

    /** @inheritDoc */
    public function run(): void
    {
        // explicitly use teh write db. we aren't reading just writing...
        $this->database = $this->getDi()->get('databaseFactory')->getInternalWriteDatabase();
        // Get epoch date increment config date_increment_epoch
        $query = $this->database->prepareQuery("SELECT value FROM config WHERE name = :configName");

        $query->execute(['configName' => 'date_increment_epoch']);
        $epoch_config = $query->fetchAll(PDO::FETCH_COLUMN)[0];

        if (!$epoch_config) {
            $this->getLogger()->notice("'date_increment_epoch' not set. Exiting.");
            exit;
        }
        try {
            $epochDate = new Alaress_Date_Format($epoch_config, Alaress_Date_Format::DATABASE_DATE_FORMAT);
        } catch (InvalidArgumentException $e) {
            $this->getLogger()->notice("The 'date_increment_epoch' config needs to be in the format " . Alaress_Date_Format::DATABASE_DATE_FORMAT);
            exit;
        }

        $now = Alaress_Date_Format::now();

        // If date_increment_epoch is more than 7 days ago, increment dates by week(s)
        // I don't think we ever want to set a date in the future to this config?
        $diffDays = (int)floor(($epochDate->diffInDays($now, false) / 7)) * 7;
        if ($diffDays <= 0) {
            $this->getLogger()->notice("Day diff of today and date_increment_epoch is $diffDays. It needs to be more than 7 to the increment to happen.");
            exit;
        }
        $diffYears = $epochDate->diffInYears($now);

        $this->getLogger()->notice("Incrementing dates by $diffDays days.");
        $this->database->beginTransaction();
        try {
            $this->incrementDates((int)$diffDays, $diffYears);
        } catch (\Throwable $e) {
            $this->getLogger()->error("Exception caught. Rolling back transaction.");
            $this->database->rollBack();
            throw $e;
        }
        $this->database->commit();
    }


    /**
     * Increments all dates in database by specified number
     */
    private function incrementDates(int $diffDays, int $diffYears): void
    {
        // Fetch all tables
        $query        = $this->database->executeQuery(
            "
                SELECT c.TABLE_NAME, c.COLUMN_NAME
                FROM INFORMATION_SCHEMA.TABLES t
                JOIN INFORMATION_SCHEMA.COLUMNS c ON c.TABLE_NAME = t.TABLE_NAME
                WHERE t.TABLE_SCHEMA = :schema
                AND TABLE_TYPE = 'BASE TABLE'
                AND c.DATA_TYPE IN ('date','datetime', 'timestamp')
                ORDER BY c.TABLE_NAME, c.COLUMN_NAME
            ",
            ['schema' => $this->database->getDatabase()],
        );
        $tableColumns = [];
        foreach ($query->fetchAll(PDO::FETCH_NUM) as [$table, $column]) {
            $tableColumns[$table]   ??= [];
            $tableColumns[$table][] = $column;
        }

        foreach ($tableColumns as $tableName => $columns) {
            if (isset(self::WHITELISTED_COLUMNS[$tableName])) {
                $this->incrementColumn($tableName, $columns, $diffDays);
            }
            if ($diffYears > 0 && isset(self::WHITELISTED_COLUMNS_YEAR[$tableName])) {
                $this->incrementYear($tableName, $columns, $diffYears);
            }
        }

        $this->database->executeQuery(
            "
                UPDATE config
                SET value = DATE_FORMAT(DATE_ADD(value, INTERVAL :diff DAY), '%Y-%m-%d')
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

            $dateAddSql = "DATE_ADD(`$column`, INTERVAL :diff DAY)";
            $logMessageSuffix = '';

            if (
                array_key_exists($column, self::HISTORIC_COLUMN_NAMES_AS_KEYS) ||
                array_key_exists("$tableName.$column", self::HISTORIC_COLUMN_NAMES_AS_KEYS)
            ) {
                // Dates/times in historic columns should never be incremented past the present date/time.
                $dateAddSql = "LEAST(NOW(), DATE_ADD(`$column`, INTERVAL :diff DAY))";
                $logMessageSuffix = ' (not beyond the current date)';
            }

            $this->getLogger()->info("Incrementing dates for: $tableName.$column$logMessageSuffix");
            $updateQuery = $this->database->prepareQuery(
                "UPDATE `$tableName`
                SET `$column` = $dateAddSql
                $whereNotDeleted
                ORDER BY `$column` $order"
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

        // update name (e.g. Semester 1, 2021 -> Semester 1, 2022) first
        if ($diffYears > 0 && count($nameColumns)) {
            foreach ($nameColumns as $column) {
                $rows = $this->database->executeQuery(
                    "SELECT
                       REPLACE(`$column`, YEAR(`$dateColumns[0]`), YEAR(`$dateColumns[0]`) + $diffYears) AS new_name,
                       `$column` AS old_name
                     FROM `$tableName`
                     WHERE `$dateColumns[0]` IS NOT NULL
                     ORDER BY `$dateColumns[0]` DESC"
                )->fetchAll();
                foreach ($rows as $row) {
                    $this->database->executeQuery(
                        "UPDATE `$tableName` SET `$column` = :newName WHERE `$column` = :oldName",
                        [
                            'newName' => $row['new_name'],
                            'oldName' => $row['old_name'],
                        ]
                    );
                }
            }
        }

        foreach ($dateColumns as $column) {
            $dateAddSql = "DATE_ADD(`$column`, INTERVAL :diff YEAR)";
            $logMessageSuffix = '';

            if (
                array_key_exists($column, self::HISTORIC_COLUMN_NAMES_AS_KEYS) ||
                array_key_exists("$tableName.$column", self::HISTORIC_COLUMN_NAMES_AS_KEYS)
            ) {
                // Dates/times in historic columns should never be incremented past the present date/time.
                $dateAddSql = "LEAST(NOW(), DATE_ADD(`$column`, INTERVAL :diff YEAR))";
                $logMessageSuffix = ' (not beyond the current date)';
            }

            $updateQuery = $this->database->prepareQuery(
                "UPDATE `$tableName`
                SET `$column` = $dateAddSql
                $whereNotDeleted
                ORDER BY `$column` DESC"
            );

            $this->getLogger()->info("Incrementing year for: $tableName.$column$logMessageSuffix");
            $updateQuery->execute([':diff' => $diffYears]);
        }
    }
};

$kernel = new CliTool\Kernel($app, [CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::INFO]);
$kernel->runApp();
