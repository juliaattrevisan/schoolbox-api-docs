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
 * @copyright Copyright 2019, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

use Alaress\Database\Connector\IConnector;
use Aura\Di;
use Psr\Log\LoggerInterface as Logger;
use React\EventLoop\LoopInterface;
use Schoolbox\Application\Daemon\App;
use Schoolbox\Assessment\Component\SubmissionBox\Rubric\RubricMapper;
use Schoolbox\Assessment\Rubric\RowSet\Capability;
use Schoolbox\Assessment\Rubric\RowSet\Indicator;
use Schoolbox\Assessment\Rubric\RowSet\RowSet;
use Schoolbox\Learning\Course\DataModel\Specification;


require_once __DIR__ . '/../../conf/core/require.php';

spl_autoload_call('\\Schoolbox\\Application\\Daemon');

$opts   = getopt('vp', ['perform']);
$kernel = new Schoolbox\Application\Daemon\Kernel(
    'Rubric repair',
    [
        'verbose' => $opts['v'] ?? [],
        'perform' => isset($opts['p']) || isset($opts['perform']),
    ]
);

$kernel->run(new class extends App
{
    /**
     * Helper for indenting log output for legibility.
     */
    private const INDENT = '  ';


    /**
     * Query for migrating a rubric indicator from unit to a course folder.
     *
     * Called when the indicator doesn't exist in the course.
     *
     * @var string
     */
    private $migrateRubricIndicatorQuery = '
        UPDATE rubric_criterion
        SET    rubric_group_id = :courseCapabilityId
        WHERE  id              = :unitIndicatorId
    ';



    /**
     * Query for deleting a rubric capability from a unit folder.
     *
     * Called when a capability of the same name exists in the course.
     *
     * @var string
     */
    private $deleteRubricCapabilityQuery = '
        UPDATE rubric_group
        SET    deleted_at = :now
        WHERE  id         = :unitCapabilityId
    ';


    /**
     * Query for deleting a rubric indicator from a unit folder.
     *
     * Called when an indicator of the same name and capability exists in the
     * course.
     *
     * @var string
     */
    private $deleteRubricIndicatorQuery = '
        UPDATE rubric_criterion
        SET    deleted_at = :now
        WHERE  id         = :unitIndicatorId
    ';


    /**
     * Query for migrating the usages of a rubric capability from unit to a
     * course folder.
     *
     * Called when a capability of the same name exists in the course, and the
     * unit capability is used by one or more assessments.
     *
     * @var string
     */
    private $migrateRubricCapabilityUsageQuery = '
        UPDATE rubric_group_usage
        SET    rubric_group_id = :courseCapabilityId
        WHERE  rubric_group_id = :unitCapabilityId
    ';


    /**
     * Query for migrating the usages of a rubric indicator from unit to a
     * course folder.
     *
     * Called when an indicator of the same name and capability exists in the
     * course, and the unit indicator is used by one or more assessments.
     *
     * @var string
     */
    private $migrateRubricIndicatorUsageQuery = '
        UPDATE rubric_criterion_usage
        SET    criterion_id = :courseIndicatorId
        WHERE  criterion_id = :unitIndicatorId
    ';


    /**
     * Query for finding usages of rubric descriptors for an indicator.
     *
     * Called when an indicator of the same name and capability exists in the
     * course and unit. It is used for finding if a descriptor is in the same
     * level for both the course and unit indicator.
     *
     * @var string
     */
    private $findRubricDescriptorQuery = '
        SELECT rl.id AS levelId,
               rd.id,
               a.id AS assessmentId
        FROM       rubric_descriptor rd 
        INNER JOIN rubric_level      rl ON rd.level_id = rl.id
        INNER JOIN submission_box    sb ON rl.box_id = sb.id
        INNER JOIN assessment         a ON sb.assessment_id = a.id
        WHERE  criterion_id = :indicatorId
    ';


    /**
     * Query for deleting usages of rubric descriptors.
     *
     * Used when migrating descriptors, and there's a descriptor in the same
     * level for both the course-level and unit-level indicator: the unit-level
     * descriptor is removed.
     *
     * @var string
     */
    private $deleteDuplicateRubricDescriptorQuery = '
        UPDATE rubric_descriptor
        SET    deleted_at = :now
        WHERE  id         = :descriptorId
    ';


    /**
     * Query for migrating usages of rubric descriptors.
     *
     * Used when migrating descriptors, and the unit-level descriptor exists
     * but the course-level does not: the unit-level descriptor is moved into
     * the course-level.
     *
     * @var string
     */
    private $migrateRubricDescriptorQuery = '
        UPDATE rubric_descriptor
        SET    criterion_id = :courseIndicatorId
        WHERE  id           = :descriptorId
    ';


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
     * Is this actually performing an update?
     *
     * @var bool
     */
    private $perform = false;


    /**
     * Set up and run the repair process.
     *
     * Install all instance vars, and wraps the repair process in a transaction
     * for commit (if `--perform` is on) or rollback (if it's off, or an error
     * is raised).
     *
     * @param Di\Container  $di
     * @param LoopInterface $loop
     * @param Logger        $log
     * @param array         $opts
     */
    public function run(Di\Container $di, LoopInterface $loop, Logger $log, array $opts): void
    {
        // Install logger and DB
        $this->log     = $log;
        $this->db      = $di->get('databaseInternal');
        $this->now     = Alaress_Date_Format::now();
        $this->perform = $opts['perform'] ?? false;


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
    }


    /**
     * Run the repair process.
     */
    private function repair(): void
    {
        // Find each course
        // Retrieve its capabilities+indicators
        $rubricMapper = RubricMapper::getInstance();
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
            $courseRowSet = $rubricMapper->retrieveRowSet($course->getFolder(), false);

            // Don't go further if there's no capabilities in course
            $courseCapabilityCount = count($courseRowSet->getCapabilities());
            if ($courseCapabilityCount === 0) {
                $this->log->debug(self::indent(1).'course contains no capabilities: no action required');
                continue;
            }


            /** @var Specification\Unit\Model[] $units */
            $units = Specification\Unit\Mapper::getInstance()->findAllByCourseFolderId($course->getFolder()->getId());
            foreach ($units as $unit) {
                $this->log->info(
                    sprintf(
                        self::indent(1).'finding capabilities for unit #%d (%s)',
                        $unit->getFolder()->getId(),
                        $unit->getTitle()
                    )
                );
                // Must re-fetch the course row set after every unit, because
                // repairRowSet may change the course row set.
                $courseRowSet        = $rubricMapper->retrieveRowSet($course->getFolder(), false);
                $unitRowSet          = $rubricMapper->retrieveRowSet($unit->getFolder(), false);
                $unitCapabilityCount = count($unitRowSet->getCapabilities());

                if ($unitCapabilityCount === 0) {
                    $this->log->debug(self::indent(2).'unit contains no capabilities: no action required');
                    continue;
                }

                $this->repairRowSet($courseRowSet, $unitRowSet);
            }
        }
    }


    /**
     * Attempt to repair this unit rowset by migrating its conflicting
     * capabilities (if any) into the course rowset.
     *
     * @param RowSet $courseRowSet
     * @param RowSet $unitRowSet
     */
    private function repairRowSet(RowSet $courseRowSet, RowSet $unitRowSet): void
    {
        foreach ($courseRowSet->getCapabilities() as $courseCapability) {
            $courseCapabilityName = $courseCapability->getName();
            $capabilityConflict   = false;
            foreach ($unitRowSet->getCapabilities() as $unitCapability) {
                if ($courseCapabilityName === $unitCapability->getName()) {
                    $capabilityConflict = true;
                    $this->log->info(
                        sprintf(
                            self::indent(2).'capability "%s" exists in course and unit: resolving conflict',
                            $courseCapabilityName
                        )
                    );

                    $this->repairCapability($courseCapability, $unitCapability);
                    break;
                }
            }

            if (!$capabilityConflict) {
                $this->log->debug(
                    sprintf(
                        self::indent(2).'capability "%s" exists in course, but not in unit: no action required',
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
     * @param Capability $courseCapability
     * @param Capability $unitCapability
     */
    private function repairCapability(Capability $courseCapability, Capability $unitCapability): void
    {
        // Capability usages must be migrated from the unit to the course.
        $this->db
            ->prepareQuery($this->migrateRubricCapabilityUsageQuery)
            ->execute([
                'courseCapabilityId' => $courseCapability->getId(),
                'unitCapabilityId'   => $unitCapability->getId(),
            ]);

        foreach ($unitCapability->getIndicators() as $unitIndicator) {
            $unitIndicatorName       = $unitIndicator->getName();
            $matchingCourseIndicator = null;
            foreach ($courseCapability->getIndicators() as $courseIndicator) {
                if ($courseIndicator->getName() === $unitIndicatorName) {
                    $matchingCourseIndicator = $courseIndicator;
                    break;
                }
            }


            if ($matchingCourseIndicator) {
                // Indicator exists in course and unit.
                // Need to delete the unit indicator, then migrate descriptors
                // from the now-deleted unit into the course.
                $this->log->info(
                    sprintf(
                        self::indent(3).'indicator "%s" exists in course and unit: deleting from unit, then migrating to course',
                        $unitIndicatorName
                    )
                );
                $this->db
                    ->prepareQuery($this->deleteRubricIndicatorQuery)
                    ->execute(['now' => $this->now, 'unitIndicatorId' => $unitIndicator->getId(),]);
                $this->repairIndicator($matchingCourseIndicator, $unitIndicator);

            } else {
                // Indicator exists in unit, but not course.
                // Can just move the unit indicator into the course.
                $this->log->info(
                    sprintf(
                        self::indent(3).'indicator "%s" exists in unit, but not course: moving from unit to course',
                        $unitIndicatorName
                    )
                );
                $this->db
                    ->prepareQuery($this->migrateRubricIndicatorQuery)
                    ->execute(['courseCapabilityId' => $courseCapability->getId(), 'unitIndicatorId' => $unitIndicator->getId(),]);
            }
        }


        // Now repair the capability itself, by deleting the unit-level copy
        $this->db
            ->prepareQuery($this->deleteRubricCapabilityQuery)
            ->execute(['now' => $this->now, 'unitCapabilityId' => $unitCapability->getId(),]);
    }


    /**
     * Attempt to repair this unit indicator by migrating its usages into the
     * course indicator.
     *
     * @param Indicator $courseIndicator
     * @param Indicator $unitIndicator
     */
    private function repairIndicator(Indicator $courseIndicator, Indicator $unitIndicator): void
    {
        // * move rubric_criterion_usage
        $this->db
            ->prepareQuery($this->migrateRubricIndicatorUsageQuery)
            ->execute([
                'courseIndicatorId' => $courseIndicator->getId(),
                'unitIndicatorId'   => $unitIndicator->getId(),
            ]);

        // descriptors
        $courseDescriptors = $this->findDescriptors($courseIndicator);
        $unitDescriptors   = $this->findDescriptors($unitIndicator);
        foreach ($unitDescriptors as $assessmentId => $levels) {

            $this->log->notice(
                sprintf(self::indent(4).'checking assessment #%d for conflicting usage', $assessmentId)
            );

            foreach ($levels as $levelId => $unitDescriptorId) {
                $courseDescriptorId = $courseDescriptors[$assessmentId][$levelId] ?? null;
                if (isset($courseDescriptorId)) {
                    // Conflicting descriptor: keep the course version?
                    $this->db
                        ->prepareQuery($this->deleteDuplicateRubricDescriptorQuery)
                        ->execute(['now' => $this->now, 'descriptorId' => $unitDescriptorId,]);
                    $this->log->warning(
                        sprintf(self::indent(5).'assessment has conflicts for level #%d', $levelId)
                    );
                } else {
                    // No conflicting descriptor: migrate the unit descriptor to
                    // the course
                    $this->db
                        ->prepareQuery($this->migrateRubricDescriptorQuery)
                        ->execute([
                            'courseIndicatorId' => $courseIndicator->getId(),
                            'descriptorId'      => $unitDescriptorId,
                        ]);
                    $this->log->notice(
                        sprintf(self::indent(5).'assessment has no conflicts for level #%d', $levelId)
                    );
                }
            }

            // Is this descriptor used in the course copy?
        }


        // The following should not exist at all. If they do that's an error,
        // but move them anyway
        // * move rubric_descriptor_mark
        // * move rubric_group_mark
    }


    /**
     * Finds descriptors used by the indicator, grouped by assessment and then
     * by level.
     *
     * @param Indicator $indicator
     * @return array[] Dimension 1: assessment ID
     *                 Dimension 2: level ID
     *                 Key:         descriptor ID
     */
    private function findDescriptors(Indicator $indicator): array
    {
        $assessments = [];
        $descriptorQuery = $this->db->prepareQuery($this->findRubricDescriptorQuery);
        $descriptorQuery->execute(['indicatorId' => $indicator->getId(),]);
        foreach ($descriptorQuery->getIterator() as $row) {
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
});
