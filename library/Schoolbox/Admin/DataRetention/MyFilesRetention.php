<?php

/**
 * My Files, retain soft deleted data for a certain time
 *
 * Justin Clarke <justin.clarke@schoolbox.education>
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\DataRetention;

use Schoolbox\Config\ConfigMapper;

class MyFilesRetention extends StrategyBase
{
    public string $retentionConfigKey = ConfigMapper::NAME_DATA_RETENTION_MYFILES;

    public function getRetentionLabel(): string
    {
        return 'My Files Retention';
    }

    protected function hasDataToDelete(): bool
    {
        // find out how many MyFiles are soft deleted for the retention period

        $query = "
            SELECT
                count(*) AS files
            FROM myFile
            WHERE deleted_at < :old
        ";

        $statement = $this->database->prepare($query);
        $statement->execute([
                                'old'   => $this->getRetentionPeriod(),
                            ]);

        $filesToDelete = $statement->fetch();

        $query = "
            SELECT
                count(*) AS folders
            FROM myFolder
            WHERE deleted_at < :old
        ";

        $statement = $this->database->prepare($query);
        $statement->execute([
                                'old'   => $this->getRetentionPeriod(),
                            ]);

        $folderToDelete = $statement->fetch();

        // if no entries exit early
        if (empty($folderToDelete['folders']) && empty($filesToDelete['files'])) {
            return false;
        }

        $this->log->info(sprintf("Found '%s' MyFiles to delete", $filesToDelete['files']));
        $this->log->info(sprintf("Found '%s' MyFolders to delete", $folderToDelete['folders']));
        return true;
    }
}
