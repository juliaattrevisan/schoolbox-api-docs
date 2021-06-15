#!/usr/bin/php
<?php

declare(strict_types=1);

use Schoolbox\Application\CliTool;

require_once __DIR__ . '/../../../conf/core/require.php';

$app = new class extends CliTool\App {

    public function run(): void
    {
        // Find all duplicate registrations: remove them
        $db = $this->getDatabaseInternal();
        $db->beginTransaction();

        $deleteDuplicateQuery = $db->prepareQuery("
            DELETE FROM mobile_app_registry
            WHERE user_id = :userId
            AND   SUBSTR(app_token, 1, 255) = :appToken
            AND   app_id = :appId
            ORDER BY touched_at ASC LIMIT :limit
        ");

        $result = $db
            ->executeQuery("
                SELECT
                    user_id,
                    SUBSTR(app_token, 1, 255) AS app_token,
                    app_id,
                    COUNT(*) as duplicate_count
                FROM mobile_app_registry
                GROUP BY user_id, SUBSTR(app_token, 1, 255), app_id
                HAVING duplicate_count > 1
            ")
            ->fetchAll();
        $this->getLogger()->info(sprintf('%d duplicated mobile_app_registry record(s) found', count($result)));
        foreach ($result as $row) {
            $this->getLogger()->info(
                sprintf(
                    '%d duplicates for user_id: %d, app_id: %d, app_token: "%s" found: deleting all but one',
                    $row['user_id'],
                    $row['app_id'],
                    $row['app_token'],
                    $row['duplicate_count'],
                )
            );
            $deleteDuplicateQuery->execute([
                'userId'   => $row['user_id'],
                'appId'    => $row['app_id'],
                'appToken' => $row['app_token'],
                'limit'    => (int)$row['duplicate_count'] - 1,
            ]);
        }

        $db->commit();
    }
};

/** @noinspection PhpUnhandledExceptionInspection */
(new Schoolbox\Application\CliTool\Kernel($app))->runApp();
