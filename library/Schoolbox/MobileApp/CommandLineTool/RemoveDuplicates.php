<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\MobileApp\CommandLineTool;

use Schoolbox\Application\CliTool;
use Throwable;

/**
 * Command-line tool for removing duplicate app registrations, in preparation
 * for applying a new primary key on the `mobile_app_registry` table.
 */
class RemoveDuplicates extends CliTool\App
{
    /**
     * @inheritdoc
     */
    public function run(): void
    {
        $db = $this->getDatabaseInternal();
        $db->beginTransaction();

        $deleteDuplicateQuery = $db->prepareQuery("
            DELETE FROM mobile_app_registry
            WHERE user_id = :userId
            AND   SUBSTR(app_token, 1, 255) = :appToken
            AND   app_id = :appId
            ORDER BY touched_at ASC LIMIT :limit
        ");

        $this->getLogger()->notice(
            $this->perform()
                ? 'Running in live mode: changes will be committed'
                : 'Running in dry-run mode: changes will not be committed'
        );
        try {
            $result = $db
                ->executeQuery(
                    "
                    SELECT
                        user_id,
                        SUBSTR(app_token, 1, 255) AS app_token,
                        app_id,
                        COUNT(*) as duplicate_count
                    FROM mobile_app_registry
                    GROUP BY user_id, SUBSTR(app_token, 1, 255), app_id
                    HAVING duplicate_count > 1
                "
                )
                ->fetchAll();
            $this->getLogger()->notice(sprintf('%d duplicated mobile_app_registry record(s) found', count($result)));
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
                $deleteDuplicateQuery->execute(
                    [
                        'userId'   => $row['user_id'],
                        'appId'    => $row['app_id'],
                        'appToken' => $row['app_token'],
                        'limit'    => (int)$row['duplicate_count'] - 1,
                    ]
                );
            }
        } catch (Throwable $e) {
            $this->getLogger()->error(sprintf('Caught error "%s" during deletion: rolling back', $e->getMessage()));
            $db->rollBack();
            exit(1);
        }
        if ($this->perform()) {
            $this->getLogger()->notice('Committing changes');
            $db->commit();
        } else {
            $this->getLogger()->notice('Rolling back changes');
            $db->rollBack();
        }
    }
}
