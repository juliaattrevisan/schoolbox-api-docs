#!/usr/bin/php
<?php
/**
 * Repair script for merging duplicate rubric capabilities.
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
 * This script will raise an error (and not continue to migrate the capability)
 * if both the original and duplicate are in use on the same assessment.
 *
 * @copyright Copyright 2019, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);


use Alaress\Database\Connector\IConnector;
use Alaress\Database\Statement\Query\IQuery;
use Psr\Log\LoggerInterface as Logger;
use Schoolbox\Application\CliTool;
use Schoolbox\Learning\Course\DataModel\Implementation;

$opts = require __DIR__ . '/../../conf/core/require-cli.php';

// TODO - START - Delete this once SA-8248 is merged. It will be handled by a single core require.php
require_once __DIR__ . '/../../www/conf/conf.php';
require_once __DIR__ . '/../../www/include/stdfuncs.php';
require_once __DIR__ . '/../../library/constants.php';
set_include_path('.' . PATH_SEPARATOR . SITE_root . PATH_SEPARATOR . get_include_path());
require_once __DIR__ . '/../../library/Autoloader.php';
Autoloader::initialise();
// TODO - END

$app = new class extends CliTool\App
{
    // This tools CLI options
    private const OPT_SHORT_FOLDER = 'f:';
    private const OPT_LONG_MAILTO = 'mailto:';


    // An indentation constant.
    // TODO make this part of the CliTool class
    private const INDENT = '  ';


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


    /**************************** Indicator migration queries ***************************/

    private const FIND_CAPABILITY_INDICATORS_QUERY = '
        SELECT id, name
        FROM   rubric_criterion
        WHERE  rubric_group_id = :capabilityId
        AND    deleted_at IS NULL
        ORDER BY name, id
    ';

    private const MIGRATE_CAPABILITY_INDICATOR_QUERY = '
        UPDATE rubric_criterion
        SET rubric_group_id = :originalCapabilityId
        WHERE rubric_group_id = :duplicateCapabilityid
        AND id = :indicatorId
    ';


    /**************************** Usage migration queries ***************************/

    private const FIND_CAPABILITY_USAGES_QUERY = '
        SELECT rgu.submission_box_id
        FROM rubric_group_usage rgu
        INNER JOIN submission_box sb ON rgu.submission_box_id = sb.id
        INNER JOIN assessment     a  ON sb.assessment_id = a.id
        WHERE rgu.rubric_group_id = :capabilityId
        AND   a.deleted_at IS NULL
    ';

    private const MIGRATE_CAPABILITY_USAGE_QUERY = '
        UPDATE rubric_group_usage
        SET rubric_group_id = :originalCapabilityId
        WHERE rubric_group_id = :duplicateCapabilityid
        AND submission_box_id = :boxId
    ';


    /**************************** Mark migration queries ***************************/

    private const FIND_CAPABILITY_MARKS_QUERY = '
        SELECT rgu.submission_box_id
        FROM rubric_group_mark rm
        INNER JOIN rubric_group_usage rgu ON rm.rubric_group_id = rgu.rubric_group_id
        INNER JOIN submission_box     sb  ON rgu.submission_box_id = sb.id
        INNER JOIN assessment         a   ON sb.assessment_id = a.id
    ';

    private const MIGRATE_CAPABILITY_MARK_QUERY = '
        UPDATE rubric_group_mark rm
        INNER JOIN rubric_group_usage rgu ON rm.rubric_group_id = rgu.rubric_group_id
        SET rm.rubric_group_id = :originalCapabilityId
        WHERE rm.rubric_group_id = :duplicateCapabilityid
        AND rgu.submission_box_id = :boxId
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


    /**************************** Prepared indicator migration queries ***************************/

    /**
     * Query for finding all rubric indicators in a capability.
     *
     * @var IQuery
     */
    private $findCapabilityIndicatorsQuery;

    /**
     * Query for migrating an indicator from a duplicate to the original capability.
     *
     * @var IQuery
     */
    private $migrateCapabilityIndicatorQuery;


    /**************************** Prepared usage migration queries ***************************/

    /**
     * Query for finding all usages of a capability.
     *
     * @var IQuery
     */
    private $findCapabilityUsagesQuery;

    /**
     * Query for migrating a usage from a duplicate to the original capability.
     *
     * @var IQuery
     */
    private $migrateCapabilityUsageQuery;


    /**************************** Prepared mark migration queries ***************************/

    /**
     * Query for finding all marks given for a capability.
     *
     * @var IQuery
     */
    private $findCapabilityMarksQuery;

    /**
     * Query for migrating a usage from a duplicate to the original capability.
     *
     * @var IQuery
     */
    private $migrateCapabilityMarkQuery;


    /**************************** Repair state ***************************/

    /**
     * @var int $duplicateCapabilityCount
     */
    private $duplicateCapabilityCount = 0;

    /**
     * @var int $repairedCapabilityCount
     */
    private $repairedCapabilityCount = 0;

    /**
     * @var int $duplicateIndicatorCount
     */
    private $duplicateIndicatorCount = 0;

    /**
     * @var int $repairedIndicatorCount
     */
    private $repairedIndicatorCount = 0;

    /**
     * @var int $bothCapabilityDupesInUseCount
     */
    private $bothCapabilityDupesInUseCount = 0;

    /**
     * @var int $capabilityNotInUseButHasIndicatorsInUseCount
     */
    private $capabilityNotInUseButHasIndicatorsInUseCount = 0;

    /**
     * @var int $capabilityHasNoIndicators
     */
    private $capabilityHasNoIndicators = 0;

    /**
     * @var int $indicatorWithDescriptorConflictCount
     */
    private $indicatorWithDescriptorConflictCount = 0;

    /**
     * @var int $indicatorInUseWithNoDescriptorsCount
     */
    private $indicatorInUseWithNoDescriptorsCount = 0;


    /**
     * @var Logger
     */
    private $log;

    /**
     * @var IConnector
     */
    private $db;

    /**
     * @var Alaress_Date_Format
     */
    private $now;

    /**
     * The fully-qualified domain name for this instance.
     *
     * @var
     */
    private $fqdn;

    /**
     * The folders to repair, if passed via the `self::OPT_SHORT_FOLDER`
     * option.
     *
     * If empty, repair all folders.
     *
     * @var array
     */
    private $folders;

    /**
     * Significant events: read these, if nothing else.
     *
     * Indexed by assessment ID.
     *
     * @var array|string[][]
     */
    private $significantEvents = [];


    /**
     * @inheritdoc
     */
    public function getShortOpts(): array
    {
        return [self::OPT_SHORT_FOLDER];
    }


    /**
     * @inheritDoc
     */
    public function getLongOpts(): array
    {
        return [self::OPT_LONG_MAILTO];
    }


    /**
     * Set up and run the repair process.
     *
     * Install all instance vars, and wraps the repair process in a transaction
     * for commit (if `--perform` is on) or rollback (if it's off, or an error
     * is raised).
     *
     * @inheritdoc
     * @throws RuntimeException
     */
    public function run(): void
    {
        // Install logger and DB
        $this->log     = $this->getLogger();
        $this->db      = $this->getDi()->get('databaseInternal');
        $this->now     = Alaress_Date_Format::now();
        $this->fqdn    = trim($this->getConfig()->get('site_hostname') . '.' . $this->getConfig()->get('site_domain'), ';.');
        $folderIds     = $this->getOpt(self::OPT_SHORT_FOLDER);
        $this->folders = empty($folderIds)
            ? []
            : array_map('intval', explode(',', $folderIds));

        // log perform / dry run
        if ($this->perform()) {
            $this->log->info('Running in perform mode (-p): changes will be written to database');
        } else {
            $this->log->info('Running in dry-run mode: use -p to write changes to database');
        }

        $this->findFoldersQuery
            = $this->db->prepareQuery(self::FIND_FOLDERS_QUERY);

        $this->findCapabilitiesQuery
            = $this->db->prepareQuery(self::FIND_CAPABILITIES_QUERY);

        $this->findCapabilityIndicatorsQuery
            = $this->db->prepareQuery(self::FIND_CAPABILITY_INDICATORS_QUERY);
        $this->migrateCapabilityIndicatorQuery
            = $this->db->prepareQuery(self::MIGRATE_CAPABILITY_INDICATOR_QUERY);

        $this->findCapabilityUsagesQuery
            = $this->db->prepareQuery(self::FIND_CAPABILITY_USAGES_QUERY);
        $this->migrateCapabilityUsageQuery
            = $this->db->prepareQuery(self::MIGRATE_CAPABILITY_USAGE_QUERY);

        $this->findCapabilityMarksQuery
            = $this->db->prepareQuery(self::FIND_CAPABILITY_MARKS_QUERY);
        $this->migrateCapabilityMarkQuery
            = $this->db->prepareQuery(self::MIGRATE_CAPABILITY_MARK_QUERY);

        // Do everything in a transaction
        // (will be in individual methods, as we have no nesting)
//        $this->db->beginTransaction();
        $this->repair();

//        // Report on what we were able to automatically repair
//        if ($this->duplicateCapabilityCount > 0) {
//            $this->log->critical(
//                sprintf('%d capabilities had duplicates.',
//                    $this->duplicateCapabilityCount
//                )
//            );
//            $this->log->critical(
//                sprintf('%d capabilities were automatically deduplicated.',
//                    $this->repairedCapabilityCount
//                )
//            );
//        }
//
//        if ($this->repairedCapabilityCount < $this->duplicateCapabilityCount) {
//            $this->log->critical(
//                sprintf('%d capabilities were not able to be automatically deduplicated:',
//                    $this->duplicateCapabilityCount - $this->repairedCapabilityCount
//                )
//            );
//            $this->log->critical(
//                sprintf(self::indent(1) . '- %d capabilities could not be deduped because both copies were in use',
//                    $this->bothCapabilityDupesInUseCount
//                )
//            );
//
//            $cumulativeInvalidCapabilityCount = $this->capabilityNotInUseButHasIndicatorsInUseCount + $this->capabilityHasNoIndicators;
//
//            if ($cumulativeInvalidCapabilityCount > 0) {
//                $this->log->critical(
//                    sprintf(self::indent(1) . '- %d capabilities could not be deduped because they had invalid data:',
//                        $cumulativeInvalidCapabilityCount
//                    )
//                );
//                $this->log->critical(
//                    sprintf(self::indent(2) . '- %d capabilities were not in use, but had indicators in use',
//                        $this->capabilityNotInUseButHasIndicatorsInUseCount
//                    )
//                );
//                $this->log->critical(
//                    sprintf(self::indent(2) . '- %d capabilities had no indicators',
//                        $this->capabilityHasNoIndicators
//                    )
//                );
//            }
//        }
//
//        if ($this->duplicateIndicatorCount > 0) {
//            $this->log->critical(
//                sprintf('%d indicators had duplicates.',
//                    $this->duplicateIndicatorCount
//                )
//            );
//            $this->log->critical(
//                sprintf('%d indicators were automatically deduplicated.',
//                    $this->repairedIndicatorCount
//                )
//            );
//        }
//        if ($this->repairedIndicatorCount < $this->duplicateIndicatorCount) {
//            $this->log->critical(
//                sprintf('%d indicators were not able to be automatically deduplicated:',
//                    $this->duplicateIndicatorCount - $this->repairedIndicatorCount
//                )
//            );
//            $this->log->critical(
//                sprintf(self::indent(1) . '- %d indicators could not be deduped because both copies had descriptors',
//                    $this->indicatorWithDescriptorConflictCount
//                )
//            );
//
//            if ($this->indicatorInUseWithNoDescriptorsCount) {
//                $this->log->critical(
//                    sprintf(self::indent(1) . '- %d indicators could not be deduped because they had usages but no descriptors',
//                        $this->indicatorInUseWithNoDescriptorsCount
//                    )
//                );
//            }
//        }

        // Either commit or roll back changes, depending on whether we ran in perform mode or not
        if ($this->perform()) {
            try {
//                $this->db->commit();
                $this->log->alert('Changes written to database');
            } catch (\Throwable $e) {
                // In case something goes wrong when executing a query
                $this->log->alert('An error occurred while trying to write changes to the database: rolling back changes');
//                $this->db->rollBack();
                throw $e;
            }
        } else {
//            $this->db->rollBack();
            $this->log->alert('Dry-run complete - no changes written to database');
        }

        // Throw an exception if the migration was incomplete, so that it gets logged in our dashboard for manual intervention
        if ($this->repairedIndicatorCount < $this->duplicateIndicatorCount || $this->repairedCapabilityCount < $this->duplicateCapabilityCount) {
            throw new RuntimeException('Migration incomplete, could not migrate non-destructively.');
            // TODO instead of throwing exception here, email Support with a list of assessments that didn't work properly
        }
    }


    /**
     * Run the repair process.
     *
     * @throws \Alaress\Database\Exception
     */
    private function repair(): void
    {
        // Actions:
        // repairDuplicate


        // Find each class
        // Retrieve its capabilities+indicators
        /** @var Implementation\Model[] $classes */
        $this->findFoldersQuery->execute();
        if ($this->findFoldersQuery->rowCount() === 0) {
            $this->log->critical('No classes found - nothing to migrate');
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

            $groupedCapabilities = [];
            foreach ($this->findCapabilitiesQuery->fetchColumnArray('name', 'id') as $id => $name) {
                $groupedCapabilities[$name][] = $id;
            }

            $this->repairCapabilities($groupedCapabilities, $classFolderId);
        }
    }


    /**
     * Attempt to repair this set of capabilities by removing any duplicates.
     *
     * @param array $groupedCapabilities key: capability name, value: list of capability IDs
     * @param int   $folderId            Used to find duplicates of this capability in the same folder
     * @throws \Alaress\Database\Exception
     */
    private function repairCapabilities(array $groupedCapabilities, int $folderId): void
    {
        foreach ($groupedCapabilities as $capabilityName => $capabilityIds) {
            $capabilityCount = count($capabilityIds);
            if ($capabilityCount === 1) {
                $this->log->debug(
                    self::indent(2) . sprintf('capability "%s" has no duplicates: skipping', $capabilityName)
                );
                continue;
            }

            // We have duplicates. Undupe them
            $this->log->notice(
                self::indent(2) . sprintf('capability "%s" has %d duplicate(s)', $capabilityName, $capabilityCount - 1)
            );
            $this->repairCapabilityIndicators($capabilityIds);
        }
    }


    private function repairCapabilityIndicators(array $capabilityIds): void
    {
        // Roll each capability after the last into the first.
        $originalCapabilityId = array_shift($capabilityIds);

        foreach ($capabilityIds as $duplicateCapabilityId) {
            // Which indicators are within the original capability?
            // (Need to load after each loop, because the loop puts indicators in the original)
            $this->log->notice(
                self::indent(3) . sprintf('merging duplicate #%d into original #%d', $duplicateCapabilityId, $originalCapabilityId)
            );

            $this->findCapabilityIndicatorsQuery->execute(['capabilityId' => $originalCapabilityId]);
            $originalIndicators = $this->findCapabilityIndicatorsQuery->fetchColumnArray('name', 'id');

            // And which are in the duplicate?
            $this->findCapabilityIndicatorsQuery->execute(['capabilityId' => $duplicateCapabilityId]);
            $duplicateIndicators = $this->findCapabilityIndicatorsQuery->fetchColumnArray('name', 'id');

            // Is there any overlap between the capabilities in the original and the duplicate?
            $this->db->beginTransaction();
            $conflict = false;
            foreach ($duplicateIndicators as $duplicateIndicatorId => $duplicateIndicatorName) {
                $this->log->notice(
                    self::indent(4) . sprintf('moving indicator %s into original', $duplicateIndicatorName)
                );

                if (in_array($duplicateIndicatorId, $originalIndicators, true)) {
                    // raise an exception
                    $this->log->warning(
                        self::indent(4) . 'indicator already exists in original: rolling back'
                    );
                    $conflict = true;
                    break;
                }

                $this->migrateCapabilityIndicatorQuery->execute([
                    'originalCapabilityId'  => $originalCapabilityId,
                    'duplicateCapabilityId' => $duplicateCapabilityId,
                    'id'                    => $duplicateIndicatorId,
                ]);
            }

            // Probably this won't work without nested transactions
            // TODO this should be around the capability itself, not a part
            if ($conflict) {
                $this->db->rollBack();
            } elseif (!$this->perform()) {
                $this->db->rollBack();
            } else {
                $this->db->commit();
            }
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
};

$options = [
    CliTool\Kernel::OPTION_ERRORS => E_ALL,
];

try {
    (new Schoolbox\Application\CliTool\Kernel($app, null, $options))->runApp();
} catch (Throwable $e) {
    /** @noinspection PhpUnhandledExceptionInspection */
    throw $e;
}
