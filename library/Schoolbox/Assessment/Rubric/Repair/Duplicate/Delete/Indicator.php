<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Rubric\Repair\Duplicate\Delete;

use Alaress\Database\Exception;
use Alaress\Database\Statement\Query\IQuery;
use Schoolbox\Assessment\Rubric\Repair\Base;
use Schoolbox\Assessment\Rubric\Util as RubricUtil;

/**
 * Script for deleting empty duplicate rubric indicators.
 *
 * In the past it may have been possible to create more than one indicator with
 * the same name in a capability.
 * This is now validated against, but broken data still exists for some schools.
 *
 * This script:
 * * finds all capabilities within a folder
 * * for any duplicate indicators:
 * ** if it's not in use on any due works:
 * *** deletes the capability
 *
 * This script will refuse to delete a duplicate indicator which is still in
 * use on a due work.
 */
class Indicator extends Base
{
    /**************************** Capability queries ***************************/

    private const DELETE_INDICATOR_QUERY_V1 = '
        UPDATE rubric_criterion
        SET    deleted_at = :now
        WHERE  id         = :indicatorId
    ';
    private const DELETE_INDICATOR_QUERY_V2 = '
        UPDATE rubric_indicator
        SET    deleted_at = :now
        WHERE  id         = :indicatorId
    ';


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


    /**************************** Usage queries ***************************/

    private const COUNT_INDICATOR_USAGES_QUERY_V1 = '
        SELECT COUNT(*)
        FROM       rubric_criterion_usage rcu
        INNER JOIN submission_box         sb ON rcu.submission_box_id = sb.id
        INNER JOIN assessment             a  ON sb.assessment_id = a.id
        WHERE rcu.criterion_id = :indicatorId
        AND   a.deleted_at IS NULL
    ';
    private const COUNT_INDICATOR_USAGES_QUERY_V2 = '
        SELECT COUNT(*)
        FROM       rubric_indicator_usage rcu
        INNER JOIN submission_box         sb ON rcu.submission_box_id = sb.id
        INNER JOIN assessment             a  ON sb.assessment_id = a.id
        WHERE rcu.rubric_indicator_id = :indicatorId
        AND   a.deleted_at IS NULL
    ';


    /**************************** Prepared capability queries ***************************/

    /**
     * Query for deleting rubric capabilities.
     *
     * @var IQuery
     */
    private $deleteIndicatorQuery;


    /**************************** Prepared indicator queries ***************************/

    /**
     * Query for finding all rubric indicators in a capability.
     *
     * @var IQuery
     */
    private $findCapabilityIndicatorsQuery;


    /**************************** Prepared usage queries ***************************/

    /**
     * Query for finding all usages of a capability.
     *
     * @var IQuery
     */
    private $countIndicatorUsagesQuery;


    /**
     * Setup all queries this script requires.
     */
    protected function setupQueries(): void
    {
        $this->deleteIndicatorQuery
            = $this->db->prepareQuery(
                $this->useV2Queries
                    ? self::DELETE_INDICATOR_QUERY_V2
                    : self::DELETE_INDICATOR_QUERY_V1
            );

        $this->findCapabilityIndicatorsQuery
            = $this->db->prepareQuery(
                $this->useV2Queries
                    ? self::FIND_CAPABILITY_INDICATORS_QUERY_V2
                    : self::FIND_CAPABILITY_INDICATORS_QUERY_V1
            );
        $this->countIndicatorUsagesQuery
            = $this->db->prepareQuery(
                $this->useV2Queries
                    ? self::COUNT_INDICATOR_USAGES_QUERY_V2
                    : self::COUNT_INDICATOR_USAGES_QUERY_V1
            );
    }


    /**
     * @inheritDoc
     */
    protected function outputIntro(): void
    {
        $this->log->notice('Deleting empty duplicate indicators that are not in use');
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

                $this->createSavepoint();
                $this->deleteIndicators($groupedIndicators, $folderId);
                $this->commitSavepoint();
            }
        }
    }


    /**
     * Attempt to delete any indicators.
     *
     * @param array $groupedIndicators key: indicator name, value: list of indicator IDs
     * @param int   $folderId
     */
    private function deleteIndicators(array $groupedIndicators, int $folderId): void
    {
        foreach ($groupedIndicators as $indicatorName => $indicatorIds) {
            $truncatedName  = self::truncate($indicatorName, self::MAX_LOG_ITEM_WIDTH);
            $indicatorCount = count($indicatorIds);
            if ($indicatorCount <= 1) {
                $this->log->debug(
                    self::indent(1) . sprintf('indicator "%s" has no duplicates: skipping', $truncatedName)
                );
                continue;
            }

            // We have duplicates. Undupe them
            $this->log->notice(
                self::indent(1) . sprintf('indicator "%s" has %d duplicate(s)', $truncatedName, $indicatorCount - 1)
            );

            // Delete any duplicates which are not in use
            array_shift($indicatorIds);
            foreach ($indicatorIds as $indicatorId) {
                $result = [
                    'id'       => $indicatorId,
                    'name'     => $indicatorName,
                    'folderId' => $folderId,
                ];

                $this->countIndicatorUsagesQuery->execute(['indicatorId' => $indicatorId]);
                $usageCount = (int)$this->countIndicatorUsagesQuery->fetchColumn();
                if ($usageCount === 0) {
                    $this->log->notice(
                        self::indent(2) . sprintf('deleting unused duplicate indicator #%d', $indicatorId)
                    );
                    $this->deleteIndicatorQuery->execute([
                        'indicatorId' => $indicatorId,
                        'now'         => $this->now->getDatabase(),
                    ]);
                    $this->fixedIndicators[$indicatorId] = $result;
                } else {
                    $this->log->warning(
                        self::indent(2) . sprintf('refusing to delete indicator #%d, as it is in use', $indicatorId)
                    );
                    $this->failedIndicators[$indicatorId] = $result;
                }
            }
        }
    }
}
