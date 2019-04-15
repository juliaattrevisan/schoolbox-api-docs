<?php
/**
 * @copyright Copyright 2019, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Rubric\Repair\Duplicate\Delete;

use Alaress\Database\Exception;
use Alaress\Database\Statement\Query\IQuery;
use RuntimeException;
use Schoolbox\Assessment\Rubric\Repair\Base;
use Scrub;

/**
 * Script for deleting empty duplicate rubric indicators.
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
 * This script will raise an error (and not continue to merge the capability)
 * if both the original and duplicate are in use on the same assessment.
 */
class Indicator extends Base
{
    /**************************** Folder queries ***************************/

    private const FIND_FOLDERS_QUERY = '
       SELECT DISTINCT
           id, name
       FROM folder
       WHERE deleted_at IS NULL
    ';


    /**************************** Capability queries ***************************/

    private const FIND_CAPABILITIES_QUERY = "
        SELECT id, name
        FROM   rubric_group
        WHERE  parent_type = 'category'
        AND    parent_id   = :folderId
        AND    deleted_at IS NULL
        ORDER BY name, id
    ";

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


    /**************************** Prepared folder queries ***************************/

    /**
     * Query for finding folders which have had a unit imported into them.
     *
     * @var IQuery
     */
    private $findFoldersQuery;


    /**************************** Prepared capability queries ***************************/

    /**
     * Query for finding rubric capabilities in a folder.
     *
     * @var IQuery
     */
    private $findCapabilitiesQuery;

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
     * Due works which failed to merge for whatever reason.
     *
     * These must be fixed manually for each instance.
     *
     * @var array|int[]
     */
    private $failedDueWorks = [];


    /**
     * Setup all queries this script requires.
     */
    protected function setupQueries(): void
    {
        $this->findFoldersQuery
            = $this->db->prepareQuery(self::FIND_FOLDERS_QUERY);

        $this->findCapabilitiesQuery
            = $this->db->prepareQuery(self::FIND_CAPABILITIES_QUERY);
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
            $this->log->critical('No classes found - nothing to merge');
        }
        foreach ($this->findFoldersQuery->fetchAll() as $class) {
            $classFolderId = (int)$class['id'];

            // Is there a folder restriction?
            // If so and this folder's outside of it: just skip
            if (!empty($this->folders) && !in_array($classFolderId, $this->folders, true)) {
                continue;
            }

            $this->log->info(
                sprintf(
                    'Checking class folder #%d (%s)',
                    $classFolderId,
                    $class['name']
                )
            );

            // Don't go further if there's no capabilities in the class
            $this->findCapabilitiesQuery->execute(['folderId' => $classFolderId]);
            if ($this->findCapabilitiesQuery->rowCount() === 0) {
                $this->log->debug(self::indent(1) . 'class contains no capabilities: no action required');
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

                $this->deleteIndicators($groupedIndicators);
            }
        }
    }


    /**
     * Attempt to delete any indicators.
     *
     * @param array $groupedIndicators key: indicator name, value: list of indicator IDs
     * @throws Exception
     */
    private function deleteIndicators(array $groupedIndicators): void
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
                        'now'          => $this->now->getDatabase(),
                    ]);
                } else {
                    $this->log->warning(
                        self::indent(2) . sprintf('refusing to delete indicator #%d, as it is in use', $indicatorId)
                    );
                }
            }
        }
    }
}
