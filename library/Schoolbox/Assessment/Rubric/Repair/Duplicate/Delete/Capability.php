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
 * Script for deleting empty duplicate rubric capabilities.
 *
 * In the past it may have been possible to create more than one capability
 * with the same name in a folder.
 * This is now validated against, but broken data still exists for some schools.
 *
 * This script:
 * * finds all capabilities within a folder
 * * for any duplicate capabilities:
 * ** if it's neither in use on any due works, nor containing any indicators:
 * *** deletes the capability
 *
 * This script will refuse to delete a duplicate capability which is still in
 * use on a due work, or which still contains indicators.
 */
class Capability extends Base
{
    /**************************** Capability queries ***************************/

    private const DELETE_CAPABILITY_QUERY_V1 = '
        UPDATE rubric_group
        SET    deleted_at = :now
        WHERE  id         = :capabilityId
    ';
    private const DELETE_CAPABILITY_QUERY_V2 = '
        UPDATE rubric_capability
        SET    deleted_at = :now
        WHERE  id         = :capabilityId
    ';


    /**************************** Indicator queries ***************************/

    private const COUNT_CAPABILITY_INDICATORS_QUERY_V1 = '
        SELECT COUNT(*)
        FROM   rubric_criterion
        WHERE  rubric_group_id = :capabilityId
        AND    deleted_at IS NULL
        ORDER BY name, id
    ';
    private const COUNT_CAPABILITY_INDICATORS_QUERY_V2 = '
        SELECT COUNT(*)
        FROM   rubric_indicator
        WHERE  rubric_capability_id = :capabilityId
        AND    deleted_at IS NULL
        ORDER BY name, id
    ';


    /**************************** Usage queries ***************************/

    private const COUNT_CAPABILITY_USAGES_QUERY_V1 = '
        SELECT COUNT(*)
        FROM rubric_group_usage rgu
        INNER JOIN submission_box sb ON rgu.submission_box_id = sb.id
        INNER JOIN assessment     a  ON sb.assessment_id = a.id
        WHERE rgu.rubric_group_id = :capabilityId
        AND   a.deleted_at IS NULL
    ';
    private const COUNT_CAPABILITY_USAGES_QUERY_V2 = '
        SELECT COUNT(*)
        FROM rubric_capability_usage rgu
        INNER JOIN submission_box sb ON rgu.submission_box_id = sb.id
        INNER JOIN assessment     a  ON sb.assessment_id = a.id
        WHERE rgu.rubric_capability_id = :capabilityId
        AND   a.deleted_at IS NULL
    ';


    /**************************** Prepared capability queries ***************************/

    /**
     * Query for deleting rubric capabilities.
     *
     * @var IQuery
     */
    private $deleteCapabilityQuery;


    /**************************** Prepared indicator queries ***************************/

    /**
     * Query for finding all rubric indicators in a capability.
     *
     * @var IQuery
     */
    private $countCapabilityIndicatorsQuery;


    /**************************** Prepared usage queries ***************************/

    /**
     * Query for finding all usages of a capability.
     *
     * @var IQuery
     */
    private $countCapabilityUsagesQuery;


    /**
     * Setup all queries this script requires.
     */
    protected function setupQueries(): void
    {
        $this->deleteCapabilityQuery
            = $this->db->prepareQuery(
                $this->useV2Queries
                    ? self::DELETE_CAPABILITY_QUERY_V2
                    : self::DELETE_CAPABILITY_QUERY_V1
            );

        $this->countCapabilityIndicatorsQuery
            = $this->db->prepareQuery(
                $this->useV2Queries
                    ? self::COUNT_CAPABILITY_INDICATORS_QUERY_V2
                    : self::COUNT_CAPABILITY_INDICATORS_QUERY_V1
            );
        $this->countCapabilityUsagesQuery
            = $this->db->prepareQuery(
                $this->useV2Queries
                    ? self::COUNT_CAPABILITY_USAGES_QUERY_V2
                    : self::COUNT_CAPABILITY_USAGES_QUERY_V1
            );
    }


    /**
     * @inheritDoc
     */
    protected function outputIntro(): void
    {
        $this->log->notice('Deleting empty duplicate capabilities that are not in use');
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

            $this->createSavepoint();
            $this->deleteCapabilities($groupedCapabilities, $folderId);
            $this->commitSavepoint();
        }
    }


    /**
     * Attempt to delete any capabilities.
     *
     * @param array $groupedCapabilities key: capability name, value: list of capability IDs
     * @param int   $folderId
     */
    private function deleteCapabilities(array $groupedCapabilities, int $folderId): void
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

            // Delete any duplicates which contain no indicators, and are not in use
            array_shift($capabilityIds);
            foreach ($capabilityIds as $capabilityId) {
                $result = [
                    'id'       => $capabilityId,
                    'name'     => $capabilityName,
                    'folderId' => $folderId,
                ];

                // Indicator check
                $this->countCapabilityIndicatorsQuery->execute(['capabilityId' => $capabilityId]);
                $indicatorCount = (int)$this->countCapabilityIndicatorsQuery->fetchColumn();
                if ($indicatorCount > 0) {
                    $this->log->warning(
                        self::indent(2)
                        . sprintf('refusing to delete capability #%d, as it contains indicators', $capabilityId)
                    );
                    $this->failedCapabilities[$capabilityId] = $result;
                    continue;
                }

                // Usage check
                $this->countCapabilityUsagesQuery->execute(['capabilityId' => $capabilityId]);
                $usageCount = (int)$this->countCapabilityUsagesQuery->fetchColumn();
                if ($usageCount > 0) {
                    $this->log->warning(
                        self::indent(2) . sprintf('refusing to delete capability #%d, as it is in use', $capabilityId)
                    );
                    $this->failedCapabilities[$capabilityId] = $result;
                    continue;
                }

                $this->log->notice(
                    self::indent(2) . sprintf('deleting unused duplicate capability #%d', $capabilityId)
                );
                $this->deleteCapabilityQuery->execute([
                    'capabilityId' => $capabilityId,
                    'now'          => $this->now->getDatabase(),
                ]);
                $this->fixedCapabilities[$capabilityId] = $result;
            }
        }
    }
}
