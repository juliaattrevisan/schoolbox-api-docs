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
 * This script will raise an error (and not continue to merge the capability)
 * if both the original and duplicate are in use on the same assessment.
 */
class Capability extends Base
{
    /**************************** Folder queries ***************************/

    private const FIND_FOLDERS_QUERY = '
       SELECT
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

    private const DELETE_CAPABILITY_QUERY = '
        UPDATE rubric_group
        SET    deleted_at = :now
        WHERE  id         = :capabilityId
    ';

    /**************************** Indicator queries ***************************/

    private const COUNT_CAPABILITY_INDICATORS_QUERY = '
        SELECT COUNT(*)
        FROM   rubric_criterion
        WHERE  rubric_group_id = :capabilityId
        AND    deleted_at IS NULL
        ORDER BY name, id
    ';


    /**************************** Usage queries ***************************/

    private const COUNT_CAPABILITY_USAGES_QUERY = '
        SELECT COUNT(*)
        FROM rubric_group_usage rgu
        INNER JOIN submission_box sb ON rgu.submission_box_id = sb.id
        INNER JOIN assessment     a  ON sb.assessment_id = a.id
        WHERE rgu.rubric_group_id = :capabilityId
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
        $this->findFoldersQuery
            = $this->db->prepareQuery(self::FIND_FOLDERS_QUERY);

        $this->findCapabilitiesQuery
            = $this->db->prepareQuery(self::FIND_CAPABILITIES_QUERY);
        $this->deleteCapabilityQuery
            = $this->db->prepareQuery(self::DELETE_CAPABILITY_QUERY);

        $this->countCapabilityIndicatorsQuery
            = $this->db->prepareQuery(self::COUNT_CAPABILITY_INDICATORS_QUERY);
        $this->countCapabilityUsagesQuery
            = $this->db->prepareQuery(self::COUNT_CAPABILITY_USAGES_QUERY);
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

            $this->deleteCapabilities($groupedCapabilities);
        }
    }


    /**
     * Attempt to delete any capabilities.
     *
     * @param array $groupedCapabilities key: capability name, value: list of capability IDs
     * @throws Exception
     */
    private function deleteCapabilities(array $groupedCapabilities): void
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

            // Delete any duplicates which are not in use
            array_shift($capabilityIds);
            foreach ($capabilityIds as $capabilityId) {

                $this->countCapabilityIndicatorsQuery->execute(['capabilityId' => $capabilityId]);
                $this->countCapabilityUsagesQuery->execute(['capabilityId' => $capabilityId]);
                $indicatorCount = (int)$this->countCapabilityIndicatorsQuery->fetchColumn();
                $usageCount     = (int)$this->countCapabilityUsagesQuery->fetchColumn();
                if ($indicatorCount === 0 && $usageCount === 0) {
                    $this->log->notice(
                        self::indent(2) . sprintf('deleting unused duplicate capability #%d', $capabilityId)
                    );
                    $this->deleteCapabilityQuery->execute([
                        'capabilityId' => $capabilityId,
                        'now'          => $this->now->getDatabase(),
                    ]);
                } else {
                    $this->log->warning(
                        self::indent(2) . sprintf('refusing to delete capability #%d, as it is in use', $capabilityId)
                    );
                }
            }
        }
    }
}
