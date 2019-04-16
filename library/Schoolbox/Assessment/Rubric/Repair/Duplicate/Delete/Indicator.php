<?php
/**
 * @copyright Copyright 2019, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Rubric\Repair\Duplicate\Delete;

use Alaress\Database\Exception;
use Alaress\Database\Statement\Query\IQuery;
use Schoolbox\Assessment\Rubric\Repair\Base;

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
 * This script will raise an error (and not continue to merge the indicator)
 * if both the original and duplicate are in use on the same assessment.
 */
class Indicator extends Base
{
    /**************************** Capability queries ***************************/

    private const DELETE_INDICATOR_QUERY = '
        UPDATE rubric_criterion
        SET    deleted_at = :now
        WHERE  id         = :indicatorId
    ';


    /**************************** Indicator queries ***************************/

    private const FIND_CAPABILITY_INDICATORS_QUERY = '
        SELECT id, name
        FROM   rubric_criterion
        WHERE  rubric_group_id = :capabilityId
        AND    deleted_at      IS NULL
        ORDER BY name, id
    ';


    /**************************** Usage queries ***************************/

    private const COUNT_INDICATOR_USAGES_QUERY = '
        SELECT COUNT(*)
        FROM       rubric_criterion_usage rcu
        INNER JOIN submission_box         sb ON rcu.submission_box_id = sb.id
        INNER JOIN assessment             a  ON sb.assessment_id = a.id
        WHERE rcu.criterion_id = :indicatorId
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
            = $this->db->prepareQuery(self::DELETE_INDICATOR_QUERY);

        $this->findCapabilityIndicatorsQuery
            = $this->db->prepareQuery(self::FIND_CAPABILITY_INDICATORS_QUERY);
        $this->countIndicatorUsagesQuery
            = $this->db->prepareQuery(self::COUNT_INDICATOR_USAGES_QUERY);
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

                $this->db->beginTransaction();
                $this->deleteIndicators($groupedIndicators, $folderId);
                if ($this->perform()) {
                    $this->db->commit();
                } else {
                    $this->db->rollBack();
                }
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
            $indicatorCount = count($indicatorIds);
            if ($indicatorCount <= 1) {
                $this->log->debug(
                    self::indent(1) . sprintf('indicator "%s" has no duplicates: skipping', $indicatorName)
                );
                continue;
            }

            // We have duplicates. Undupe them
            $this->log->notice(
                self::indent(1) . sprintf('indicator "%s" has %d duplicate(s)', $indicatorName, $indicatorCount - 1)
            );

            // Delete any duplicates which are not in use
            array_shift($indicatorIds);
            foreach ($indicatorIds as $indicatorId) {

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
                    $this->fixedIndicators[$indicatorId] = [
                        'id'       => $indicatorId,
                        'name'     => $indicatorName,
                        'folderId' => $folderId,
                    ];
                } else {
                    $this->log->warning(
                        self::indent(2) . sprintf('refusing to delete indicator #%d, as it is in use', $indicatorId)
                    );
                }
            }
        }
    }
}
