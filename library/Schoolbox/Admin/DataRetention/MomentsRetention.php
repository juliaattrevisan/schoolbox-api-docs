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

use Alaress\Database\Predicate\Builder;
use Schoolbox\Config\ConfigMapper;

class MomentsRetention extends StrategyBase
{
    public string $retentionConfigKey = ConfigMapper::NAME_DATA_RETENTION_LEARNINGMOMENTS;
    /**
     * @inheritDoc
     */

    /**
     * @inheritDoc
     */
    public function getRetentionLabel(): string
    {
        return 'Learning Moment Retention';
    }

    public function getStrategyName(): string
    {
        return 'moments';
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
                (SELECT COUNT(*) FROM moment WHERE moment.deleted_at IS NOT NULL AND moment.deleted_at < :old) AS moments,
                (SELECT COUNT(*) FROM moment_evidence me WHERE EXISTS (SELECT 1 FROM moment m WHERE m.id = me.moment_id AND m.deleted_at IS NOT NULL AND m.deleted_at < :old)) AS evidence,
                (SELECT COUNT(*) FROM moment_folder mf WHERE EXISTS (SELECT 1 FROM moment m2 WHERE m2.id = mf.moment_id AND m2.deleted_at IS NOT NULL AND m2.deleted_at < :old)) AS folders,
                (SELECT COUNT(*) FROM moment_tag mt WHERE EXISTS (SELECT 1 FROM moment m3 WHERE m3.id = mt.moment_id AND m3.deleted_at IS NOT NULL AND m3.deleted_at < :old)) AS tags,
                (SELECT COUNT(DISTINCT dt.id) FROM discussion_thread dt WHERE EXISTS (SELECT 1 FROM moment m4 WHERE m4.discussion_thread_id = dt.id AND m4.deleted_at IS NOT NULL AND m4.deleted_at < :old)) AS threads,
                (SELECT COUNT(DISTINCT dtc.id) FROM discussion_thread_comment dtc WHERE EXISTS (SELECT 1 FROM moment m5 WHERE m5.discussion_thread_id = dtc.discussion_thread_id AND m5.deleted_at IS NOT NULL AND m5.deleted_at < :old)) AS comments,
                (SELECT COUNT(*) FROM curriculum_moment cm WHERE EXISTS (SELECT 1 FROM moment m6 WHERE m6.id = cm.moment_id AND m6.deleted_at IS NOT NULL AND m6.deleted_at < :old)) AS curriculum_moments
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
        $this->log->info(sprintf("With '%s' curriculum_moments to delete", $toDelete['curriculum_moments']));

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
        WHERE moment.deleted_at IS NOT NULL AND moment.deleted_at < :old
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

        // some value which will never be an ID
        $nonExistentId = -1;

        // fill $chunkIds up to $this->chunkSize with $nonExistentId
        $chunkIds  = array_merge($chunkIds, array_fill(0, $this->chunkSize - count($chunkIds), $nonExistentId));

        $delPlaceholders = Builder::getArrayPlaceholders($chunkIds, 'moment_id');
        $statementEvidence = $this->database->prepare($this->deleteStatement() . " FROM moment_evidence WHERE moment_id IN {$delPlaceholders}");
        $statementFolder = $this->database->prepare($this->deleteStatement() . " FROM moment_folder WHERE moment_id IN {$delPlaceholders}");
        $statementTag = $this->database->prepare($this->deleteStatement() . " FROM moment_tag WHERE moment_id IN {$delPlaceholders}");
        $statementCurriculum = $this->database->prepare($this->deleteStatement() . " FROM curriculum_moment WHERE moment_id IN {$delPlaceholders}");
        $statementMoment = $this->database->prepare($this->deleteStatement() . " FROM moment WHERE id IN {$delPlaceholders}");

        // build params
        $delParams       = Builder::getArrayParameters($chunkIds, 'moment_id');

        // unlink evidence // the Garbage Collector will take care of the actual files, we just need to remove the database records
        $statementEvidence->execute($delParams);

        // unlink folders
        $statementFolder->execute($delParams);

        // unlink tags
        $statementTag->execute($delParams);

        // unlink curriculum node relations
        $statementCurriculum->execute($delParams);

        // delete the moments
        $statementMoment->execute($delParams);

        if (!empty($discussionThreadIds)) { // if there are discussion threads to delete, delete the threads and comments
            // fill $discussionThreadIds up to $this->chunkSize with $nonExistentId
            $discussionThreadIds  = array_merge($discussionThreadIds, array_fill(0, $this->chunkSize - count($discussionThreadIds), $nonExistentId));
            $discussionPlaceholders = Builder::getArrayPlaceholders($discussionThreadIds, 'moment_id');

            // delete the discussion thread comments // there may be a weird d-sync here... I have found in demo moments that were deleted, but threads comments that were not... but they do match to the moment...
            $statementComment = $this->database->prepare($this->deleteStatement() . " FROM discussion_thread_comment WHERE discussion_thread_id IN {$discussionPlaceholders}");
            // delete the discussion threads // there may be a weird d-sync here... I have found in demo moments that were deleted, but threads that were not... but they do match to the moment...
            $statementThread = $this->database->prepare($this->deleteStatement() . " FROM discussion_thread WHERE id IN {$discussionPlaceholders}");

            $discussionParams       = Builder::getArrayParameters($discussionThreadIds, 'moment_id');

            // delete the discussion thread comments // there may be a weird d-sync here... I have found in demo moments that were deleted, but threads comments that were not... but they do match to the moment...
            $statementComment->execute($discussionParams);

            // delete the discussion threads // there may be a weird d-sync here... I have found in demo moments that were deleted, but threads that were not... but they do match to the moment...
            $statementThread->execute($discussionParams);
        } else { // if there are no discussion threads to delete, set the statement variables to null so we can log them as 0
            $statementComment = null;
            $statementThread  = null;
        }

        // if perform is true, delete learning moments older than the retention period
        if ($this->perform) {
            $this->log->notice(
                sprintf(
                    "Deleted '%s' moments, '%s' moment_evidence, '%s' moment_folders, '%s' moment_tags, '%s' curriculum_moments, '%s' discussion_threads, '%s' discussion_thread_comments",
                    $statementMoment->rowCount(),
                    $statementEvidence->rowCount(),
                    $statementFolder->rowCount(),
                    $statementTag->rowCount(),
                    $statementCurriculum->rowCount(),
                    $statementThread?->rowCount() ?? 0,
                    $statementComment?->rowCount() ?? 0
                )
            );
        } else {
            $this->log->notice(
                sprintf(
                    "Would have deleted '%s' moments, '%s' moment_evidence, '%s' moment_folders, '%s' moment_tags, '%s' curriculum_moments, '%s' discussion_threads, '%s' discussion_thread_comments",
                    $statementMoment->rowCount(),
                    $statementEvidence->rowCount(),
                    $statementFolder->rowCount(),
                    $statementTag->rowCount(),
                    $statementCurriculum->rowCount(),
                    $statementThread?->rowCount() ?? 0,
                    $statementComment?->rowCount() ?? 0
                )
            );
        }
    }
}
