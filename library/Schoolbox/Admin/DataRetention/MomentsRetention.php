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
use Schoolbox\Mapper\Exception;
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
        $retentionPeriod = (string)$this->config->get(ConfigMapper::NAME_DATA_RETENTION_LEARNINGMOMENTS); // configured by admin with planned options 1;1 Day:30;30 Days:365;365 Days and <null> (null is do NOT hard delete this)
        if ('' === $retentionPeriod) {
            $this->log->notice('No retention period set, skipping');
            return;
        }

        $this->lock->refresh();

        $deleteOlderThan = (\Alaress_Date_Format::newNaturalDate(sprintf("-%s days", $retentionPeriod)))->getDatabase();

        // find out how many moments are soft deleted for the retention period

        $query = "
            SELECT
                count(distinct moment.id) AS moments,
                count(moment_evidence.moment_id) AS evidence,
                count(moment_folder.moment_id) AS folders,
                count(moment_tag.moment_id) AS tags,
                count(discussion_thread.id) AS threads,
                count(discussion_thread_comment.discussion_thread_id) AS comments
            FROM moment
             LEFT JOIN moment_evidence ON moment.id = moment_evidence.moment_id
             LEFT JOIN moment_folder ON moment.id = moment_folder.moment_id
             LEFT JOIN moment_tag ON moment.id = moment_tag.moment_id
             LEFT JOIN discussion_thread ON moment.discussion_thread_id = discussion_thread.id
             LEFT JOIN discussion_thread_comment ON moment.discussion_thread_id = discussion_thread_comment.discussion_thread_id
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
        $this->log->info(sprintf("With '%s' discussion_threads to delete", $toDelete['threads']));
        $this->log->info(sprintf("With '%s' discussion_thread_comments to delete", $toDelete['comments']));

        $this->log->info('Deleting learning moments older than ' . $deleteOlderThan);
        $query = "
        SELECT
            id,
            discussion_thread_id
        FROM moment
        WHERE moment.deleted_at < :old
    ";

        $statement = $this->database->prepare($query);
        $statement->execute([
                                'old'   => $deleteOlderThan,
                            ]);

        // collate ids to delete
        $deleteIds = [];
        $discussionThreadIds = [];
        while ($row = $statement->fetch()) {
            $deleteIds[] = [
                'id' => $row['id'],
                'discussion_thread_id' => $row['discussion_thread_id'] // may exist, may be null, we need this to delete the discussion thread and comments if it exists
            ];
        }

        // log how many chunks we will delete
        $chunkCount = ceil(count($deleteIds) / self::CHUNK_SIZE);
        $this->log->notice(sprintf("Deleting '%s' moments broken into '%s' chunks", count($deleteIds), $chunkCount));

        // chunk things into self::CHUNK_SIZE
        foreach (array_chunk($deleteIds, self::CHUNK_SIZE) as $chunkId => $chunk) {
            // begin transaction for the chunk
            $this->database->beginTransaction();

            try {
                // log the chunk we are working on
                $this->log->notice(sprintf("[%s/%s] Processing chunk", $chunkId + 1, $chunkCount));

                // extract the ids and the discussion threads from the chunk
                $chunkIds            = array_column($chunk, 'id');
                $discussionThreadIds = array_column($chunk, 'discussion_thread_id');

                // debug log the ids we are deleting in this chunk
                $this->log->debug(sprintf("Deleting learning moment ids: %s", implode(', ', $chunkIds)));
                // clean up the display of the discussion thread ids, as there may be nulls in there, we only want to log the ones that exist
                $discussionThreadIds = array_filter($discussionThreadIds);
                if (!empty($discussionThreadIds)) {
                    $this->log->debug(sprintf("Deleting discussion_thread ids: %s", implode(', ', $discussionThreadIds)));
                }

                $delPlaceholders = Builder::getArrayPlaceholders($chunkIds, 'moment_id');
                $delParams       = Builder::getArrayParameters($chunkIds, 'moment_id');

                $discussionPlaceholders = Builder::getArrayPlaceholders($discussionThreadIds, 'moment_id');
                $discussionParams       = Builder::getArrayParameters($discussionThreadIds, 'moment_id');

                // unlink evidence // the Garbage Collector will take care of the actual files, we just need to remove the database records
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

                if (!empty($discussionThreadIds)) {
                    // delete the discussion thread comments // there may be a weird d-sync here... I have found in demo moments that were deleted, but threads comments that were not... but they do match to the moment...
                    $statementComment = $this->database->prepare("DELETE FROM discussion_thread_comment WHERE discussion_thread_id IN {$discussionPlaceholders}");
                    $statementComment->execute($discussionParams);

                    // delete the discussion threads // there may be a weird d-sync here... I have found in demo moments that were deleted, but threads that were not... but they do match to the moment...
                    $statementThread = $this->database->prepare("DELETE FROM discussion_thread WHERE id IN {$discussionPlaceholders}");
                    $statementThread->execute($discussionParams);
                } {
                    $statementComment = null;
                    $statementThread  = null;
                }

                // if perform is true, delete learning moments older than the retention period
                if ($this->perform) {
                    // perform the transaction for the chunk
                    $this->database->commit();

                    $this->log->notice(
                        sprintf(
                            "Deleted '%s' moments, '%s' moment_evidence, '%s' moment_folders, '%s' moment_tags '%s' discussion_threads, '%s' discussion_thread_comments",
                            $statementMoment->rowCount(),
                            $statementEvidence->rowCount(),
                            $statementFolder->rowCount(),
                            $statementTag->rowCount(),
                            $statementThread?->rowCount() ?? 0,
                            $statementComment?->rowCount() ?? 0
                        )
                    );
                } else {
                    // if not performing, roll back the transaction to avoid making changes
                    $this->database->rollBack();

                    $this->log->notice(
                        sprintf(
                            "Would have deleted '%s' moments, '%s' moment_evidence, '%s' moment_folders, '%s' moment_tags '%s' discussion_threads, '%s' discussion_thread_comments",
                            $statementMoment->rowCount(),
                            $statementEvidence->rowCount(),
                            $statementFolder->rowCount(),
                            $statementTag->rowCount(),
                            $statementThread?->rowCount() ?? 0,
                            $statementComment?->rowCount() ?? 0
                        )
                    );
                }
            } catch (\PDOException | Exception $e) {
                $this->database->rollBack();
                $this->log->error(sprintf("Error processing chunk: %s", $e->getMessage()));
            }

            // if we have more chunks, refresh the lock
            if ($chunkId < $chunkCount - 1) {
                $this->lock->refresh();
            }
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
