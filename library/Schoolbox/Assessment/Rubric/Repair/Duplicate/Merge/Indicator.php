<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Rubric\Repair\Duplicate\Merge;

use Alaress\Database\Exception;
use Alaress\Database\Statement\Query\IQuery;
use Schoolbox\Assessment\Rubric\Repair\Base;
use Schoolbox\Assessment\Rubric\Util as RubricUtil;
use UnexpectedValueException;

/**
 * Script for merging duplicate rubric indicators.
 *
 * In the past it may have been possible to create more than one indicator with
 * the same name in a capability.
 * This is now validated against, but broken data still exists for some schools.
 *
 * This script:
 * * finds all indicators within a capability
 * * for any duplicate indicators:
 * ** moves usages of the duplicate to the original
 * ** moves descriptors of the duplicate to the original
 * *** moving descriptors will also move marks given to those descriptors,
 *     so they don't need to be moved separately.
 *
 * This script will raise an error (and not continue to merge the indicator)
 * if both the original and duplicate are in use on the same assessment.
 */
class Indicator extends Base
{
    /**************************** Indicator queries ***************************/

    private const FIND_CAPABILITY_INDICATORS_QUERY_V1 = '
        SELECT id, name
        FROM   rubric_criterion
        WHERE  rubric_group_id = :capabilityId
        AND    deleted_at      IS NULL
        ORDER BY name, id
    ';
    private const FIND_CAPABILITY_INDICATORS_QUERY_V2 = '
        SELECT id, name
        FROM   rubric_indicator
        WHERE  rubric_capability_id = :capabilityId
        AND    deleted_at      IS NULL
        ORDER BY name, id
    ';


    /**************************** Usage merging queries ***************************/

    private const FIND_INDICATOR_USAGES_QUERY_V1 = '
        SELECT
            rcu.submission_box_id AS box_id,
            a.id,
            a.title
        FROM       rubric_criterion_usage rcu
        INNER JOIN submission_box         sb ON rcu.submission_box_id = sb.id
        INNER JOIN assessment             a  ON sb.assessment_id = a.id
        WHERE rcu.criterion_id = :indicatorId
        AND   a.deleted_at IS NULL
    ';
    private const FIND_INDICATOR_USAGES_QUERY_V2 = '
        SELECT
            rcu.submission_box_id AS box_id,
            a.id,
            a.title
        FROM       rubric_indicator_usage rcu
        INNER JOIN submission_box         sb ON rcu.submission_box_id = sb.id
        INNER JOIN assessment             a  ON sb.assessment_id = a.id
        WHERE rcu.rubric_indicator_id = :indicatorId
        AND   a.deleted_at IS NULL
    ';

    private const MERGE_INDICATOR_USAGE_QUERY_V1 = '
        UPDATE rubric_criterion_usage
        SET   criterion_id      = :originalIndicatorId
        WHERE criterion_id      = :duplicateIndicatorId
        AND   submission_box_id = :boxId
    ';
    private const MERGE_INDICATOR_USAGE_QUERY_V2 = '
        UPDATE rubric_indicator_usage
        SET   rubric_indicator_id      = :originalIndicatorId
        WHERE rubric_indicator_id      = :duplicateIndicatorId
        AND   submission_box_id = :boxId
    ';

    private const MERGE_INDICATOR_DESCRIPTOR_QUERY_V1 = '
        UPDATE     rubric_descriptor rd
        INNER JOIN rubric_level      rl ON rd.level_id = rl.id
        SET   rd.criterion_id = :originalIndicatorId
        WHERE rd.criterion_id = :duplicateIndicatorId
        AND   rl.box_id       = :boxId
    ';
    private const MERGE_INDICATOR_DESCRIPTOR_QUERY_V2 = '
        UPDATE     rubric_descriptor rd
        INNER JOIN rubric_level      rl ON rd.level_id = rl.id
        SET   rd.rubric_indicator_id = :originalIndicatorId
        WHERE rd.rubric_indicator_id = :duplicateIndicatorId
        AND   rl.box_id       = :boxId
    ';


    /**************************** Prepared indicator merging queries ***************************/

    /**
     * Query for finding all rubric indicators in a capability.
     *
     * @var IQuery
     */
    private $findCapabilityIndicatorsQuery;


    /**************************** Prepared usage merging queries ***************************/

    /**
     * Query for finding all usages of a capability.
     *
     * @var IQuery
     */
    private $findIndicatorUsagesQuery;


    /**
     * Query for merging a usage from a duplicate to the original capability.
     *
     * @var IQuery
     */
    private $mergeIndicatorUsageQuery;


    /**
     * Query for merging a usage from a duplicate to the original capability.
     *
     * @var IQuery
     */
    private $mergeIndicatorDescriptorQuery;


    /**
     * Setup all queries this script requires.
     */
    protected function setupQueries(): void
    {
        $this->findCapabilityIndicatorsQuery
            = $this->db->prepareQuery(
                $this->useV2Queries
                    ? self::FIND_CAPABILITY_INDICATORS_QUERY_V2
                    : self::FIND_CAPABILITY_INDICATORS_QUERY_V1
            );

        $this->findIndicatorUsagesQuery
            = $this->db->prepareQuery(
                $this->useV2Queries
                    ? self::FIND_INDICATOR_USAGES_QUERY_V2
                    : self::FIND_INDICATOR_USAGES_QUERY_V1
            );
        $this->mergeIndicatorUsageQuery
            = $this->db->prepareQuery(
                $this->useV2Queries
                    ? self::MERGE_INDICATOR_USAGE_QUERY_V2
                    : self::MERGE_INDICATOR_USAGE_QUERY_V1
            );
        $this->mergeIndicatorDescriptorQuery
            = $this->db->prepareQuery(
                $this->useV2Queries
                    ? self::MERGE_INDICATOR_DESCRIPTOR_QUERY_V2
                    : self::MERGE_INDICATOR_DESCRIPTOR_QUERY_V1
            );
    }


    /**
     * @inheritDoc
     */
    protected function outputIntro(): void
    {
        $this->log->notice('Merging duplicate indicators');
        $this->log->notice('This script will not merge duplicates which are both used by the same due work');
    }


    /**
     * Run this script's repair process.
     *
     * @throws Exception
     */
    protected function repair(): void
    {
        $this->findFoldersQuery->execute();
        if ($this->findFoldersQuery->rowCount() === 0) {
            $this->log->warning('No folders found - nothing to merge');
        }
        foreach ($this->findFoldersQuery->fetchAll() as $folder) {
            $folderId = (int)$folder['id'];

            // Is there a folder restriction?
            // If so and this folder's outside of it: just skip
            if (!empty($this->folders) && !in_array($folderId, $this->folders, true)) {
                continue;
            }

            $this->log->notice(
                sprintf(
                    'Checking folder #%d (%s)',
                    $folderId,
                    self::truncate($folder['name'], self::MAX_LOG_ITEM_WIDTH)
                )
            );

            // Don't go further if there's no capabilities in the folder
            $this->findCapabilitiesQuery->execute(['folderId' => $folderId]);
            if ($this->findCapabilitiesQuery->rowCount() === 0) {
                $this->log->debug(self::indent(1) . 'folder contains no capabilities: no action required');
                continue;
            }

            foreach ($this->findCapabilitiesQuery->fetchColumnArray('name', 'id') as $capId => $capName) {
                $truncatedName = self::truncate($capName, self::MAX_LOG_ITEM_WIDTH);
                $this->log->notice(sprintf(self::indent(1) . 'Checking capability #%d (%s)', $capId, $truncatedName));
                $this->findCapabilityIndicatorsQuery->execute(['capabilityId' => $capId]);
                if ($this->findCapabilityIndicatorsQuery->rowCount() === 0) {
                    $this->log->debug(self::indent(2) . 'capability contains no indicators: no action required');
                    continue;
                }

                $groupedIndicators = [];
                foreach ($this->findCapabilityIndicatorsQuery->fetchColumnArray('name', 'id') as $indId => $indName) {
                    $groupedIndicators[RubricUtil::normalizeName($indName)][] = $indId;
                }

                $this->mergeIndicators($groupedIndicators, $folderId);
            }
        }
    }


    /**
     * Attempt to merge this set of indicators by removing any duplicates.
     *
     * @param array $groupedIndicators key: indicator name, value: list of indicator IDs
     * @param int   $folderId
     * @throws Exception
     */
    private function mergeIndicators(array $groupedIndicators, int $folderId): void
    {
        foreach ($groupedIndicators as $indicatorName => $indicatorIds) {
            $truncatedName  = self::truncate($indicatorName, self::MAX_LOG_ITEM_WIDTH);
            $indicatorCount = count($indicatorIds);
            if ($indicatorCount <= 1) {
                $this->log->debug(
                    self::indent(2) . sprintf('indicator "%s" has no duplicates: skipping', $truncatedName)
                );
                continue;
            }

            // We have duplicates. Undupe them
            $this->log->notice(
                self::indent(2) . sprintf('indicator "%s" has %d duplicate(s)', $truncatedName, $indicatorCount - 1)
            );


            // Merge backwards from the last duplicate into the original.
            $destinationIndicatorId = array_shift($indicatorIds);
            $indicatorIds           = array_reverse($indicatorIds);

            foreach ($indicatorIds as $sourceIndicatorId) {
                $conflict = false;
                $result   = [
                    'id'       => $sourceIndicatorId,
                    'name'     => $indicatorName,
                    'folderId' => $folderId,
                ];
                $this->createSavepoint();
                try {
                    // do stuff
                    $this->log->notice(
                        self::indent(3)
                        . sprintf(
                            'merging duplicate #%d into original #%d',
                            $sourceIndicatorId,
                            $destinationIndicatorId
                        )
                    );
                    $this->mergeIndicatorUsages($destinationIndicatorId, $sourceIndicatorId, $folderId);
                    $this->fixedIndicators[$sourceIndicatorId] = $result;
                } catch (UnexpectedValueException $e) {
                    $conflict                                   = true;
                    $this->failedIndicators[$sourceIndicatorId] = $result;
                }

                if ($conflict) {
                    $this->rollbackSavepoint();
                } else {
                    $this->commitSavepoint();
                }
            }
        }
    }


    /**
     * Merge usages and marks for the indicator IDs provided.
     *
     * Each due work which uses `$sourceIndicatorId` has its usages and marks
     * merged to `$destinationIndicatorId`.
     *
     * However, if the due work uses both source and destination indicators,
     * that is an error: it will be reported, and merging of that indicator
     * will be rolled back.
     *
     * (Marks for source and destination indicators don't need to be checked
     *  separately, because the mark can't exist without a usage.)
     *
     * @param int $destinationIndicatorId
     * @param int $sourceIndicatorId
     * @param int $folderId
     * @throws UnexpectedValueException if due work uses both indicators
     */
    private function mergeIndicatorUsages(int $destinationIndicatorId, int $sourceIndicatorId, int $folderId): void
    {
        // Which due works use the original indicator?
        $this->findIndicatorUsagesQuery->execute(['indicatorId' => $destinationIndicatorId]);
        $originalDueWorkIds = $this->findIndicatorUsagesQuery->fetchColumnArray('id');

        // And which use the duplicate?
        $this->findIndicatorUsagesQuery->execute(['indicatorId' => $sourceIndicatorId]);
        $duplicateDueWorks = $this->findIndicatorUsagesQuery->fetchAll();

        $failedDueWorks = [];
        foreach ($duplicateDueWorks as $duplicateDueWork) {
            $this->log->notice(
                self::indent(4)
                . sprintf(
                    'moving usages and marks of indicator in due work #%d (%s) into original',
                    $duplicateDueWork['id'],
                    $duplicateDueWork['title']
                )
            );

            // Do any due works use both the source and destination indicator?
            // If so that's an error.
            $result = [
                'id'       => $duplicateDueWork['id'],
                'name'     => $duplicateDueWork['title'],
                'folderId' => $folderId,
            ];
            if (in_array($duplicateDueWork['id'], $originalDueWorkIds, true)) {
                $this->log->warning(
                    self::indent(5) . 'indicator is used by both source and destination due work: aborting'
                );
                $failedDueWorks[$duplicateDueWork['id']] = $result;
                continue;
            }

            $this->fixedDueWorks[$duplicateDueWork['id']] = $result;

            $this->mergeIndicatorUsageQuery->execute([
                'originalIndicatorId'  => $destinationIndicatorId,
                'duplicateIndicatorId' => $sourceIndicatorId,
                'boxId'                => $duplicateDueWork['box_id'],
            ]);

            $this->mergeIndicatorDescriptorQuery->execute([
                'originalIndicatorId'  => $destinationIndicatorId,
                'duplicateIndicatorId' => $sourceIndicatorId,
                'boxId'                => $duplicateDueWork['box_id'],
            ]);
        }

        // Did any fail? Log and report later
        if (!empty($failedDueWorks)) {
            /** @noinspection AdditionOperationOnArraysInspection */
            $this->failedDueWorks += $failedDueWorks;
            throw new UnexpectedValueException('One or more due works failed to merge');
        }
    }
}
