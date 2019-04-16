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
 * Script for merging duplicate rubric indicators.
 *
 * In the past it may have been possible to create more than one indicator with
 * the same name in a capability.
 * This is now validated against, but broken data still exists for some schools.
 *
 * This script:
 * * finds all indicators within a capability
 * * for any duplicate indicators:
 * ** moves usages within the duplicate into the original
 * ** moves descriptors within the duplicate into the original
 * *** moving descriptors will also move marks given to those descriptors,
 *     so they don't need to be moved separately.
 *
 * This script will raise an error (and not continue to merge the capability)
 * if both the original and duplicate are in use on the same assessment.
 */
class Indicator extends Base
{
    /**************************** Indicator queries ***************************/

    private const FIND_CAPABILITY_INDICATORS_QUERY = '
        SELECT id, name
        FROM   rubric_criterion
        WHERE  rubric_group_id = :capabilityId
        AND    deleted_at      IS NULL
        ORDER BY name, id
    ';


    /**************************** Usage merging queries ***************************/

    private const FIND_INDICATOR_USAGES_QUERY = '
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

    private const MERGE_INDICATOR_USAGE_QUERY = '
        UPDATE rubric_criterion_usage
        SET   criterion_id      = :originalIndicatorId
        WHERE criterion_id      = :duplicateIndicatorId
        AND   submission_box_id = :boxId
    ';

    private const MERGE_INDICATOR_DESCRIPTOR_QUERY = '
        UPDATE     rubric_descriptor rd
        INNER JOIN rubric_level      rl ON rd.level_id = rl.id
        SET   rd.criterion_id = :originalIndicatorId
        WHERE rd.criterion_id = :duplicateIndicatorId
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
            = $this->db->prepareQuery(self::FIND_CAPABILITY_INDICATORS_QUERY);

        $this->findIndicatorUsagesQuery
            = $this->db->prepareQuery(self::FIND_INDICATOR_USAGES_QUERY);
        $this->mergeIndicatorUsageQuery
            = $this->db->prepareQuery(self::MERGE_INDICATOR_USAGE_QUERY);
        $this->mergeIndicatorDescriptorQuery
            = $this->db->prepareQuery(self::MERGE_INDICATOR_DESCRIPTOR_QUERY);
    }


//    /**
//     * @inheritDoc
//     */
//    protected function summarizeResult(): void
//    {
//        // Report on which due works must be merged manually, if any.
//        if (!empty($this->failedDueWorks)) {
//            $subject = sprintf(
//                '%d due work(s) must be fixed manually before merge can complete',
//                count($this->failedDueWorks)
//            );
//            $this->log->warning($subject);
//            $failedDueWorkUrls = [];
//            foreach ($this->failedDueWorks as $failedDueWorkId) {
//                $failedDueWorkUrls[] = $failedDueWorkUrl = sprintf(
//                    'https://%s/learning/assessments/%d/modify',
//                    $this->fqdn,
//                    $failedDueWorkId
//                );
//                $this->log->warning(self::indent(1) . $failedDueWorkUrl);
//            }
//
//            $this->sendMail(
//                $subject,
//                ['output' => implode('<br />', array_map([Scrub::class, 'text',], $failedDueWorkUrls)),]
//            );
//
//            throw new RuntimeException('Merging incomplete, could not merge non-destructively.');
//        }
//    }


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

            foreach ($this->findCapabilitiesQuery->fetchColumnArray('name', 'id') as $capId => $capName) {
                $this->log->info(sprintf(self::indent(1) . 'Checking capability #%d (%s)', $capId, $capName));
                $this->findCapabilityIndicatorsQuery->execute(['capabilityId' => $capId]);
                if ($this->findCapabilityIndicatorsQuery->rowCount() === 0) {
                    $this->log->debug(self::indent(2) . 'capability contains no indicators: no action required');
                    continue;
                }

                $groupedIndicators = [];
                foreach ($this->findCapabilityIndicatorsQuery->fetchColumnArray('name', 'id') as $indId => $indName) {
                    $groupedIndicators[$indName][] = $indId;
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
            $indicatorCount = count($indicatorIds);
            if ($indicatorCount <= 1) {
                $this->log->debug(
                    self::indent(2) . sprintf('indicator "%s" has no duplicates: skipping', $indicatorName)
                );
                continue;
            }

            // We have duplicates. Undupe them
            $this->log->notice(
                self::indent(2) . sprintf('indicator "%s" has %d duplicate(s)', $indicatorName, $indicatorCount - 1)
            );


            // Build pairs to merge into one another.
            // Merge backwards from the last duplicate into the previous one,
            // ending up at the last.
            $indicatorIds = array_reverse($indicatorIds);
            while (count($indicatorIds) >= 2) {
                $sourceIndicatorId = array_shift($indicatorIds);
                foreach ($indicatorIds as $destinationIndicatorId) {
                    $conflict = false;
                    $this->db->beginTransaction();
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
