<?php

/**
 * Learning Moments, retain soft deleted data for a certain time
 *
 * Justin Clarke <justin.clarke@schoolbox.education>
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\DataRetention;

use Alaress\Database\Connector\IConnector;
use Alaress\Database\Predicate\Builder;
use Psr\Log\LoggerInterface;
use Schoolbox\Admin\DataRetention\IStrategy;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\IConfigLoader;
use Symfony\Component\Lock\LockInterface;

class MomentsRetention implements IStrategy
{
    private const CHUNK_SIZE = 1000; // how many things to delete at once

    /**
     * @param null|LoggerInterface  $log
     * @param bool                  $perform
     * @param IConnector            $database
     * @param LockInterface         $lock
     * @param IConfigLoader         $config
     * @return void
     */
    public function __construct(private ?LoggerInterface $log, private bool $perform, private IConnector $database, private LockInterface $lock, private IConfigLoader $config)
    {
    }

    /**
     * @inheritDoc
     */
    public function process(): void
    {
        $retentionPeriod = (string)$this->config->get(ConfigMapper::NAME_DATA_RETENTION_LEARNINGMOMENTS); // configured by admin with planned options 1 Day, 30 Days, 365 Days, <null> (null is do NOT hard delete this)
        if ('' === $retentionPeriod) {
            $this->log->notice('No retention period set, skipping');
            return;
        }

        $this->lock->refresh();

        $deleteCount = 0;

        $deleteOlderThan = (\Alaress_Date_Format::newNaturalDate(sprintf("-%s days", $retentionPeriod)))->getDatabase();

        // find out how many moments are soft deleted for the retention period

        $query = "
            SELECT
                count(distinct moment.id) AS moments,
                count(moment_evidence.moment_id) AS evidence,
                count(moment_folder.moment_id) AS folders,
                count(moment_tag.moment_id) AS tags
            FROM moment
             LEFT JOIN moment_evidence ON moment.id = moment_evidence.moment_id
             LEFT JOIN moment_folder ON moment.id = moment_folder.moment_id
             LEFT JOIN moment_tag ON moment.id = moment_tag.moment_id
            WHERE moment.deleted_at < :old
        ";

        $statement = $this->database->prepare($query);
        $statement->execute([
                'old'   => $deleteOlderThan,
            ]);

        $toDelete = $statement->fetch();

        // if no entries exit early
        if (empty($toDelete['moments'])) {
            $this->log->notice('No moments to delete');
            return;
        }

        $this->log->info(sprintf("Found '%s' moments to delete", $toDelete['moments']));
        $this->log->info(sprintf("With '%s' moment_evidence to delete", $toDelete['evidence']));
        $this->log->info(sprintf("With '%s' moment_folders to delete", $toDelete['folders']));
        $this->log->info(sprintf("With '%s' moment_tags to delete", $toDelete['tags']));

        // if perform is true, delete learning moments older than the retention period
        if ($this->perform) {
            $this->log->info('Deleting learning moments older than ' . $deleteOlderThan);
            $query = "
            SELECT
                id
            FROM moment
            WHERE moment.deleted_at < :old
        ";

            $statement = $this->database->prepare($query);
            $statement->execute([
                                    'old'   => $deleteOlderThan,
                                ]);

            // collate ids to delete
            $deleteIds = [];
            while ($row = $statement->fetch()) {
                $deleteIds[] = $row['id'];
            }

            // log how many chunks we will delete
            $chunkCount = ceil(count($deleteIds) / self::CHUNK_SIZE);
            $this->log->notice(sprintf("Deleting '%s' moments broken into '%s' chunks", count($deleteIds), $chunkCount));

            // chunk things into self::CHUNK_SIZE
            foreach (array_chunk($deleteIds, self::CHUNK_SIZE) as $chunkId => $chunkIds) {
                // log the chunk we are working on
                $this->log->notice(sprintf("[%s/%s] Processing chunk", $chunkId + 1, $chunkCount));

                $delPlaceholders = Builder::getArrayPlaceholders($chunkIds, 'moment_id');
                $delParams       = Builder::getArrayParameters($chunkIds, 'moment_id');

                // unlink evidence
                $statementEvidence = $this->database->prepare("DELETE FROM moment_evidence WHERE moment_id IN {$delPlaceholders}");
                $statementEvidence->execute($delParams);

                // unlink folders
                $statementFolder = $this->database->prepare("DELETE FROM moment_folder WHERE moment_id IN {$delPlaceholders}");
                $statementFolder->execute($delParams);

                // unlink tags
                $statementTag = $this->database->prepare("DELETE FROM moment_tag WHERE moment_id IN {$delPlaceholders}");
                $statementTag->execute($delParams);

                // delete the moments
                $statementMoment = $this->database->prepare("DELETE FROM moment WHERE id IN {$delPlaceholders}");
                $statementMoment->execute($delParams);

                $this->log->notice(
                    sprintf(
                        "Deleted '%s' moments, '%s' moment_evidence, '%s' moment_folders and '%s' moment_tags",
                        $statementMoment->rowCount(),
                        $statementEvidence->rowCount(),
                        $statementFolder->rowCount(),
                        $statementTag->rowCount()
                    )
                );

                // if we have more chunks, refresh the lock
                if ($chunkId < $chunkCount - 1) {
                    $this->lock->refresh();
                }
            }
        } else {
            $this->log->info('Would have deleted older than ' . $deleteOlderThan);
        }
    }

    /**
     * @inheritDoc
     */
    public function getRetentionLabel(): string
    {
        return 'Learning Moment Retention';
    }
}
