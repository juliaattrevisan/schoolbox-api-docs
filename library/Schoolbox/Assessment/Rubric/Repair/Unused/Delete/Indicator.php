<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Rubric\Repair\Unused\Delete;

use Alaress\Database\Connector\IConnector;
use Alaress\Database\Statement\IStatement;
use Alaress_Date_Format;
use Schoolbox\Application\CliTool;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Throwable;

class Indicator extends CliTool\App
{
    private const OPT_LONG_FOLDER_IDS = 'folders:';

    private const INDENT = '  ';

    private const FIND_RUBRIC_CAPABILITY_QUERY = "
        SELECT id, name
        FROM   rubric_capability
        WHERE  parent_type = 'category'
        AND    parent_id   = :folderId
        AND    deleted_at IS NULL
    ";


    private const FIND_RUBRIC_INDICATOR_QUERY = "
        SELECT id, name
        FROM   rubric_indicator
        WHERE  rubric_capability_id = :capabilityId
        AND    deleted_at IS NULL
    ";

    private const FIND_USED_RUBRIC_INDICATOR_QUERY = "
        SELECT ri.id, ri.name
        FROM   rubric_indicator ri
        LEFT JOIN rubric_indicator_usage riu on ri.id=riu.rubric_indicator_id
        WHERE  rubric_capability_id = :capabilityId
        AND    riu.rubric_indicator_id IS NOT NULL
        AND    deleted_at IS NULL
    ";


    private const FIND_UNUSED_INDICATORS_QUERY = "
        SELECT ri.id, ri.name
        FROM rubric_indicator ri
        LEFT JOIN rubric_indicator_usage riu ON ri.id=riu.rubric_indicator_id
        LEFT JOIN rubric_capability rc ON ri.rubric_capability_id = rc.id
        WHERE riu.rubric_indicator_id IS NULL
        AND rc.parent_id = :folderId
        AND ri.deleted_at IS null;
    ";

    private const DELETE_RUBRIC_INDICATOR_QUERY = '
        UPDATE rubric_indicator
        SET    deleted_at = :now
        WHERE  id         = :indicatorId
    ';

    private const DELETE_RUBRIC_CAPABILITY_QUERY = '
        UPDATE rubric_capability
        SET    deleted_at = :now
        WHERE  id         = :capabilityId
    ';


    /**
     * Query for finding rubric capabilities in a folder.
     */
    private IStatement $findRubricCapabilityQuery;

    /**
     * Query for finding rubric indicators in a capability.
     */
    private IStatement $findRubricIndicatorQuery;

    /**
     * Query for finding rubric indicators in a capability.
     */
    private IStatement $findUsedRubricIndicatorQuery;

    /**
     * Query for finding all unused indicators in a given folder
     */
    private IStatement $findUnusedIndicatorsQuery;

    /**
     * Query for deleting a rubric indicator from a unit folder.
     */
    private IStatement $deleteRubricIndicatorQuery;

    /**
     * Query for deleting a rubric capability from a folder.
     */
    private IStatement $deleteRubricCapabilityQuery;

    /**
     * The time at the start of the script run: stored so that any indicators
     * deleted are marked as deleted at a consistent time.
     */
    private Alaress_Date_Format $now;

    /**
     * Array of one or more folder IDs to operate on.
     *
     * @var int[]
     */
    private array $folderIds;

    /**
     * @inheritdoc
     */
    public function getLongOpts(): array
    {
        return array_merge(
            parent::getLongOpts(),
            [self::OPT_LONG_FOLDER_IDS,]
        );
    }


    /**
     * Set up and run the repair process.
     *
     * Install all instance vars, and wraps the repair process in a transaction
     * for commit (if `--perform` is on) or rollback (if it's off, or an error
     * is raised).
     *
     * @inheritdoc
     */
    public function run(): void
    {
        $db        = $this->getDatabaseInternal();
        $this->now = Alaress_Date_Format::now();

        // Set up folders
        $folderIds = $this->getOpt(self::OPT_LONG_FOLDER_IDS);
        if (!isset($folderIds)) {
            $this->getLogger()->error('Exiting - at least 1 folder ID must be supplied. Run again with --folders <id1,id2>');
            return;
        }
        $this->folderIds = explode(",", $folderIds);

        // log perform / dry run
        if ($this->perform()) {
            $this->getLogger()->warning('Running in perform mode (-p): changes will be written to database');
        } else {
            $this->getLogger()->warning('Running in dry-run mode: use -p to write changes to database');
        }

        // prepare queries
        $this->findRubricCapabilityQuery    = $db->prepareQuery(self::FIND_RUBRIC_CAPABILITY_QUERY);
        $this->findRubricIndicatorQuery     = $db->prepareQuery(self::FIND_RUBRIC_INDICATOR_QUERY);
        $this->findUsedRubricIndicatorQuery = $db->prepareQuery(self::FIND_USED_RUBRIC_INDICATOR_QUERY);
        $this->findUnusedIndicatorsQuery    = $db->prepareQuery(self::FIND_UNUSED_INDICATORS_QUERY);
        $this->deleteRubricIndicatorQuery   = $db->prepareQuery(self::DELETE_RUBRIC_INDICATOR_QUERY);
        $this->deleteRubricCapabilityQuery  = $db->prepareQuery(self::DELETE_RUBRIC_CAPABILITY_QUERY);

        // Wrap entire loop in a transaction
        $db->beginTransaction();
        try {
            $this->repair();
            if ($this->perform()) {
                $this->getLogger()->notice('Repair complete: committing changes');
                $db->commit();
            } else {
                $this->getLogger()->notice('Repair dry run complete: rolling back changes');
                $db->rollBack();
            }
        } catch (Throwable $e) {
            $this->getLogger()->error('Error occurred during repair: rolling back changes');
            $db->rollBack();
            throw $e;
        }
    }


    /**
     * Run the repair process.
     */
    private function repair(): void
    {
        foreach ($this->folderIds as $folderId) {
            $folder = FolderMapper::getInstance()->findById($folderId);
            $this->getLogger()->info(
                sprintf(
                    'Deleting unused indicators in folder #%d (%s)',
                    $folder->getId(),
                    $folder->getTitle()
                )
            );

            // Don't go further if there's no capabilities in folder
            $this->findRubricCapabilityQuery->execute(['folderId' => $folder->getId()]);
            if ($this->findRubricCapabilityQuery->rowCount() === 0) {
                $this->getLogger()->debug(self::indent(1) . 'folder contains no capabilities: no action required');
            } else {
                // This folder contains capabilites: great! Let's delete any unused indicators.
                $this->getLogger()->debug(self::indent(1) . 'finding unused indicators...');
                $this->findUnusedIndicatorsQuery->execute(['folderId' => $folder->getFolder()->getId()]);
                $folderIndicators = $this->findUnusedIndicatorsQuery->fetchColumnArray('name', 'id');

                if (count($folderIndicators) === 0) {
                    $this->getLogger()->debug(self::indent(1) . 'no unused indicators to delete, skipping');
                } else {
                    $this->deleteUnusedIndicators($folderIndicators);

                    // Find all the capabilities in this folder
                    $this->findRubricCapabilityQuery->execute(['folderId' => $folder->getFolder()->getId()]);
                    $folderCapabilities = $this->findRubricCapabilityQuery->fetchColumnArray('name', 'id');

                    // Clean up
                    $this->getLogger()->debug(self::indent(1) . 'cleaning up any empty capabilities left behind...');
                    $this->deleteEmptyCapabilities($folderCapabilities);
                    $this->getLogger()->debug(self::indent(1) . 'cleaning up any capabilities with no indicators in use...');
                    $this->deleteCapabilitiesWithNoUsedIndicators($folderCapabilities);
                }
            }
        }
    }


    /**
     * Find unused indicators and delete them
     *
     * @param array $indicators
     */
    private function deleteUnusedIndicators(array $indicators): void
    {
        foreach ($indicators as $indicatorId => $indicatorName) {
            $this->getLogger()->info(
                sprintf(
                    self::indent(2) . 'indicator "%s" (%d) is unused: deleting',
                    trim($indicatorName),
                    $indicatorId
                )
            );

            $this->deleteRubricIndicatorQuery
                ->execute(
                    [
                        'now'         => $this->now,
                        'indicatorId' => $indicatorId,
                    ]
                );
        }
    }


    /**
     * Finds capabilities without indicators and delete them
     *
     * @param array $capabilities
     */
    private function deleteEmptyCapabilities(array $capabilities): void
    {
        foreach ($capabilities as $capabilityId => $capabilityName) {
            $this->findRubricIndicatorQuery->execute(['capabilityId' => $capabilityId]);
            $indicators = $this->findRubricIndicatorQuery->fetchColumnArray('name', 'id');
            if (!empty($indicators)) {
                continue;
            }
            $this->getLogger()->info(
                sprintf(
                    self::indent(2) . 'capability "%s" (%d) does not have any indicators: deleting',
                    trim($capabilityName),
                    $capabilityId
                )
            );
            // no indicators for this capability
            $this->deleteRubricCapabilityQuery
                ->execute(
                    [
                        'now'          => $this->now,
                        'capabilityId' => $capabilityId,
                    ]
                );
        }
    }

    /**
     * If any capabilities in `$capabilities` are unused, remove them.
     *
     * @param array $capabilities
     */
    private function deleteCapabilitiesWithNoUsedIndicators(array $capabilities): void
    {
        foreach ($capabilities as $capabilityId => $capabilityName) {
            $this->findUsedRubricIndicatorQuery->execute(['capabilityId' => $capabilityId]);
            $usedIndicators = $this->findUsedRubricIndicatorQuery->fetchColumnArray('name', 'id');
            if (!empty($usedIndicators)) {
                continue;
            }
            $this->getLogger()->info(
                sprintf(
                    self::indent(2) . 'capability "%s" (%d) does not have any indicators in use: deleting',
                    trim($capabilityName),
                    $capabilityId
                )
            );
            // no indicators in use for this capability
            $this->deleteRubricCapabilityQuery
                ->execute(
                    [
                        'now'          => $this->now,
                        'capabilityId' => $capabilityId,
                    ]
                );
        }
    }


    /**
     * Helper method for indenting log output for legibility.
     *
     * @param int $depth The depth to indent by
     * @return string
     */
    private static function indent(int $depth): string
    {
        return str_repeat(self::INDENT, $depth);
    }
}
