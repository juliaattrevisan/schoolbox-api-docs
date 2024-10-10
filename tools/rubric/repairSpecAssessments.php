#!/usr/bin/php
<?php

/**
 * Repair script for repairing conflicting rubric capabilities between a unit
 * specification and its course.
 *
 * Rubrics on assessments within a unit specification may use capabilities from
 * either the unit's folder, or the parent course folder. The issue is that it
 * is possible to create capabilities with the same name in both the unit and
 * course folder, which can lead to two different capabilities with the same
 * name in the same folder when the unit is imported onto a class page.
 *
 * The solution is to migrate conflicting capabilities from the unit up into
 * the course spec, and move their usage up into the course spec.
 *
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

use Alaress\Database\Connector\IConnector;
use Alaress\Database\Statement\Query\IQuery;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface as Logger;
use React\EventLoop\LoopInterface;
use Schoolbox\Application\Daemon\App;
use Schoolbox\Assessment\Rubric\Util as RubricUtil;
use Schoolbox\Learning\Course\DataModel\Specification;
use Schoolbox\Url\Uri;
use Schoolbox\Application\Daemon;

require_once __DIR__ . '/../../conf/core/require.php';

$kernel = new Daemon\Kernel(
    'Rubric Repair',
    [
        Daemon\Kernel::OPTION_LEVEL          => Monolog\Logger::CRITICAL,
        Daemon\Kernel::OPTION_APP_SHORT_OPTS => 'p',
        Daemon\Kernel::OPTION_APP_LONG_OPTS  => ['perform'],
    ]
);

/** @noinspection PhpUnhandledExceptionInspection */
$kernel->run(new class extends App {
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

    private const MIGRATE_RUBRIC_INDICATOR_QUERY = '
        UPDATE rubric_indicator
        SET    rubric_capability_id = :courseCapabilityId
        WHERE  id              = :unitIndicatorId
    ';

    private const DELETE_RUBRIC_CAPABILITY_QUERY = '
        UPDATE rubric_capability
        SET    deleted_at = :now
        WHERE  id         = :unitCapabilityId
    ';

    private const DELETE_RUBRIC_INDICATOR_QUERY = '
        UPDATE rubric_indicator
        SET    deleted_at = :now
        WHERE  id         = :unitIndicatorId
    ';

    private const FIND_RUBRIC_CAPABILITY_USAGE_QUERY = '
        SELECT     a.id
        FROM       rubric_capability_usage rgu
        INNER JOIN submission_box      sb ON rgu.submission_box_id = sb.id
        INNER JOIN assessment           a ON sb.assessment_id = a.id
        WHERE rgu.rubric_capability_id = :unitCapabilityId
    ';

    private const MIGRATE_RUBRIC_CAPABILITY_USAGE_QUERY = '
        UPDATE     rubric_capability_usage rgu
        INNER JOIN submission_box      sb ON rgu.submission_box_id = sb.id
        INNER JOIN assessment           a ON sb.assessment_id = a.id
        SET    rgu.rubric_capability_id = :courseCapabilityId
        WHERE  rgu.rubric_capability_id = :unitCapabilityId
        AND    a.id                     = :assessmentId
    ';

    private const DELETE_RUBRIC_CAPABILITY_USAGE_QUERY = '
        DELETE rgu
        FROM       rubric_capability_usage rgu
        INNER JOIN submission_box      sb ON rgu.submission_box_id = sb.id
        INNER JOIN assessment           a ON sb.assessment_id = a.id
        WHERE rgu.rubric_capability_id = :unitCapabilityId
        AND   a.id                     = :assessmentId
    ';

    private const FIND_RUBRIC_INDICATOR_USAGE_QUERY = '
        SELECT     a.id
        FROM       rubric_indicator_usage rcu
        INNER JOIN submission_box          sb ON rcu.submission_box_id = sb.id
        INNER JOIN assessment               a ON sb.assessment_id = a.id
        WHERE rcu.rubric_indicator_id = :unitIndicatorId
    ';

    private const MIGRATE_RUBRIC_INDICATOR_USAGE_QUERY = '
        UPDATE rubric_indicator_usage rcu
        INNER JOIN submission_box      sb ON rcu.submission_box_id = sb.id
        INNER JOIN assessment           a ON sb.assessment_id = a.id
        SET    rcu.rubric_indicator_id = :courseIndicatorId
        WHERE  rcu.rubric_indicator_id = :unitIndicatorId
        AND    a.id             = :assessmentId
    ';

    private const DELETE_RUBRIC_INDICATOR_USAGE_QUERY = '
        DELETE rcu
        FROM       rubric_indicator_usage rcu
        INNER JOIN submission_box          sb ON rcu.submission_box_id = sb.id
        INNER JOIN assessment               a ON sb.assessment_id = a.id
        WHERE rcu.rubric_indicator_id = :unitIndicatorId
        AND   a.id             = :assessmentId
    ';

    private const FIND_RUBRIC_DESCRIPTOR_QUERY = '
        SELECT rl.id AS levelId,
               rd.id,
               a.id AS assessmentId
        FROM       rubric_descriptor rd
        INNER JOIN rubric_level      rl ON rd.level_id = rl.id
        INNER JOIN submission_box    sb ON rl.box_id = sb.id
        INNER JOIN assessment         a ON sb.assessment_id = a.id
        WHERE rd.rubric_indicator_id = :indicatorId
        AND   rd.deleted_at IS NULL
    ';

    private const MIGRATE_RUBRIC_DESCRIPTOR_QUERY = '
        UPDATE rubric_descriptor
        SET    rubric_indicator_id = :courseIndicatorId
        WHERE  id           = :unitDescriptorId
    ';

    private const DELETE_DUPLICATE_RUBRIC_DESCRIPTOR_QUERY = '
        UPDATE rubric_descriptor
        SET    deleted_at = :now
        WHERE  id         = :unitDescriptorId
    ';


    /**
     * Query for finding rubric capabilities in a folder.
     *
     * @var IQuery
     */
    private $findRubricCapabilityQuery;


    /**
     * Query for finding rubric indicators in a capability.
     *
     * @var IQuery
     */
    private $findRubricIndicatorQuery;


    /**
     * Query for migrating a rubric indicator from unit to a course folder.
     *
     * Called when the indicator doesn't exist in the course.
     *
     * @var IQuery
     */
    private $migrateRubricIndicatorQuery;


    /**
     * Query for deleting a rubric capability from a unit folder.
     *
     * Called when a capability of the same name exists in the course.
     *
     * @var IQuery
     */
    private $deleteRubricCapabilityQuery;


    /**
     * Query for deleting a rubric indicator from a unit folder.
     *
     * Called when an indicator of the same name and capability exists in the
     * course.
     *
     * @var IQuery
     */
    private $deleteRubricIndicatorQuery;


    /**
     * Query for finding the usages of a rubric capability, indexed by
     * assessment.
     *
     * Called when migrating capabilities: all usages of a unit capability must
     * be moved to the course, and this must be done individually per assessment
     * (because the assessment may or may not be using the course capability).
     *
     * @var IQuery
     */
    private $findRubricCapabilityUsageQuery;


    /**
     * Query for migrating the usage of a rubric capability from unit to a
     * course folder for an assessment.
     *
     * Called when migrating capabilities: all usages of the unit capability
     * must be migrated, and this attempts to simply move them from the unit to
     * the course.
     *
     * @var IQuery
     */
    private $migrateRubricCapabilityUsageQuery;


    /**
     * Query for deleting the usages of a rubric capability from an assessment.
     *
     * Called when migrating capabilities: all usages of the unit capability
     * must be migrated, and if moving them from the unit capability fails
     * (@see $this->migrateRubricCapabilityUsageQuery), the usage is simply
     * deleted.
     *
     * @var IQuery
     */
    private $deleteRubricCapabilityUsageQuery;


    /**
     * Query for finding the usages of a rubric indicator, indexed by
     * assessment.
     *
     * Called when migrating indicators: all usages of a unit indicator must be
     * moved to the course, and this must be done individually per assessment
     * (because the assessment may or may not be using the course indicator).
     *
     * @var IQuery
     */
    private $findRubricIndicatorUsageQuery;


    /**
     * Query for migrating the usages of a rubric indicator from unit to a
     * course folder.
     *
     * Called when an indicator of the same name and capability exists in the
     * course, and the unit indicator is used by one or more assessments.
     *
     * @var IQuery
     */
    private $migrateRubricIndicatorUsageQuery;


    /**
     * Query for deleting the usages of a rubric indicator from an assessment.
     *
     * Called when migrating indicators: all usages of the unit indicator must
     * be migrated, and if moving them from the unit indicator fails
     * (@see $this->migrateRubricIndicatorUsageQuery), the usage is simply
     * deleted.
     *
     * @var IQuery
     */
    private $deleteRubricIndicatorUsageQuery;


    /**
     * Query for finding usages of rubric descriptors for an indicator.
     *
     * Called when an indicator of the same name and capability exists in the
     * course and unit. It is used for finding if a descriptor is in the same
     * level for both the course and unit indicator.
     *
     * @var IQuery
     */
    private $findRubricDescriptorQuery;


    /**
     * Query for migrating usages of rubric descriptors.
     *
     * Used when migrating descriptors, and the unit-level descriptor exists
     * but the course-level does not: the unit-level descriptor is moved into
     * the course-level.
     *
     * @var IQuery
     */
    private $migrateRubricDescriptorQuery;


    /**
     * Query for deleting usages of rubric descriptors.
     *
     * Used when migrating descriptors, and there's a descriptor in the same
     * level for both the course-level and unit-level indicator: the unit-level
     * descriptor is removed.
     *
     * @var IQuery
     */
    private $deleteDuplicateRubricDescriptorQuery;


    /**
     * @var Logger
     */
    private $log;


    /**
     * @var IConnector
     */
    private $db;


    /**
     * @var string
     */
    private $now;


    /**
     * Is this actually performing an update?
     *
     * @var bool
     */
    private $perform = false;


    /**
     * The fully-qualified domain name for this instance.
     *
     * @var string
     */
    private $fqdn;


    /**
     * Significant events: read these, if nothing else.
     *
     * Indexed by assessment ID.
     *
     * @var array|string[][]
     */
    private $significantEvents = [];


    /**
     * Set up and run the repair process.
     *
     * Install all instance vars, and wraps the repair process in a transaction
     * for commit (if `--perform` is on) or rollback (if it's off, or an error
     * is raised).
     *
     * @inheritdoc
     */
    public function run(ContainerInterface $di, LoopInterface $loop, Logger $log, array $opts, string $flags): void
    {
        // Install logger and DB
        $this->log     = $log;
        $this->db      = $di->get('databaseInternal');
        $this->now     = Alaress_Date_Format::now()->getDatabase();
        $this->perform = isset($opts['p']) || isset($opts['perform']);
        $config        = $di->get('config');
        $this->fqdn    = trim($config->get('site_hostname') . "." . $config->get('site_domain'), ';.');


        // log perform / dry run
        if ($this->perform) {
            $this->log->warning('Running in perform mode (-p): changes will be written to database');
        } else {
            $this->log->warning('Running in dry-run mode: use -p to write changes to database');
        }

        // log verbosity
        $verbosity = count($opts['verbose'] ?? []);
        if ($verbosity <= 0) {
            $this->log->warning('Running in non-verbose mode: Use -v, -vv or -vvv for varying levels of verbosity');
        } elseif ($verbosity === 1) {
            $this->log->warning('Running in verbose mode (-v)');
        } elseif ($verbosity === 2) {
            $this->log->warning('Running in very verbose mode (-vv)');
        } elseif ($verbosity >= 3) {
            $this->log->warning('Running in very very verbose mode (-vvv)');
        }


        // Capability and indicator (not usage) queries
        $this->findRubricCapabilityQuery
            = $this->db->prepareQuery(self::FIND_RUBRIC_CAPABILITY_QUERY);
        $this->findRubricIndicatorQuery
            = $this->db->prepareQuery(self::FIND_RUBRIC_INDICATOR_QUERY);
        $this->migrateRubricIndicatorQuery
            = $this->db->prepareQuery(self::MIGRATE_RUBRIC_INDICATOR_QUERY);
        $this->deleteRubricCapabilityQuery
            = $this->db->prepareQuery(self::DELETE_RUBRIC_CAPABILITY_QUERY);
        $this->deleteRubricIndicatorQuery
            = $this->db->prepareQuery(self::DELETE_RUBRIC_INDICATOR_QUERY);


        // Capability usage queries
        $this->findRubricCapabilityUsageQuery
            = $this->db->prepareQuery(self::FIND_RUBRIC_CAPABILITY_USAGE_QUERY);
        $this->migrateRubricCapabilityUsageQuery
            = $this->db->prepareQuery(self::MIGRATE_RUBRIC_CAPABILITY_USAGE_QUERY);
        $this->deleteRubricCapabilityUsageQuery
            = $this->db->prepareQuery(self::DELETE_RUBRIC_CAPABILITY_USAGE_QUERY);


        // Indicator usage queries
        $this->findRubricIndicatorUsageQuery
            = $this->db->prepareQuery(self::FIND_RUBRIC_INDICATOR_USAGE_QUERY);
        $this->migrateRubricIndicatorUsageQuery
            = $this->db->prepareQuery(self::MIGRATE_RUBRIC_INDICATOR_USAGE_QUERY);
        $this->deleteRubricIndicatorUsageQuery
            = $this->db->prepareQuery(self::DELETE_RUBRIC_INDICATOR_USAGE_QUERY);


        // Descriptor queries
        $this->findRubricDescriptorQuery
            = $this->db->prepareQuery(self::FIND_RUBRIC_DESCRIPTOR_QUERY);
        $this->migrateRubricDescriptorQuery
            = $this->db->prepareQuery(self::MIGRATE_RUBRIC_DESCRIPTOR_QUERY);
        $this->deleteDuplicateRubricDescriptorQuery
            = $this->db->prepareQuery(self::DELETE_DUPLICATE_RUBRIC_DESCRIPTOR_QUERY);


        // Wrap entire loop in a transaction
        $this->db->beginTransaction();
        try {
            $this->repair();
            if ($this->perform) {
                $this->log->notice('Repair complete: committing changes');
                $this->db->commit();
            } else {
                $this->log->notice('Repair dry run complete: rolling back changes');
                $this->db->rollBack();
            }
        } catch (\Throwable $e) {
            $this->log->error('Error occurred during repair: rolling back changes');
            $this->db->rollBack();
            throw $e;
        }


        // Talk about significant events
        if (!empty($this->significantEvents)) {
            $this->log->warning('Assessments affected:');
            $this->outputSignificantEvents();
        } else {
            $this->log->warning('No assessments affected');
        }
    }


    /**
     * Run the repair process.
     */
    private function repair(): void
    {
        // Find each course
        // Retrieve its capabilities+indicators
        /** @var Specification\Model[] $courses */
        $courses = Specification\Mapper::getInstance()->findAll();
        foreach ($courses as $course) {
            $this->log->info(
                sprintf(
                    'Finding capabilities for course #%d (%s)',
                    $course->getFolder()->getId(),
                    $course->getTitle()
                )
            );


            // Don't go further if there's no capabilities in course
            $this->findRubricCapabilityQuery->execute(['folderId' => $course->getFolder()->getId()]);
            if ($this->findRubricCapabilityQuery->rowCount() === 0) {
                $this->log->debug(self::indent(1) . 'course contains no capabilities: no action required');
                continue;
            }


            /** @var Specification\Unit\Model[] $units */
            $units = Specification\Unit\Mapper::getInstance()->findAllByCourseFolderId($course->getFolder()->getId());
            foreach ($units as $unit) {
                $this->log->info(
                    sprintf(
                        self::indent(1) . 'finding capabilities for unit #%d (%s)',
                        $unit->getFolder()->getId(),
                        $unit->getTitle()
                    )
                );

                $this->findRubricCapabilityQuery->execute(['folderId' => $unit->getFolder()->getId()]);
                $unitCapabilities = $this->findRubricCapabilityQuery->fetchColumnArray('name', 'id');
                if (count($unitCapabilities) === 0) {
                    $this->log->debug(self::indent(2) . 'unit contains no capabilities: no action required');
                } else {
                    // Must re-fetch the course row set after every unit, because
                    // repairCapabilities may change the course's capabilities.
                    $this->findRubricCapabilityQuery->execute(['folderId' => $course->getFolder()->getId()]);
                    $courseCapabilities = $this->findRubricCapabilityQuery->fetchColumnArray('name', 'id');

                    $this->repairCapabilities($courseCapabilities, $unitCapabilities);

                    // Now that clean up of capabilities is done, let's delete capabilities without indicators.
                    // No indicators found for this capability. Delete it.
                    $this->findRubricCapabilityQuery->execute(['folderId' => $unit->getFolder()->getId()]);
                    $unitCapabilities = $this->findRubricCapabilityQuery->fetchColumnArray('name', 'id');
                    $this->deleteEmptyCapabilities($unitCapabilities);
                }
                // disable rubric for assessments which are not using rubric
                $this->disableRubric($unit->getFolder()->getId());
            }

            $this->findRubricCapabilityQuery->execute(['folderId' => $course->getFolder()->getId()]);
            $courseCapabilities = $this->findRubricCapabilityQuery->fetchColumnArray('name', 'id');
            $this->deleteEmptyCapabilities($courseCapabilities);
        }
    }


    /**
     * Attempt to repair this set of capabilities by migrating its conflicting
     * capabilities (if any) into the course.
     *
     * @param array $courseCapabilities key: capability ID, value: capability name
     * @param array $unitCapabilities   key: capability ID, value: capability name
     */
    private function repairCapabilities(array $courseCapabilities, array $unitCapabilities): void
    {
        foreach ($courseCapabilities as $courseCapabilityId => $courseCapabilityName) {
            $capabilityConflict = false;
            foreach ($unitCapabilities as $unitCapabilityId => $unitCapabilityName) {
                if (RubricUtil::normalizeName($courseCapabilityName) === RubricUtil::normalizeName($unitCapabilityName)) {
                    $capabilityConflict = true;
                    $this->log->info(
                        sprintf(
                            self::indent(2) . 'capability "%s" exists in course and unit: resolving conflict',
                            $courseCapabilityName
                        )
                    );

                    $this->repairCapability($courseCapabilityId, $unitCapabilityId, $courseCapabilityName, $unitCapabilityName);
                    break;
                }
            }

            if (!$capabilityConflict) {
                $this->log->debug(
                    sprintf(
                        self::indent(2) . 'capability "%s" exists in course, but not in unit: no action required',
                        $courseCapabilityName
                    )
                );
            }
        }
    }


    /**
     * Attempt to repair this unit capability by migrating its indicators, and
     * any usages therein, into the course capability.
     *
     * @param int    $courseCapabilityId   the ID of the course-level capability
     * @param int    $unitCapabilityId     the ID of the unit-level capability
     * @param string $courseCapabilityName the capability name (Logging only, and
     *                                     the same for both course and unit)
     * @param string $unitCapabilityName   the capability name (Logging only, and
     *                                     the same for both course and unit)
     */
    private function repairCapability(int $courseCapabilityId, int $unitCapabilityId, string $courseCapabilityName, string $unitCapabilityName): void
    {
        // Capability usages must be migrated from the unit to the course,
        // and deleted if there is a conflict.
        // (This means that any mark-per-capability max values will be lost)
        $this->findRubricCapabilityUsageQuery
            ->execute(['unitCapabilityId' => $unitCapabilityId,]);
        foreach ($this->findRubricCapabilityUsageQuery->fetchColumnArray('id') as $assessmentId) {
            // attempt to migrate
            try {
                $this->migrateRubricCapabilityUsageQuery
                    ->execute([
                        'courseCapabilityId' => $courseCapabilityId,
                        'unitCapabilityId'   => $unitCapabilityId,
                        'assessmentId'       => $assessmentId,
                    ]);
            } catch (Alaress\Database\Exception $e) {
                // Foreign key error:
                // this happens when the assessment is already using the
                // course-level capability.
                // Delete the unit-level capability usage completely
                // (This is significant)
                $this->deleteRubricCapabilityUsageQuery
                    ->execute([
                        'unitCapabilityId' => $unitCapabilityId,
                        'assessmentId'     => $assessmentId,
                    ]);
                $this->logSignificantEvent(
                    (int)$assessmentId,
                    sprintf(
                        'removed usage record for unit-level capability "%s", and replaced with equivalent course-level record "%s" (original mark-per-capability value not preserved)',
                        $unitCapabilityName,
                        $courseCapabilityName
                    )
                );
            }
        }


        // Now clean up indicators
        $this->findRubricIndicatorQuery->execute(['capabilityId' => $unitCapabilityId]);
        $unitIndicators = $this->findRubricIndicatorQuery->fetchColumnArray('name', 'id');

        // Compare each of the unit indicator names with course indicator names
        foreach ($unitIndicators as $unitIndicatorId => $unitIndicatorName) {
            $matchingCourseIndicatorId   = null;
            $matchingCourseIndicatorName = '';

            // Need to refetch course indicators each iteration, because this
            // loop can change the indicators available.
            $this->findRubricIndicatorQuery->execute(['capabilityId' => $courseCapabilityId]);
            $courseIndicators = $this->findRubricIndicatorQuery->fetchColumnArray('name', 'id');

            $normalizedUnitIndicatorName = RubricUtil::normalizeName($unitIndicatorName);
            foreach ($courseIndicators as $courseIndicatorId => $courseIndicatorName) {
                if (RubricUtil::normalizeName($courseIndicatorName) === $normalizedUnitIndicatorName) {
                    $matchingCourseIndicatorId   = $courseIndicatorId;
                    $matchingCourseIndicatorName = $courseIndicatorName;
                    break;
                }
            }


            if ($matchingCourseIndicatorId) {
                // Indicator exists in course and unit.
                // Need to delete the unit indicator, then migrate descriptors
                // from the now-deleted unit indicator into the course indicator.
                $this->log->info(
                    sprintf(
                        self::indent(3) . 'indicator "%s" exists in course and unit: deleting from unit, then migrating usage to course',
                        $unitIndicatorName
                    )
                );
                $this->repairIndicator($matchingCourseIndicatorId, $unitIndicatorId, $matchingCourseIndicatorName, $unitIndicatorName);
            } else {
                // Indicator exists in unit, but not course.
                // Can just move the unit indicator into the course.
                $this->log->info(
                    sprintf(
                        self::indent(3) . 'indicator "%s" exists in unit, but not course: moving from unit to course',
                        $unitIndicatorName
                    )
                );
                $this->migrateRubricIndicatorQuery
                    ->execute([
                        'courseCapabilityId' => $courseCapabilityId,
                        'unitIndicatorId'    => $unitIndicatorId,
                    ]);
            }
        }


        // Now repair the capability itself, by deleting the unit-level copy
        $this->deleteRubricCapabilityQuery
            ->execute([
                'now'              => $this->now,
                'unitCapabilityId' => $unitCapabilityId,
            ]);
    }


    /**
     * Attempt to repair this unit indicator by migrating its usages into the
     * course indicator.
     *
     * @param int    $courseIndicatorId   the ID of the course-level indicator
     * @param int    $unitIndicatorId     the ID of the unit-level indicator
     * @param string $courseIndicatorName the indicator name (Logging only, and
     *                                    the same for both course and unit)
     * @param string $unitIndicatorName   the indicator name (Logging only, and
     *                                    the same for both course and unit)
     */
    private function repairIndicator(int $courseIndicatorId, int $unitIndicatorId, string $courseIndicatorName, string $unitIndicatorName): void
    {
        $this->findRubricIndicatorUsageQuery
            ->execute(['unitIndicatorId' => $unitIndicatorId,]);
        foreach ($this->findRubricIndicatorUsageQuery->fetchColumnArray('id') as $assessmentId) {
            // attempt to migrate
            try {
                $this->migrateRubricIndicatorUsageQuery
                    ->execute([
                        'courseIndicatorId' => $courseIndicatorId,
                        'unitIndicatorId'   => $unitIndicatorId,
                        'assessmentId'      => $assessmentId,
                    ]);
            } catch (Alaress\Database\Exception $e) {
                // Foreign key error:
                // this happens when the assessment is already using the
                // course-level indicator.
                // Delete the unit-level indicator usage completely
                // (This is significant)
                $this->deleteRubricIndicatorUsageQuery
                    ->execute([
                        'unitIndicatorId' => $unitIndicatorId,
                        'assessmentId'    => $assessmentId,
                    ]);
                $this->logSignificantEvent(
                    (int)$assessmentId,
                    sprintf(
                        'removed and recreated usage record for unit indicator "%s", and replaced with equivalent course-level indicator "%s" ',
                        $unitIndicatorName,
                        $courseIndicatorName
                    )
                );
            }
        }


        // Find the descriptors for the course and unit
        $courseDescriptors = $this->findDescriptors($courseIndicatorId);
        $unitDescriptors   = $this->findDescriptors($unitIndicatorId);
        foreach ($unitDescriptors as $assessmentId => $levels) {
            $removedDescriptorCount  = 0;
            $migratedDescriptorCount = 0;

            $this->log->notice(
                sprintf(self::indent(4) . 'checking assessment #%d for conflicting descriptors', $assessmentId)
            );

            foreach ($levels as $levelId => $unitDescriptorId) {
                $courseDescriptorId = $courseDescriptors[$assessmentId][$levelId] ?? null;
                if (isset($courseDescriptorId)) {
                    // Conflict: keep the course descriptor, delete the unit one
                    $this->deleteDuplicateRubricDescriptorQuery
                        ->execute([
                            'now'              => $this->now,
                            'unitDescriptorId' => $unitDescriptorId,
                        ]);
                    $this->log->notice(
                        sprintf(self::indent(5) . 'assessment has conflicts for level #%d', $levelId)
                    );
                    $removedDescriptorCount++;
                } else {
                    // No conflict: migrate the unit descriptor to the course
                    $this->migrateRubricDescriptorQuery
                        ->execute([
                            'courseIndicatorId' => $courseIndicatorId,
                            'unitDescriptorId'  => $unitDescriptorId,
                        ]);
                    $this->log->notice(
                        sprintf(self::indent(5) . 'assessment has no conflicts for level #%d', $levelId)
                    );
                    $migratedDescriptorCount++;
                }
            }


            // Affected assessments are significant
            $this->logSignificantEvent(
                (int)$assessmentId,
                sprintf(
                    'removed %d and migrated %d descriptors from indicator "%s"',
                    $removedDescriptorCount,
                    $migratedDescriptorCount,
                    $unitIndicatorName
                )
            );
        }


        // Now repair the indicator itself
        $this->deleteRubricIndicatorQuery
            ->execute([
                'now'             => $this->now,
                'unitIndicatorId' => $unitIndicatorId,
            ]);
    }

    /**
     * Finds capabilities without indicators and delete them
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
            $this->log->info(
                sprintf(
                    self::indent(1) . 'capability %s (%d) does not have any indicators: deleting',
                    trim($capabilityName),
                    $capabilityId
                )
            );
            // no indicators for this capability
            $this->deleteRubricCapabilityQuery
                ->execute([
                    'now'              => $this->now,
                    'unitCapabilityId' => $capabilityId,
                ]);
        }
    }

    /**
     * @param int $unitFolderId
     */
    private function disableRubric(int $unitFolderId): void
    {
        $ids = $this->db->executeQuery(
            'SELECT sb.id
            FROM submission_box sb
            JOIN assessment a ON sb.assessment_id = a.id
            JOIN v_assessment_relation var ON a.id = var.assessment_id
            LEFT JOIN folder f ON (f.id = var.folder_id)
            LEFT JOIN course_implementation ci ON (ci.id = var.course_implementation_id)
            LEFT JOIN course_specification cs ON (cs.id = var.course_specification_id)
            LEFT JOIN course_unit_specification cus ON (cus.id = var.course_unit_specification_id)
            LEFT JOIN rubric_indicator_usage rcu ON sb.id = rcu.submission_box_id
            WHERE TRUE #sb.use_rubric = 1
            AND (
                CASE (
                    CONCAT(
                        IF(var.project_assessment_id         IS NULL, "0", "1"), "-",
                        IF(var.course_implementation_id      IS NULL, "0", "1"), "-",
                        IF(var.course_unit_implementation_id IS NULL, "0", "1"), "-",
                        IF(var.course_specification_id       IS NULL, "0", "1"), "-",
                        IF(var.course_unit_specification_id  IS NULL, "0", "1")
                    )
                )
                    WHEN "0-0-0-0-0" THEN var.folder_id # Folder.
                    WHEN "0-0-0-1-1" THEN cus.folder_id # Course unit specification.
                    WHEN "0-1-1-0-0" THEN ci.folder_id  # Course unit implementation.
                    WHEN "1-0-0-0-0" THEN var.folder_id # Project.
                    WHEN "1-0-0-1-1" THEN cus.folder_id # Project within course unit specification.
                    WHEN "1-1-1-0-0" THEN ci.folder_id  # Project within course unit implementation.
                    ELSE "This case should occur due to the way v_assessment_relation is constructed."
                END
            ) = :folderId
            GROUP BY sb.id HAVING count(rcu.rubric_indicator_id) = 0',
            ['folderId' => $unitFolderId]
        )->fetchAll(PDO::FETCH_COLUMN);

        if (empty($ids)) {
            return;
        }

        $idString = implode(',', array_map('intval', $ids));
        $this->db->executeQuery("UPDATE submission_box set use_rubric = 0 WHERE id in ({$idString})");
        $this->log->info(self::indent(2) . "submission boxes {$idString} in unit {$unitFolderId} are not using rubric: disabling Rubric");
    }

    /**
     * Finds descriptors used by the indicator, grouped by assessment and then
     * by level.
     *
     * @param int $indicatorId
     * @return array[] Dimension 1: assessment ID
     *                 Dimension 2: level ID
     *                 Key:         descriptor ID
     */
    private function findDescriptors(int $indicatorId): array
    {
        $assessments = [];
        $this->findRubricDescriptorQuery->execute(['indicatorId' => $indicatorId,]);
        foreach ($this->findRubricDescriptorQuery->getIterator() as $row) {
            $assessments[$row['assessmentId']][$row['levelId']] = $row['id'];
        }
        return $assessments;
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
     * Log a significant event regarding an assessment.
     *
     * @param int    $assessmentId
     * @param string $message
     */
    private function logSignificantEvent(int $assessmentId, string $message): void
    {
        $this->significantEvents[$assessmentId][] = $message;
    }


    /**
     * Output significant events.
     */
    private function outputSignificantEvents(): void
    {
        foreach ($this->significantEvents as $assessmentId => $events) {
            $url = (new Uri())
                ->withScheme('https')
                ->withHost($this->fqdn)
                ->withPath('/learning/assessments/' . $assessmentId);
            $this->log->warning(self::indent(1) . $url);
            foreach ($events as $event) {
                $this->log->warning(self::indent(2) . $event);
            }
        }
    }
});
