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


use Schoolbox\Learning\Course\DataModel\Implementation;
use Alaress\Database\Connector\IConnector;
use Alaress\Database\Statement\Query\IQuery;
use Psr\Log\LoggerInterface as Logger;
use Schoolbox\Application\CliTool;

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
        $this->db->beginTransaction();
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
                $this->db->commit();
                $this->log->alert('Changes written to database');
            } catch (\Throwable $e) {
                // In case something goes wrong when executing a query
                $this->log->alert('An error occurred while trying to write changes to the database: rolling back changes');
                $this->db->rollBack();
                throw $e;
            }
        }  else {
            $this->db->rollBack();
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
     * Cascades to each capability's indicators.
     *
     * @param array $groupedCapabilities key: capability name, value: list of capability IDs
     * @param int $folderId    Used to find duplicates of this capability in the same folder
     * @throws \Alaress\Database\Exception
     */
    private function repairCapabilities(array $groupedCapabilities, int $folderId): void
    {
        // In here: get indicators for each capability. Group by name


        $seenCapabilities = [];
        foreach ($groupedCapabilities as $capabilityName => $capabilityIds) {

//            $this->log->info(
//                sprintf(
//                    self::indent(1) . 'Checking capability #%d "%s"',
//                    $capabilityId,
//                    $capabilityName
//                )
//            );
//
//            // Skip if we've already looked at this capability (such as in the inner loop)
//            // TODO this isn't correctly excluding caps we've already seen
//            if (isset($seenCapabilities[$capabilityName]) && in_array($capabilityId, $seenCapabilities[$capabilityName], false)) {
//                $this->log->debug(self::indent(2) . 'Already checked capability ' . $capabilityId . ': skipping');
//                continue;
//            }
//            // Record having looked at this capability
//            $seenCapabilities[$capabilityName][] = $capabilityId;


            // Find duplicates of this capability
            $capabilityNeedsDedupe = count($capabilityIds) > 1;
//            $this->findCapabilityDupesQuery->execute(
//                [
//                    'capabilityName' => $capabilityName,
//                    'capabilityId' => $capabilityId,
//                    'folderId' => $folderId
//                ]
//            );
//            if ($this->findCapabilityDupesQuery->rowCount() > 0) {
//                $capabilityNeedsDedupe = true;
//            }

            if ($capabilityNeedsDedupe) {
                $this->duplicateCapabilityCount++;
                $this->log->debug(self::indent(1) . 'incrementing duplicateCapabilityCount line ' . __LINE__);

                // Get the IDs of the duplicate capability/s
                $duplicateCapabilityIds = $this->findCapabilityDupesQuery->fetchColumnArray('name', 'id');
                $seenCapabilities[$capabilityName][] = array_keys($duplicateCapabilityIds);

                $this->log->info(
                    sprintf(
                        self::indent(2) . 'capability #%d has duplicates - checking if it can be deduplicated',
                        $capabilityId
                    )
                );

                $capabilityIsInUse = false;
                $capabilityHasIndicatorsInUse = false;
                // Count usages of this capability
                $this->countCapabilityUsagesQuery->execute(['capabilityId' => $capabilityId]);
                if ($this->countCapabilityUsagesQuery->rowCount() > 0) {
                    $capabilityIsInUse = true;
                }

                // Is this capability in use?
                if ($capabilityIsInUse) {
                    // Loop through the duplicates and see if we can safely delete them instead

                    // An array of boolean results indexed by capability ID (either true or false)
                    // Represents whether we were able to successfully dedupe or not
                    $migratedStatuses = [];

                    foreach($duplicateCapabilityIds as $duplicateCapabilityId => $duplicateCapabilityName) {
                        // Skip if we've already looked at this capability (such as in the outer loop)
                        // TODO not working
                        if (in_array($duplicateCapabilityId, $seenCapabilities[$capabilityName], false)) {
                            $this->log->debug(self::indent(2) . 'Already checked capability ' . $duplicateCapabilityId . ': skipping');
                            continue;
                        }
                        // Record having looked at this capability
                        $seenCapabilities[$capabilityName][] = $duplicateCapabilityId;

                        $migratedStatuses[] = $this->attemptToSafelyDedupeCapability($capabilityId, $capabilityName, $duplicateCapabilityId);
                    }

                    $successfullyMigratedCaps = array_filter($migratedStatuses, function($element) {
                        return $element === true;
                    });

                    if (\count($successfullyMigratedCaps) === \count($migratedStatuses)) {
                        // Don't increment this unless everything was executed successfully
                        $this->log->debug(self::indent(2) . 'incrementing repairedCapabilityCount line ' . __LINE__);
                        $this->repairedCapabilityCount++;
                    }
                } else {
                    // Capability is not in use

                    // Does this capability have indicators in use?
                    $this->countIndicatorUsagesForCapabilityQuery->execute(['capabilityId' => $capabilityId]);
                    if ($this->countIndicatorUsagesForCapabilityQuery->rowCount() > 0) {
                        $capabilityHasIndicatorsInUse = true;
                    }

                    if ($capabilityHasIndicatorsInUse) {
                        $this->capabilityNotInUseButHasIndicatorsInUseCount++;
                        $this->log->error('INVALID DATA: capability is not in use, but has indicators in use - skipping');
                    } else {
                        // Capability does not have indicators in use
                        // Dedupe it
                        // No point deduping its indicators here, because we're just gonna delete the capability anyway
                        $this->log->warning(
                            sprintf(
                                self::indent(2) . 'capability #%d has duplicates and can safely be removed: deduplicating',
                                $capabilityId
                            )
                        );
                        $this->deduplicateCapability((int)$capabilityId);
                        // Don't increment this unless everything was executed successfully
                        $this->log->debug(self::indent(2) . 'incrementing repairedCapabilityCount line ' . __LINE__);
                        $this->repairedCapabilityCount++;
                    }
                }
            } else {
                // Capability has no duplicates (that we know of, at this point in time)
                $this->log->debug(
                    sprintf(
                        self::indent(2) . 'capability #%d has no duplicates: no action required',
                        $capabilityId
                    )
                );

                // Does this capability have indicators?
                $this->findCapabilityIndicatorsQuery->execute(['capabilityId' => $capabilityId]);
                if ($this->findCapabilityIndicatorsQuery->rowCount() === 0) {
                    // No indicators is an error state - a capability must have children
                    $this->capabilityHasNoIndicators++;

                    $this->log->error(
                        self::indent(2) .
                        sprintf('INVALID DATA - capability #%d contains no indicators, skipping', $capabilityId)
                    );
                } else {
                    // Capability has indicators
                    // Loop through and dedupe them as required
                    $indicatorsForCapability = $this->findCapabilityIndicatorsQuery->fetchColumnArray('id', 'name');
                    $this->repairIndicators($indicatorsForCapability, $capabilityId);
                }
            }

        }
    }

    /**
     * Loop through a capability's duplicates and test if we can safely remove them
     *
     * @param $capabilityId
     * @param $capabilityName
     * @param $duplicateCapabilityId
     * @return bool
     * @throws \Alaress\Database\Exception
     */
    private function attemptToSafelyDedupeCapability($capabilityId, $capabilityName, $duplicateCapabilityId) : bool
    {
        // TODO somehow this is returning true even when none of the logs in here are, uh, getting logged
        $duplicateCapabilityIsInUse = false;
        $duplicateCapabilityHasIndicatorsInUse = false;
        $this->countCapabilityUsagesQuery->execute(['capabilityId' => $duplicateCapabilityId]);
        if ($this->countCapabilityUsagesQuery->rowCount() > 0) {
            $duplicateCapabilityIsInUse = true;
        }
        $this->countIndicatorUsagesForCapabilityQuery->execute(['capabilityId' => $duplicateCapabilityId]);
        if ($this->countIndicatorUsagesForCapabilityQuery->rowCount() > 0) {
            $duplicateCapabilityHasIndicatorsInUse = true;
        }

        // Is capability's duplicate in use?
        if ($duplicateCapabilityIsInUse) {
            // Don't dedupe
            $this->bothCapabilityDupesInUseCount++;
            $this->log->notice(
                sprintf(
                    self::indent(2) . 'capability #%d has duplicates, but both copies are in use: skipping deduplication',
                    $capabilityId
                )
            );
        } else {
            // Capability's duplicate is not in use.

            // Does capability's duplicate have indicators in use?
            if ($duplicateCapabilityHasIndicatorsInUse) {
                // Capability's duplicate has indicators in use
                $this->capabilityNotInUseButHasIndicatorsInUseCount++;
                $this->log->error('INVALID DATA: duplicate is in not in use, but has indicators in use - skipping');
            } else {
                // Capability's duplicate does not have indicators in use
                // Dedupe it
                // No point deduping its indicators here, because we're just gonna delete the capability anyway
                $this->log->warning(
                    sprintf(
                        self::indent(2) . 'capability #%d has duplicates but cannot be safely be removed: deleting its duplicate #%d instead',
                        $capabilityId,
                        $duplicateCapabilityId
                    )
                );
                $this->deduplicateCapability((int)$duplicateCapabilityId);

                return true;
            }
        }
        return false;
    }


    /**
     * Attempt to repair this set of indicators by removing any duplicates.
     *
     * @param $indicators
     * @param $capabilityId
     * @throws \Alaress\Database\Exception
     */
    private function repairIndicators($indicators, $capabilityId) : void
    {
        $seenIndicators = [];
        foreach($indicators as $indicatorName => $indicatorId) {
            // This only needs to be debug log level, as the capability already provides enough context
            $this->log->debug(
                sprintf(
                    self::indent(2) . 'Checking indicator #%d "%s"',
                    $indicatorId,
                    $indicatorName
                )
            );

            // Skip if we've already looked at this indicator (such as in the inner loop)
            if (isset($seenIndicators[$indicatorName]) && in_array($indicatorId, $seenIndicators[$indicatorName], false)) {
                $this->log->debug(self::indent(3) . 'Already checked indicator ' . $indicatorId . ': skipping');
                continue;
            }
            // Record having looked at this indicator
            $seenIndicators[$indicatorName][] = $indicatorId;


            // Find duplicates of this indicator
            $indicatorNeedsDedupe = false;
            $this->findIndicatorDupesQuery->execute(
                [
                    'indicatorName' => $indicatorName,
                    'indicatorId' => $indicatorId,
                    'parentCapabilityId' => $capabilityId
                ]
            );
            if ($this->findIndicatorDupesQuery->rowCount() > 0) {
                $indicatorNeedsDedupe = true;
            }

            // Does this indicator have duplicates?
            if ($indicatorNeedsDedupe) {
                $this->duplicateIndicatorCount++;
                $this->log->debug(self::indent(3) . 'incrementing duplicateIndicatorCount line ' . __LINE__);

                $this->log->info(
                    sprintf(
                        self::indent(3) . 'indicator #%d has duplicates - checking if it can be deduplicated',
                        $indicatorId
                    )
                );

                $indicatorIsInUse = false;
                $indicatorHasDescriptors = false;

                $this->countDescriptorsForIndicatorQuery->execute(['indicatorId' => $indicatorId]);
                if ($this->countDescriptorsForIndicatorQuery->rowCount() > 0) {
                    $indicatorHasDescriptors = true;
                }
                $this->countIndicatorUsagesQuery->execute(['indicatorId' => $indicatorId]);
                if ($this->countIndicatorUsagesQuery->rowCount() > 0) {
                    $indicatorIsInUse = true;
                }

                // Is this indicator in use?
                if ($indicatorIsInUse) {

                    // Does this indicator have descriptors?
                    if ($indicatorHasDescriptors) {
                        // This indicator has descriptors

                        // Let's loop through the duplicate indicator/s and see if we can safely delete them instead
                        // Get the IDs of the duplicate indicator/s, filtering out the indicator we're currently looking at
                        $duplicateIndicatorIds = $this->findIndicatorDupesQuery->fetchColumnArray('name', 'id');

                        // An array of boolean results indexed by capability ID (either true or false)
                        // Represents whether we were able to successfully dedupe or not
                        $migratedStatuses = [];

                        foreach ($duplicateIndicatorIds as $duplicateIndicatorId => $duplicateIndicatorName) {
                            // Skip if we've already looked at this indicator (such as in the outer loop)
                            if (in_array($duplicateIndicatorId, $seenIndicators[$indicatorName], false)) {
                                $this->log->debug(self::indent(3) . 'Already checked indicator ' . $duplicateIndicatorId . ': skipping');
                                continue;
                            }
                            // Record having looked at this capability
                            $seenIndicators[$indicatorName][] = $duplicateIndicatorId;

                            $migratedStatuses[] = $this->attemptToSafelyDedupeIndicator(
                                $indicatorId,
                                $indicatorName,
                                $duplicateIndicatorId,
                                $capabilityId
                            );
                        }

                        $successfullyMigratedInds = array_filter($migratedStatuses, function($element) {
                            return $element === true;
                        });

                        if (\count($successfullyMigratedInds) === \count($migratedStatuses)) {
                            // Don't increment this unless everything was executed successfully
                            $this->log->debug(self::indent(3) . 'incrementing repairedIndicatorCount line ' . __LINE__);
                            $this->repairedIndicatorCount++;
                        }
                    } else {
                        // Indicator does not have descriptors - this is an error state since an indicator in use must have descriptors
                        $this->indicatorInUseWithNoDescriptorsCount++;
                        $this->log->debug(self::indent(3) . 'incrementing indicatorInUseWithNoDescriptorsCount line ' . __LINE__);

                        $this->log->error(
                            sprintf(
                                self::indent(3) . 'INVALID DATA: indicator #%d is in use, but has no descriptors - skipping',
                                $indicatorId
                            )
                        );
                    }
                } else {
                    // This indicator is not in use, and can safely be deleted
                    // Dedupe it
                    $this->log->warning(
                        sprintf(
                            self::indent(3) . 'indicator #%d has duplicates and can safely be removed: deduplicating',
                            $indicatorId
                        )
                    );
                    $this->deduplicateIndicator((int)$indicatorId, (int)$capabilityId);

                    // Don't increment this unless everything was executed successfully
                    $this->log->debug(self::indent(3) . 'incrementing repairedIndicatorCount line ' . __LINE__);
                    $this->repairedIndicatorCount++;
                }
            } else {
                // Indicator does not have duplicates. Do nothing
                $this->log->debug(
                    sprintf(
                        self::indent(3) . 'indicator #%d has no duplicates: no action required',
                        $indicatorId
                    )
                );
            }

            $seenIndicators[$indicatorName][] = $indicatorId;
        }
    }


    /**
     * @param $indicatorId
     * @param $indicatorName
     * @param $duplicateIndicatorId
     * @param $capabilityId
     * @throws \Alaress\Database\Exception
     */
    private function attemptToSafelyDedupeIndicator($indicatorId, $indicatorName, $duplicateIndicatorId, $capabilityId) : bool
    {
        $duplicateIndicatorIsInUse = false;
        $duplicateIndicatorHasDescriptors = false;
        $this->countIndicatorUsagesQuery->execute(['indicatorId' => $duplicateIndicatorId]);
        if ($this->countIndicatorUsagesQuery->rowCount() > 0) {
            $duplicateIndicatorIsInUse = true;
        }
        $this->countDescriptorsForIndicatorQuery->execute(['indicatorId' => $duplicateIndicatorId]);
        if ($this->countDescriptorsForIndicatorQuery->rowCount() > 0) {
            $duplicateIndicatorHasDescriptors = true;
        }

        // Is the indicator's duplicate in use?
        if ($duplicateIndicatorIsInUse) {
            // Indicator's duplicate is in use

            // Does indicator's duplicate have descriptors?
            if ($duplicateIndicatorHasDescriptors) {
                // Indicator's duplicate has descriptors
                // This causes a conflict which we cannot resolve ourselves
                // Don't dedupe
                $this->indicatorWithDescriptorConflictCount++;
                $this->log->debug(self::indent(3) . 'incrementing indicatorWithDescriptorConflictCount line ' . __LINE__);

                $this->log->notice(
                    sprintf(self::indent(3) . 'indicator #%d has duplicates, but both copies have descriptors: skipping deduplication', $indicatorId, $indicatorName)
                );
            } else {
                // Indicator's duplicate has no descriptors
                // This is an error state since an indicator in use must have descriptors
                $this->indicatorInUseWithNoDescriptorsCount++;
                $this->log->debug(self::indent(3) . 'incrementing indicatorInUseWithNoDescriptorsCount line ' . __LINE__);

                $this->log->error(self::indent(3) . 'INVALID DATA: duplicate indicator #%d is in use, but has no descriptors - skipping');
            }
        } else {
            // Indicator's duplicate is not in use
            // Dedupe it
            $this->log->warning(
                sprintf(
                    self::indent(3) . 'indicator #%d has duplicates but cannot safely be removed: deleting its duplicate #%d instead',
                    $indicatorId,
                    $duplicateIndicatorId
                )
            );
            $this->deduplicateIndicator($duplicateIndicatorId, $capabilityId);
            return true;
        }
        return false;
    }



    /**
     * Remove a capability that has been determined to be a duplicate
     *
     * @param int    $duplicateCapabilityId
     * @throws \Alaress\Database\Exception    if the query did not execute successfully
     */
    private function deduplicateCapability(int $duplicateCapabilityId): void
    {
        $this->deleteCapabilityQuery
            ->execute([
                'now' => $this->now,
                'capabilityId' => $duplicateCapabilityId
            ]);

        $this->log->notice(
            self::indent(2) . sprintf('Deleting orphaned indicators from capability #%d', $duplicateCapabilityId)
        );

        // Delete the orphaned indicators
        $this->deleteIndicatorsForCapabilityQuery
            ->execute([
                'now' => $this->now,
                'capabilityId' => $duplicateCapabilityId
            ]);
    }


    /**
     * Remove an indicator that has been determined to be a duplicate
     *
     * @param int    $duplicateIndicatorId
     * @param int    $capabilityId
     * @throws \Alaress\Database\Exception    if the query did not execute successfully
     */
    private function deduplicateIndicator(int $duplicateIndicatorId, int $capabilityId): void
    {
        $this->deleteIndicatorQuery
            ->execute([
                'now' => $this->now,
                'indicatorId' => $duplicateIndicatorId
            ]);

        // Delete parent capability if this was the last indicator
        // This should never actually happen because if we detect a duplicate indicator,
        // it must mean there is more than 1 indicator in the cap, but better safe than sorry...
        $this->findCapabilityIndicatorsQuery
            ->execute([
                'capabilityId' => $capabilityId
            ]);
        if ($this->findCapabilityIndicatorsQuery->rowCount() === 0) {
            $this->log->notice(
                sprintf(self::indent(3) . 'No indicators left in capability #%d: deleting capability', $capabilityId)
            );
            $this->deleteCapabilityQuery->execute([
               'now' => $this->now,
               'capabilityId' => $capabilityId
            ]);
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


    /**
     * Log a significant event.
     *
     * @param string $message
     */
    private function logSignificantEvent(string $message): void
    {
        $this->significantEvents[] = $message;
    }


    /**
     * Output significant events.
     */
    private function outputSignificantEvents(): void
    {
        foreach ($this->significantEvents as $event) {
            $this->log->warning($event);
        }
    }
};

$options = [
    CliTool\Kernel::OPTION_ERRORS => E_ALL
];

try {
    (new Schoolbox\Application\CliTool\Kernel($app, null, $options))->runApp();
} catch (Throwable $e) {
    /** @noinspection PhpUnhandledExceptionInspection */
    throw $e;
}
