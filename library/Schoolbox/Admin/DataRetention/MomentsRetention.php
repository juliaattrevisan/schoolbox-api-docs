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

class MomentsRetention extends StrategyBase
{
    public string $retentionConfigKey = ConfigMapper::NAME_DATA_RETENTION_LEARNINGMOMENTS;
    /**
     * @inheritDoc
     */
    public function process(): void
    {
        $deleteOlderThan = $this->getRetentionPeriod();
        if ($deleteOlderThan === null) {
            return;
        }

        $this->lock->refresh();
        // init the chunk loop
        $chunkLoop = 1; // first loop


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

                if (!empty($discussionThreadIds)) { // if there are discussion threads to delete, delete the threads and comments
                    // delete the discussion thread comments // there may be a weird d-sync here... I have found in demo moments that were deleted, but threads comments that were not... but they do match to the moment...
                    $statementComment = $this->database->prepare("DELETE FROM discussion_thread_comment WHERE discussion_thread_id IN {$discussionPlaceholders}");
                    $statementComment->execute($discussionParams);

                    // delete the discussion threads // there may be a weird d-sync here... I have found in demo moments that were deleted, but threads that were not... but they do match to the moment...
                    $statementThread = $this->database->prepare("DELETE FROM discussion_thread WHERE id IN {$discussionPlaceholders}");
                    $statementThread->execute($discussionParams);
                } else { // if there are no discussion threads to delete, set the statement variables to null so we can log them as 0
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

            /** if we have more chunks
             * check if we have looped through more than the count limit
             * check if have passed the time limit we can continue tomorrow
             * refresh the lock
             */
            //
            if ($chunkId < $chunkCount - 1) {
                // if we have looped through more than the count limit, stop for now and continue tomorrow
                if ($this->chunkLoops > 0 && $chunkLoop >= $this->chunkLoops) {
                    $this->log->notice(sprintf("Chunk loop limit of '%s' reached, stopping for now", $this->chunkLoops));
                    break;
                }
                $chunkLoop++;
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

    /**
     * Find out if we have any data to delete, if not, we can exit early and save resources
     * @return bool
     */
    protected function hasDataToDelete(): bool
    {
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
            'old'   => $this->getRetentionPeriod(),
        ]);

        $toDelete = $statement->fetch();

        // if no entries exit early
        if (empty($toDelete['moments'])) {
            return false;
        }

        $this->log->info(sprintf("Found '%s' moments to delete", $toDelete['moments']));
        $this->log->info(sprintf("With '%s' moment_evidence to delete", $toDelete['evidence']));
        $this->log->info(sprintf("With '%s' moment_folders to delete", $toDelete['folders']));
        $this->log->info(sprintf("With '%s' moment_tags to delete", $toDelete['tags']));
        $this->log->info(sprintf("With '%s' discussion_threads to delete", $toDelete['threads']));
        $this->log->info(sprintf("With '%s' discussion_thread_comments to delete", $toDelete['comments']));

        return true;
    }

    /**
     * Get the ids of the moments to delete, and their related discussion thread ids if they exist, so we can delete them in chunks
     * @return array
     */
    protected function getIdsToDelete(): array
    {
        $this->log->info('Deleting learning moments older than ' . $this->getRetentionPeriod());
        $query = "
        SELECT
            id,
            discussion_thread_id
        FROM moment
        WHERE moment.deleted_at < :old
    ";

        $statement = $this->database->prepare($query);
        $statement->execute([
                                'old'   => $this->getRetentionPeriod(),
                            ]);

        // collate ids to delete
        $deleteIds = [];
        while ($row = $statement->fetch()) {
            $deleteIds[] = [
                'id' => $row['id'],
                'discussion_thread_id' => $row['discussion_thread_id'] // may exist, may be null, we need this to delete the discussion thread and comments if it exists
            ];
        }
        return $deleteIds;
    }

    /**
     * Delete a chunk of moments and their related data,
     * this is transacted, and logged to assist with errors and debugging
     * @param array $chunk
     * @return void
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\Exception
     */
    protected function deleteChunk(array $chunk): void
    {
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

        // if chunk is full, we can use the pre-prepared statements to save some time preparing statements
        if (count($chunkIds) === self::CHUNK_SIZE) {
            [
               'evidence' => $prepareEvidence,
                'folder' => $prepareFolder,
                'tag' => $prepareTag,
                'moment' => $prepareMoment
            ] = $this->prepareFullChunkMomentQueries();
        } else {
            [
                'evidence' => $prepareEvidence,
                'folder' => $prepareFolder,
                'tag' => $prepareTag,
                'moment' => $prepareMoment
            ] = $this->preparePartChunkMomentQueries($chunkIds);
        }
        // each loop needs this
        $delParams       = Builder::getArrayParameters($chunkIds, 'moment_id');

        $discussionPlaceholders = Builder::getArrayPlaceholders($discussionThreadIds, 'moment_id');
        $discussionParams       = Builder::getArrayParameters($discussionThreadIds, 'moment_id');

        // unlink evidence // the Garbage Collector will take care of the actual files, we just need to remove the database records
        $prepareEvidence->execute($delParams);

        // unlink folders
        $prepareFolder->execute($delParams);

        // unlink tags
        $prepareTag->execute($delParams);

        // delete the moments
        $prepareMoment->execute($delParams);

        if (!empty($discussionThreadIds)) { // if there are discussion threads to delete, delete the threads and comments
            // if chunk is full, we can use the pre-prepared statements to save some time preparing statements
            if (count($discussionThreadIds) === self::CHUNK_SIZE) {
                [
                    'comment' => $prepareComment,
                    'thread' => $prepareThread,
                ] = $this->prepareFullChunkDiscussionQueries();
            } else {
                [
                    'comment' => $prepareComment,
                    'thread' => $prepareThread,
                ] = $this->preparePartChunkDiscussionQueries($discussionThreadIds);
            }
            // delete the discussion thread comments // there may be a weird d-sync here... I have found in demo moments that were deleted, but threads comments that were not... but they do match to the moment...
            $prepareComment->execute($discussionParams);

            // delete the discussion threads // there may be a weird d-sync here... I have found in demo moments that were deleted, but threads that were not... but they do match to the moment...
            $prepareThread->execute($discussionParams);
        } else { // if there are no discussion threads to delete, set the statement variables to null so we can log them as 0
            $prepareComment = null;
            $prepareThread  = null;
        }

        // if perform is true, delete learning moments older than the retention period
        if ($this->perform) {
            // perform the transaction for the chunk
            $this->database->commit();

            $this->log->notice(
                sprintf(
                    "Deleted '%s' moments, '%s' moment_evidence, '%s' moment_folders, '%s' moment_tags '%s' discussion_threads, '%s' discussion_thread_comments",
                    $prepareMoment->rowCount(),
                    $prepareEvidence->rowCount(),
                    $prepareFolder->rowCount(),
                    $prepareTag->rowCount(),
                    $prepareThread?->rowCount() ?? 0,
                    $prepareComment?->rowCount() ?? 0
                )
            );
        } else {
            // if not performing, roll back the transaction to avoid making changes
            $this->database->rollBack();

            $this->log->notice(
                sprintf(
                    "Would have deleted '%s' moments, '%s' moment_evidence, '%s' moment_folders, '%s' moment_tags '%s' discussion_threads, '%s' discussion_thread_comments",
                    $prepareMoment->rowCount(),
                    $prepareEvidence->rowCount(),
                    $prepareFolder->rowCount(),
                    $prepareTag->rowCount(),
                    $prepareThread?->rowCount() ?? 0,
                    $prepareComment?->rowCount() ?? 0
                )
            );
        }
    }

    /**
     * Prepare the queries we will need to delete a full chunk of moments and their related data
     * this is reused to assist with not preparing each time for multiple chunks
     * @return array
     */
    private function prepareFullChunkMomentQueries(): array
    {
        // build an array length of the chunk size
        $chunkSize = array_fill(0, self::CHUNK_SIZE, 0);
        $delPlaceholders = Builder::getArrayPlaceholders($chunkSize, 'moment_id');
        $statementEvidence = $this->database->prepare("DELETE FROM moment_evidence WHERE moment_id IN {$delPlaceholders}");
        $statementFolder = $this->database->prepare("DELETE FROM moment_folder WHERE moment_id IN {$delPlaceholders}");
        $statementTag = $this->database->prepare("DELETE FROM moment_tag WHERE moment_id IN {$delPlaceholders}");
        $statementMoment = $this->database->prepare("DELETE FROM moment WHERE id IN {$delPlaceholders}");

        return [
            'evidence' => $statementEvidence,
            'folder' => $statementFolder,
            'tag' => $statementTag,
            'moment' => $statementMoment
        ];
    }

    /**
     * Prepare the queries we will need to delete a chunk of moments and their related data, with the specific chunk ids
     * @param array $chunkIds
     * @return array
     */
    private function preparePartChunkMomentQueries(array $chunkIds)
    {
        $delPlaceholders = Builder::getArrayPlaceholders($chunkIds, 'moment_id');
        $statementEvidence = $this->database->prepare("DELETE FROM moment_evidence WHERE moment_id IN {$delPlaceholders}");
        $statementFolder = $this->database->prepare("DELETE FROM moment_folder WHERE moment_id IN {$delPlaceholders}");
        $statementTag = $this->database->prepare("DELETE FROM moment_tag WHERE moment_id IN {$delPlaceholders}");
        $statementMoment = $this->database->prepare("DELETE FROM moment WHERE id IN {$delPlaceholders}");

        return [
            'evidence' => $statementEvidence,
            'folder' => $statementFolder,
            'tag' => $statementTag,
            'moment' => $statementMoment
        ];
    }

    /**
     * Prepare the queries we will need to delete a full chunk of moments and their related data
     * this is reused to assist with not preparing each time for multiple chunks
     * @return array
     */
    private function prepareFullChunkDiscussionQueries(): array
    {
        // build an array length of the chunk size
        $chunkSize = array_fill(0, self::CHUNK_SIZE, 0);
        $discussionPlaceholders = Builder::getArrayPlaceholders($chunkSize, 'moment_id');
        // delete the discussion thread comments // there may be a weird d-sync here... I have found in demo moments that were deleted, but threads comments that were not... but they do match to the moment...
        $statementComment = $this->database->prepare("DELETE FROM discussion_thread_comment WHERE discussion_thread_id IN {$discussionPlaceholders}");
        // delete the discussion threads // there may be a weird d-sync here... I have found in demo moments that were deleted, but threads that were not... but they do match to the moment...
        $statementThread = $this->database->prepare("DELETE FROM discussion_thread WHERE id IN {$discussionPlaceholders}");

        return [
            'comment' => $statementComment,
            'thread' => $statementThread,
        ];
    }

    /**
     * Prepare the queries we will need to delete a chunk of moments and their related data, with the specific chunk ids
     * @param array $discussionThreadIds
     * @return array
     */
    private function preparePartChunkDiscussionQueries(array $discussionThreadIds)
    {
        $discussionPlaceholders = Builder::getArrayPlaceholders($discussionThreadIds, 'moment_id');
        // delete the discussion thread comments // there may be a weird d-sync here... I have found in demo moments that were deleted, but threads comments that were not... but they do match to the moment...
        $statementComment = $this->database->prepare("DELETE FROM discussion_thread_comment WHERE discussion_thread_id IN {$discussionPlaceholders}");
        // delete the discussion threads // there may be a weird d-sync here... I have found in demo moments that were deleted, but threads that were not... but they do match to the moment...
        $statementThread = $this->database->prepare("DELETE FROM discussion_thread WHERE id IN {$discussionPlaceholders}");

        return [
            'comment' => $statementComment,
            'thread' => $statementThread,
        ];
    }
}
