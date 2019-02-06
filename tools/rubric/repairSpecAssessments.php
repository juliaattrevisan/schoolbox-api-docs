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
     * Query for deleting a rubric capability from unit to a course folder.
     *
     * Called when the capability does exist in the course.
     *
     * @var string
     */
    private $deleteRubricCapabilityQuery = '
        UPDATE rubric_group
        SET    deleted_at = :now
        WHERE  id         = :unitCapabilityId
    ';


    /**
     * Query for deleting a rubric indicator from unit to a course folder.
     *
     * Called when the indicator does exist in the course.
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
     * @var string
     */
    private $findRubricDescriptorQuery = '
        SELECT level_id AS levelId,
               id
        FROM   rubric_descriptor
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
     * Run loop
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


        // Find each course
        // Retrieve its capabilities+indicators
        $rubricMapper = RubricMapper::getInstance();
        /** @var Specification\Model[] $courses */
        $courses = Specification\Mapper::getInstance()->findAll();
        foreach ($courses as $course) {
            $log->info(sprintf('Finding capabilities for course "%s"...', $course->getTitle()));
            $courseRowSet = $rubricMapper->retrieveRowSet($course->getFolder(), false);

            // Don't go further if there's no capabilities in course
            $courseCapabilityCount = count($courseRowSet->getCapabilities());
            if ($courseCapabilityCount === 0) {
                $log->debug('    course contains no capabilities: no action required');
                continue;
            }

            // Do something now
            $log->info(sprintf('    course contains %d capabilities: checking units...', $courseCapabilityCount));

            /** @var Specification\Unit\Model[] $units */
            $units = Specification\Unit\Mapper::getInstance()->findAllByCourseFolderId($course->getFolder()->getId());
            foreach ($units as $unit) {
                $log->debug(sprintf('    finding capabilities for unit "%s"...', $unit->getTitle()));
                $unitRowSet          = $rubricMapper->retrieveRowSet($unit->getFolder(), false);
                $unitCapabilityCount = count($unitRowSet->getCapabilities());

                if ($unitCapabilityCount === 0) {
                    $log->debug('        unit contains no capabilities: no action required');
                    continue;
                }

                // Do something now
                $log->debug(sprintf('        unit contains %d capabilities: checking compatibility', $unitCapabilityCount));
                $this->repairRowset($courseRowSet, $unitRowSet);
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
    private function repairRowset(RowSet $courseRowSet, RowSet $unitRowSet): void
    {
        foreach ($courseRowSet->getCapabilities() as $courseCapability) {
            $courseCapabilityName = $courseCapability->getName();
            $capabilityConflict   = false;
            foreach ($unitRowSet->getCapabilities() as $unitCapability) {
                if ($courseCapabilityName === $unitCapability->getName()) {
                    $capabilityConflict = true;
                    $this->log->info(
                        sprintf(
                            '        capability "%s" exists in course and unit: resolving conflict',
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
                        '        capability "%s" exists in course, but not in unit: no action required',
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
        if ($this->perform) {
            $this->db
                ->prepareQuery($this->migrateRubricCapabilityUsageQuery)
                ->execute([
                    'courseCapabilityId' => $courseCapability->getId(),
                    'unitCapabilityId'   => $unitCapability->getId(),
                ]);
        }

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
                        '            indicator "%s" exists in course and unit: deleting from unit, then migrating to course',
                        $unitIndicatorName
                    )
                );
                if ($this->perform) {
                    $this->db
                        ->prepareQuery($this->deleteRubricIndicatorQuery)
                        ->execute(['now' => $this->now, 'unitIndicatorId' => $unitIndicator->getId(),]);
                }
                $this->repairIndicator($matchingCourseIndicator, $unitIndicator);

            } else {
                // Indicator exists in unit, but not course.
                // Can just move the unit indicator into the course.
                $this->log->info(
                    sprintf(
                        '            indicator "%s" exists in unit, but not course: moving from course to unit',
                        $unitIndicatorName
                    )
                );
                if ($this->perform) {
                    $this->db
                        ->prepareQuery($this->migrateRubricIndicatorQuery)
                        ->execute(['courseCapabilityId' => $courseCapability->getId(), 'unitIndicatorId' => $unitIndicator->getId(),]);
                }
            }
        }


        // Now repair the capability itself, by deleting the unit-level copy
        if ($this->perform) {
            $this->db
                ->prepareQuery($this->deleteRubricCapabilityQuery)
                ->execute(['now' => $this->now, 'unitCapabilityId' => $unitCapability->getId(),]);
        }
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
        if ($this->perform) {
            $this->db
                ->prepareQuery($this->migrateRubricIndicatorUsageQuery)
                ->execute([
                    'courseIndicatorId' => $courseIndicator->getId(),
                    'unitIndicatorId'   => $unitIndicator->getId(),
                ]);
        }

        // descriptors
        $descriptorQuery = $this->db->prepareQuery($this->findRubricDescriptorQuery);
        $descriptorQuery->execute(['indicatorId' => $courseIndicator->getId(),]);
        $courseDescriptors = $descriptorQuery->fetchColumnArray('id', 'levelId');
        $descriptorQuery->execute(['indicatorId' => $unitIndicator->getId(),]);
        $unitDescriptors = $descriptorQuery->fetchColumnArray('id', 'levelId');

//        var_dump($courseDescriptors, $unitDescriptors);


        foreach ($unitDescriptors as $levelId => $unitDescriptorId) {
            // Is this descriptor used in the course copy?
            $courseDescriptorId = $courseDescriptors[$levelId] ?? null;
            if (isset($courseDescriptorId)) {
                // Conflicting descriptor: keep the course version?
                if ($this->perform) {
                    $this->db
                        ->prepareQuery($this->deleteDuplicateRubricDescriptorQuery)
                        ->execute(['now' => $this->now, 'descriptorId' => $unitDescriptorId,]);
                }
            } else {
                // No conflicting descriptor: migrate the unit descriptor to
                // the course
                if ($this->perform) {
                    $this->db
                        ->prepareQuery($this->migrateRubricDescriptorQuery)
                        ->execute([
                            'courseIndicatorId' => $courseIndicator->getId(),
                            'descriptorId'      => $unitDescriptorId,
                        ]);
                }
            }
        }


        // The following should not exist at all. If they do that's an error,
        // but move them anyway
        // * move rubric_descriptor_mark
        // * move rubric_group_mark
    }
});
