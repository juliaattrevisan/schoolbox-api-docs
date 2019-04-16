<?php
/**
 * @copyright Copyright 2019, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Rubric\Repair\Duplicate\Merge;

use Alaress\Database\Exception;
use Alaress\Database\Statement\Query\IQuery;
use RuntimeException;
use Schoolbox\Assessment\Rubric\Repair\Base;
use Scrub;
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

    private const FIND_CAPABILITY_INDICATORS_QUERY = '
        SELECT id, name
        FROM   rubric_criterion
        WHERE  rubric_group_id = :capabilityId
        AND    deleted_at IS NULL
        ORDER BY name, id
    ';

    private const MERGE_CAPABILITY_INDICATOR_QUERY = '
        UPDATE rubric_criterion
        SET rubric_group_id = :originalCapabilityId
        WHERE rubric_group_id = :duplicateCapabilityId
        AND id = :indicatorId
    ';


    /**************************** Usage merging queries ***************************/

    private const FIND_CAPABILITY_USAGES_QUERY = '
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

    private const MERGE_CAPABILITY_USAGE_QUERY = '
        UPDATE rubric_group_usage
        SET rubric_group_id = :originalCapabilityId
        WHERE rubric_group_id = :duplicateCapabilityId
        AND submission_box_id = :boxId
    ';

    private const MERGE_CAPABILITY_MARK_QUERY = '
        UPDATE rubric_group_mark rm
        INNER JOIN rubric_group_usage rgu ON rm.rubric_group_id = rgu.rubric_group_id
        SET rm.rubric_group_id = :originalCapabilityId
        WHERE rm.rubric_group_id = :duplicateCapabilityId
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
            = $this->db->prepareQuery(self::FIND_CAPABILITY_INDICATORS_QUERY);
        $this->mergeCapabilityIndicatorQuery
            = $this->db->prepareQuery(self::MERGE_CAPABILITY_INDICATOR_QUERY);

        $this->findCapabilityUsagesQuery
            = $this->db->prepareQuery(self::FIND_CAPABILITY_USAGES_QUERY);
        $this->mergeCapabilityUsageQuery
            = $this->db->prepareQuery(self::MERGE_CAPABILITY_USAGE_QUERY);
        $this->mergeCapabilityMarkQuery
            = $this->db->prepareQuery(self::MERGE_CAPABILITY_MARK_QUERY);
    }


    /**
     * @inheritDoc
     */
    protected function summarizeResult(): void
    {
        // Report on which due works must be merged manually, if any.
        if (!empty($this->failedDueWorks)) {
            $subject = sprintf(
                '%d due work(s) must be fixed manually before merge can complete',
                count($this->failedDueWorks)
            );
            $this->log->warning($subject);
            $failedDueWorkUrls = [];
            foreach ($this->failedDueWorks as $failedDueWorkId) {
                $failedDueWorkUrls[] = $failedDueWorkUrl = sprintf(
                    'https://%s/learning/assessments/%d/modify',
                    $this->fqdn,
                    $failedDueWorkId
                );
                $this->log->warning(self::indent(1) . $failedDueWorkUrl);
            }

            $this->sendMail(
                $subject,
                ['output' => implode('<br />', array_map([Scrub::class, 'text',], $failedDueWorkUrls)),]
            );

            throw new RuntimeException('Merging incomplete, could not merge non-destructively.');
        }
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
            $this->log->critical('No folders found - nothing to merge');
        }
        foreach ($this->findFoldersQuery->fetchAll() as $folder) {
            $folderId = (int)$folder['id'];

            // Is there a folder restriction?
            // If so and this folder's outside of it: just skip
            if (!empty($this->folders) && !in_array($folderId, $this->folders, true)) {
                continue;
            }

            $this->log->info(
                sprintf(
                    'Checking folder #%d (%s)',
                    $folderId,
                    $folder['name']
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
                $groupedCapabilities[$name][] = $id;
            }

            $this->mergeCapabilities($groupedCapabilities);
        }
    }


    /**
     * Attempt to merge this set of capabilities by removing any duplicates.
     *
     * @param array $groupedCapabilities key: capability name, value: list of capability IDs
     * @throws Exception
     */
    private function mergeCapabilities(array $groupedCapabilities): void
    {
        foreach ($groupedCapabilities as $capabilityName => $capabilityIds) {
            $capabilityCount = count($capabilityIds);
            if ($capabilityCount <= 1) {
                $this->log->debug(
                    self::indent(1) . sprintf('capability "%s" has no duplicates: skipping', $capabilityName)
                );
                continue;
            }

            // We have duplicates. Undupe them
            $this->log->notice(
                self::indent(1) . sprintf('capability "%s" has %d duplicate(s)', $capabilityName, $capabilityCount - 1)
            );


            // Build pairs to merge into one another.
            // Merge backwards from the last duplicate into the previous one,
            // ending up at the last.
            $capabilityIds = array_reverse($capabilityIds);
            while (count($capabilityIds) >= 2) {
                $sourceCapabilityId = array_shift($capabilityIds);
                foreach ($capabilityIds as $destinationCapabilityId) {
                    $conflict = false;
                    $this->db->beginTransaction();
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
                        $this->mergeCapabilityUsages($destinationCapabilityId, $sourceCapabilityId);
                    } catch (UnexpectedValueException $e) {
                        $conflict = true;
                    }

                    if ($conflict) {
                        $this->db->rollBack();
                    } elseif (!$this->perform()) {
                        $this->db->rollBack();
                    } else {
                        $this->db->commit();
                    }
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
    private function mergeCapabilityIndicators(int $destinationCapabilityId, int $sourceCapabilityId): void
    {
        // Which indicators are within the original capability?
        $this->findCapabilityIndicatorsQuery->execute(['capabilityId' => $destinationCapabilityId]);
        $originalIndicators = $this->findCapabilityIndicatorsQuery->fetchColumnArray('name', 'id');

        // And which are in the duplicate?
        $this->findCapabilityIndicatorsQuery->execute(['capabilityId' => $sourceCapabilityId]);
        $duplicateIndicators = $this->findCapabilityIndicatorsQuery->fetchColumnArray('name', 'id');

        foreach ($duplicateIndicators as $duplicateIndicatorId => $duplicateIndicatorName) {
            $this->log->notice(
                self::indent(3) . sprintf('moving indicator "%s" into original', $duplicateIndicatorName)
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
     * @throws UnexpectedValueException if due work uses both capabilities
     */
    private function mergeCapabilityUsages(int $destinationCapabilityId, int $sourceCapabilityId): void
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
            if (in_array($duplicateDueWork['id'], $originalDueWorkIds, true)) {
                $this->log->warning(
                    self::indent(4) . 'capability is used by both source and destination due work: aborting'
                );
                $failedDueWorks[$duplicateDueWork['id']] = [
                    'id'   => $duplicateDueWork['id'],
                    'name' => $duplicateDueWork['title'],
                ];
                continue;
            }

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
