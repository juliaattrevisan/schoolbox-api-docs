#!/usr/bin/php
<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

use Schoolbox\Application\CliTool;

require_once __DIR__ . '/../../conf/core/require.php';

/**
 */
$app = new class extends CliTool\App {

    /** @inheritDoc */
    public function runAsRoot(): ?bool
    {
        // Doesn't matter, not writing any files
        return null;
    }

    /** @inheritDoc */
    public function getShortOpts(): array
    {
        return ['f:'];
    }


    /** @inheritDoc */
    public function getLongOpts(): array
    {
        return ['folder:'];
    }

    /** @inheritDoc */
    public function run(): void
    {
        if ($this->getOpt('f:') !== null && $this->getOpt('folder:') !== null) {
            $this->getLogger()->error('Exiting - A folder ID must be supplied. Run again with --folder <id>');
            exit(1);
        }
        $topFolderId = $this->getOpt('f:') ? $this->getOpt('f:') : $this->getOpt('folder:');

        $folderMapper = \Schoolbox\Resource\Folder\Mapper::getInstance();

        $topFolder = $folderMapper->findById($topFolderId);
        $folders   = $folderMapper->findAllChildren($topFolder->getId(), true);
        $folders[] = $topFolder;

        // create backup table
        $createTableQuery = 'CREATE TABLE IF NOT EXISTS `_rubric_indicator_usage_tmp` (
              `rubric_indicator_id` int(11) unsigned NOT NULL,
              `submission_box_id` int(11) unsigned NOT NULL,
              `sequence` int(10) unsigned)';
        $this->getDatabaseInternal()->execute($createTableQuery);

        foreach ($folders as $folder) {
            if (!$folder->isSpecFolder()) {
                $this->getLogger()->warning("Folder {$folder->getId()} is not a spec folder. Skipping.");
                continue;
            }
            $this->getLogger()->info("Checking Folder {$folder->getId()}.");
            $this->turnOffRubricFromAssessments($folder->getId());
            $capabilityIds = $this->findFolderCapabilityIds($folder->getId());
            $indicatorIds  = $this->findFolderIndicatorIds($capabilityIds);
            if (!empty($indicatorIds)) {
                $this->deleteIndicatorDescriptors($indicatorIds);
                $this->deleteIndicators($indicatorIds);
            }
            if (!empty($capabilityIds)) {
                $this->deleteCapabilities($capabilityIds);
            }
        }
    }

    private function turnOffRubricFromAssessments(int $folderId)
    {
        $query     = 'SELECT submission_box.id, use_rubric FROM submission_box
              JOIN assessment ON submission_box.assessment_id = assessment.id
              WHERE category = :folderId AND assessment.deleted_at IS NULL';
        $statement = $this->getDatabaseInternal()->executeQuery(
            $query, ['folderId' => $folderId,]
        );
        while ($row = $statement->fetch()) {
            if ((int)$row['use_rubric'] == 1) {
                $this->getLogger()->warning('Turning off rubric of submission_box ' . $row['id']);
            }
            if ($this->perform()) {
                $query = 'UPDATE submission_box SET use_rubric = 0 WHERE id = ' . $row['id'];
                $this->getDatabaseInternal()->executeQuery($query);
            }

            $indicatorUsageQuery = 'SELECT count(*)
                 FROM rubric_indicator_usage WHERE submission_box_id = ' . $row['id'];
            $indicatorUsageCount = $this->getDatabaseInternal()->executeQuery($indicatorUsageQuery)->fetchColumn();
            if ($indicatorUsageCount < 1) {
                continue;
            }

            $this->getLogger()->warning('Deleting indicator usage of submission_box ' . $row['id']);
            if ($this->perform()) {
                $backupUsageQuery = 'INSERT INTO _rubric_indicator_usage_tmp
                 SELECT rubric_indicator_id, submission_box_id, `sequence`
                 FROM rubric_indicator_usage WHERE submission_box_id = ' . $row['id'];
                $this->getDatabaseInternal()->executeQuery($backupUsageQuery);
                $deleteIndicatorUsageQuery = '
                 DELETE FROM rubric_indicator_usage WHERE submission_box_id = ' . $row['id'];
                $this->getDatabaseInternal()->executeQuery($deleteIndicatorUsageQuery);
            }
        }
    }

    private function findFolderCapabilityIds(int $folderId)
    {
        $query     = 'SELECT id FROM rubric_capability
               WHERE parent_id = :folderId AND parent_type = :parentType AND deleted_at IS NULL';
        $statement = $this->getDatabaseInternal()->executeQuery(
            $query, [
                      'folderId'   => $folderId,
                      'parentType' => 'category',
                  ]
        );
        return $statement->fetchAll(\PDO::FETCH_COLUMN);
    }

    private function findFolderIndicatorIds(array $capabilityIds)
    {
        if (empty($capabilityIds)) {
            return [];
        }
        $query     = 'SELECT id FROM rubric_indicator
                  WHERE rubric_capability_id in (' . implode(',', $capabilityIds) . ') AND deleted_at IS NULL';
        $statement = $this->getDatabaseInternal()->executeQuery($query);
        return $statement->fetchAll(\PDO::FETCH_COLUMN);
    }

    private function deleteIndicatorDescriptors(array $indicatorIds)
    {
        if (empty($indicatorIds)) {
            return [];
        }
        $query       = 'SELECT id FROM rubric_descriptor
                WHERE rubric_indicator_id in (' . implode(',', $indicatorIds) . ') AND deleted_at IS NULL';
        $statement   = $this->getDatabaseInternal()->executeQuery($query);
        $descriptors = $statement->fetchAll(\PDO::FETCH_COLUMN);
        $this->getLogger()->debug('Soft deleting descriptors: ' . implode(',', $descriptors));

        if ($this->perform()) {
            $query = 'UPDATE rubric_descriptor SET deleted_at = now()
                WHERE rubric_indicator_id in (' . implode(',', $indicatorIds) . ') AND deleted_at IS NULL';
            $this->getDatabaseInternal()->executeQuery($query);
        }
    }

    private function deleteIndicators(array $indicatorIds)
    {
        $query      = 'SELECT id FROM rubric_indicator
                WHERE id in (' . implode(',', $indicatorIds) . ') AND deleted_at IS NULL';
        $statement  = $this->getDatabaseInternal()->executeQuery($query);
        $fetchedIds = $statement->fetchAll(\PDO::FETCH_COLUMN);
        $this->getLogger()->debug('Soft deleting indicators: ' . implode(',', $fetchedIds));

        if ($this->perform()) {
            $query = 'UPDATE rubric_indicator SET deleted_at = now()
                WHERE id in (' . implode(',', $indicatorIds) . ') AND deleted_at IS NULL';
            $this->getDatabaseInternal()->executeQuery($query);
        }
    }

    private function deleteCapabilities(array $capabilityIds)
    {
        $query      = 'SELECT id FROM rubric_capability
                WHERE id in (' . implode(',', $capabilityIds) . ') AND deleted_at IS NULL';
        $statement  = $this->getDatabaseInternal()->executeQuery($query);
        $fetchedIds = $statement->fetchAll(\PDO::FETCH_COLUMN);
        $this->getLogger()->debug('Soft deleting capabilities: ' . implode(',', $fetchedIds));

        if ($this->perform()) {
            $query = 'UPDATE rubric_capability SET deleted_at = now()
                WHERE id in (' . implode(',', $capabilityIds) . ') AND deleted_at IS NULL';
            $this->getDatabaseInternal()->executeQuery($query);
        }
    }
};

$options = [CliTool\Kernel::OPTION_ERRORS => E_ALL,];

/** @noinspection PhpUnhandledExceptionInspection */
(new Schoolbox\Application\CliTool\Kernel($app, $options))->runApp();
