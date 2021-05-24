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
 * Script for merging duplicate rubric capabilities.
 *
 * In the past it may have been possible to create more than one capability
 * with the same name in a folder, and more than one indicator with the same
 * name in a capability.
 * This is now validated against, but broken data still exists for some schools.
 *
 * This script:
 * * finds all capabilities within a folder
 * * for any duplicate capabilities:
 * ** moves indicators within the duplicate into the original
 * ** moves usages of the duplicate into the original
 * ** moves any marks given to the duplicate into the original
 *
 * This script will raise an error (and not continue to merge the capability)
 * if both the original and duplicate are in use on the same assessment.
 */
class Capability extends Base
{
    /**************************** Indicator merging queries ***************************/

    private const FIND_CAPABILITY_INDICATORS_QUERY_V1 = '
        SELECT id, name
        FROM   rubric_criterion
        WHERE  rubric_group_id = :capabilityId
        AND    deleted_at IS NULL
        ORDER BY name, id
    ';
    private const FIND_CAPABILITY_INDICATORS_QUERY_V2 = '
        SELECT id, name
        FROM   rubric_indicator
        WHERE  rubric_capability_id = :capabilityId
        AND    deleted_at IS NULL
        ORDER BY name, id
    ';

    private const MERGE_CAPABILITY_INDICATOR_QUERY_V1 = '
        UPDATE rubric_criterion
        SET rubric_group_id = :originalCapabilityId
        WHERE rubric_group_id = :duplicateCapabilityId
        AND id = :indicatorId
    ';
    private const MERGE_CAPABILITY_INDICATOR_QUERY_V2 = '
        UPDATE rubric_indicator
        SET rubric_capability_id = :originalCapabilityId
        WHERE rubric_capability_id = :duplicateCapabilityId
        AND id = :indicatorId
    ';


    /**************************** Usage merging queries ***************************/

    private const FIND_CAPABILITY_USAGES_QUERY_V1 = '
        SELECT
            rgu.submission_box_id AS box_id,
            a.id,
            a.title
        FROM rubric_group_usage rgu
        INNER JOIN submission_box sb ON rgu.submission_box_id = sb.id
        INNER JOIN assessment     a  ON sb.assessment_id = a.id
        WHERE rgu.rubric_group_id = :capabilityId
        AND   a.deleted_at IS NULL
    ';
    private const FIND_CAPABILITY_USAGES_QUERY_V2 = '
        SELECT
            rgu.submission_box_id AS box_id,
            a.id,
            a.title
        FROM rubric_capability_usage rgu
        INNER JOIN submission_box sb ON rgu.submission_box_id = sb.id
        INNER JOIN assessment     a  ON sb.assessment_id = a.id
        WHERE rgu.rubric_capability_id = :capabilityId
        AND   a.deleted_at IS NULL
    ';

    private const MERGE_CAPABILITY_USAGE_QUERY_V1 = '
        UPDATE rubric_group_usage
        SET rubric_group_id = :originalCapabilityId
        WHERE rubric_group_id = :duplicateCapabilityId
        AND submission_box_id = :boxId
    ';
    private const MERGE_CAPABILITY_USAGE_QUERY_V2 = '
        UPDATE rubric_capability_usage
        SET rubric_capability_id = :originalCapabilityId
        WHERE rubric_capability_id = :duplicateCapabilityId
        AND submission_box_id = :boxId
    ';

    private const MERGE_CAPABILITY_MARK_QUERY_V1 = '
        UPDATE rubric_group_mark rm
        INNER JOIN rubric_group_usage rgu ON rm.rubric_group_id = rgu.rubric_group_id
        SET rm.rubric_group_id = :originalCapabilityId
        WHERE rm.rubric_group_id = :duplicateCapabilityId
        AND rgu.submission_box_id = :boxId
    ';
    private const MERGE_CAPABILITY_MARK_QUERY_V2 = '
        UPDATE rubric_capability_mark rm
        INNER JOIN rubric_capability_usage rgu ON rm.rubric_capability_id = rgu.rubric_capability_id
        SET rm.rubric_capability_id = :originalCapabilityId
        WHERE rm.rubric_capability_id = :duplicateCapabilityId
        AND rgu.submission_box_id = :boxId
    ';


    /**************************** Prepared indicator merging queries ***************************/

    /**
     * Query for finding all rubric indicators in a capability.
     *
     * @var IQuery
     */
    private $findCapabilityIndicatorsQuery;


    /**
     * Query for merging an indicator from a duplicate to the original capability.
     *
     * @var IQuery
     */
    private $mergeCapabilityIndicatorQuery;


    /**************************** Prepared usage merging queries ***************************/

    /**
     * Query for finding all usages of a capability.
     *
     * @var IQuery
     */
    private $findCapabilityUsagesQuery;


    /**
     * Query for merging a usage from a duplicate to the original capability.
     *
     * @var IQuery
     */
    private $mergeCapabilityUsageQuery;


    /**
     * Query for merging a usage from a duplicate to the original capability.
     *
     * @var IQuery
     */
    private $mergeCapabilityMarkQuery;


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
        $this->mergeCapabilityIndicatorQuery
            = $this->db->prepareQuery(
                $this->useV2Queries
                    ? self::MERGE_CAPABILITY_INDICATOR_QUERY_V2
                    : self::MERGE_CAPABILITY_INDICATOR_QUERY_V1
            );

        $this->findCapabilityUsagesQuery
            = $this->db->prepareQuery(
                $this->useV2Queries
                    ? self::FIND_CAPABILITY_USAGES_QUERY_V2
                    : self::FIND_CAPABILITY_USAGES_QUERY_V1
            );
        $this->mergeCapabilityUsageQuery
            = $this->db->prepareQuery(
                $this->useV2Queries
                    ? self::MERGE_CAPABILITY_USAGE_QUERY_V2
                    : self::MERGE_CAPABILITY_USAGE_QUERY_V1
            );
        $this->mergeCapabilityMarkQuery
            = $this->db->prepareQuery(
                $this->useV2Queries
                    ? self::MERGE_CAPABILITY_MARK_QUERY_V2
                    : self::MERGE_CAPABILITY_MARK_QUERY_V1
            );
    }


    /**
     * @inheritDoc
     */
    protected function outputIntro(): void
    {
        $this->log->notice('Merging duplicate capabilities');
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

            $groupedCapabilities = [];
            foreach ($this->findCapabilitiesQuery->fetchColumnArray('name', 'id') as $id => $name) {
                $groupedCapabilities[RubricUtil::normalizeName($name)][] = $id;
            }

            $this->mergeCapabilities($groupedCapabilities, $folderId);
        }
    }


    /**
     * Attempt to merge this set of capabilities by removing any duplicates.
     *
     * @param array $groupedCapabilities key: capability name, value: list of capability IDs
     * @param int   $folderId
     */
    private function mergeCapabilities(array $groupedCapabilities, int $folderId): void
    {
        foreach ($groupedCapabilities as $capabilityName => $capabilityIds) {
            $truncatedName   = self::truncate($capabilityName, self::MAX_LOG_ITEM_WIDTH);
            $capabilityCount = count($capabilityIds);
            if ($capabilityCount <= 1) {
                $this->log->debug(
                    self::indent(1) . sprintf('capability "%s" has no duplicates: skipping', $truncatedName)
                );
                continue;
            }

            // We have duplicates. Undupe them
            $this->log->notice(
                self::indent(1) . sprintf('capability "%s" has %d duplicate(s)', $truncatedName, $capabilityCount - 1)
            );


            // Merge backwards from the last duplicate into the original.
            $destinationCapabilityId = array_shift($capabilityIds);
            $capabilityIds           = array_reverse($capabilityIds);
            foreach ($capabilityIds as $sourceCapabilityId) {
                $conflict = false;
                $result   = [
                    'id'       => $sourceCapabilityId,
                    'name'     => $capabilityName,
                    'folderId' => $folderId,
                ];
                $this->createSavepoint();
                try {
                    // do stuff
                    $this->log->notice(
                        self::indent(2)
                        . sprintf(
                            'merging duplicate #%d into original #%d',
                            $sourceCapabilityId,
                            $destinationCapabilityId
                        )
                    );
                    $this->mergeCapabilityIndicators($destinationCapabilityId, $sourceCapabilityId);
                    $this->mergeCapabilityUsages($destinationCapabilityId, $sourceCapabilityId, $folderId);
                    $this->fixedCapabilities[$sourceCapabilityId] = $result;
                } catch (UnexpectedValueException $e) {
                    $conflict                                      = true;
                    $this->failedCapabilities[$sourceCapabilityId] = $result;
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
     * Merge indicators for the capability IDs provided.
     *
     * All indicators from `$sourceCapabilityId` are merged into
     * `$destinationCapabilityId`.
     *
     * Indicators which already exist in the original are still merged: there
     * will be another script which merges the duplicate indicators themselves.
     *
     * @param int $destinationCapabilityId
     * @param int $sourceCapabilityId
     */
    private function mergeCapabilityIndicators(
        int $destinationCapabilityId,
        int $sourceCapabilityId
    ): void {
        // Which indicators are within the original capability?
        $this->findCapabilityIndicatorsQuery->execute(['capabilityId' => $destinationCapabilityId]);
        $originalIndicators = $this->findCapabilityIndicatorsQuery->fetchColumnArray('name', 'id');

        // And which are in the duplicate?
        $this->findCapabilityIndicatorsQuery->execute(['capabilityId' => $sourceCapabilityId]);
        $duplicateIndicators = $this->findCapabilityIndicatorsQuery->fetchColumnArray('name', 'id');

        foreach ($duplicateIndicators as $duplicateIndicatorId => $duplicateIndicatorName) {
            $this->log->notice(
                self::indent(3)
                . sprintf(
                    'moving indicator "%s" into original',
                    self::truncate($duplicateIndicatorName, self::MAX_LOG_ITEM_WIDTH)
                )
            );

            if (in_array($duplicateIndicatorId, $originalIndicators, true)) {
                $this->log->warning(
                    self::indent(4) . 'indicator already exists in original: will need to deduplicate'
                );
            }

            $this->mergeCapabilityIndicatorQuery->execute([
                'originalCapabilityId'  => $destinationCapabilityId,
                'duplicateCapabilityId' => $sourceCapabilityId,
                'indicatorId'           => $duplicateIndicatorId,
            ]);
        }
    }


    /**
     * Merge usages and marks for the capability IDs provided.
     *
     * Each due work which uses `$sourceCapabilityId` has its usages and marks
     * merged to `$destinationCapabilityId`.
     *
     * However, if the due work uses both source and destination capabilities,
     * that is an error: it will be reported, and merging of that capability
     * will be rolled back.
     *
     * (Marks for source and destination capabilities don't need to be checked
     *  separately, because the mark can't exist without a usage.)
     *
     * @param int $destinationCapabilityId
     * @param int $sourceCapabilityId
     * @param int $folderId
     * @throws UnexpectedValueException if due work uses both capabilities
     */
    private function mergeCapabilityUsages(int $destinationCapabilityId, int $sourceCapabilityId, int $folderId): void
    {
        // Which due works use the original capability?
        $this->findCapabilityUsagesQuery->execute(['capabilityId' => $destinationCapabilityId]);
        $originalDueWorkIds = $this->findCapabilityUsagesQuery->fetchColumnArray('id');

        // And which use the duplicate?
        $this->findCapabilityUsagesQuery->execute(['capabilityId' => $sourceCapabilityId]);
        $duplicateDueWorks = $this->findCapabilityUsagesQuery->fetchAll();

        $failedDueWorks = [];
        foreach ($duplicateDueWorks as $duplicateDueWork) {
            $this->log->notice(
                self::indent(3)
                . sprintf(
                    'moving usages and marks of capability in due work #%d (%s) into original',
                    $duplicateDueWork['id'],
                    $duplicateDueWork['title']
                )
            );

            // Do any due works use both the source and destination capability?
            // If so that's an error.
            $result = [
                'id'       => $duplicateDueWork['id'],
                'name'     => $duplicateDueWork['title'],
                'folderId' => $folderId,
            ];
            if (in_array($duplicateDueWork['id'], $originalDueWorkIds, true)) {
                $this->log->warning(
                    self::indent(4) . 'capability is used by both source and destination due work: aborting'
                );
                $failedDueWorks[$duplicateDueWork['id']] = $result;
                continue;
            }

            $this->fixedDueWorks[$duplicateDueWork['id']] = $result;

            $this->mergeCapabilityUsageQuery->execute([
                'originalCapabilityId'  => $destinationCapabilityId,
                'duplicateCapabilityId' => $sourceCapabilityId,
                'boxId'                 => $duplicateDueWork['box_id'],
            ]);

            $this->mergeCapabilityMarkQuery->execute([
                'originalCapabilityId'  => $destinationCapabilityId,
                'duplicateCapabilityId' => $sourceCapabilityId,
                'boxId'                 => $duplicateDueWork['box_id'],
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
