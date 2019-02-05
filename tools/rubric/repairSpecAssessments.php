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

$kernel = new Schoolbox\Application\Daemon\Kernel(
    'Rubric repair',
    [
        'verbose' => [false, false, false],
    ]
);

$kernel->run(new class extends App {
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
        // Find each course
        // Retrieve its capabilities+indicators
        $rubricMapper = RubricMapper::getInstance();
        /** @var Specification\Model[] $courses */
        $courses = Specification\Mapper::getInstance()->findAll();
        foreach ($courses as $course) {
            $log->debug(sprintf('Finding capabilities for course "%s"...', $course->getTitle()));
            $courseRowSet = $rubricMapper->retrieveRowSet($course->getFolder(), false);

            // Don't go further if there's no capabilities in course
            $courseCapabilityCount = count($courseRowSet->getCapabilities());
            if ($courseCapabilityCount === 0) {
                $log->debug('Course contains no capabilities: skipping');
                continue;
            }

            // Do something now
            $log->debug(sprintf('Course contains %d capabilities: checking units...', $courseCapabilityCount));

            /** @var Specification\Unit\Model[] $units */
            $units = Specification\Unit\Mapper::getInstance()->findAllByCourseFolderId($course->getFolder()->getId());
            foreach ($units as $unit) {
                $log->debug(sprintf('Finding capabilities for unit "%s"...', $unit->getTitle()));
                $unitRowSet = $rubricMapper->retrieveRowSet($unit->getFolder(), false);
                $unitCapabilityCount = count($unitRowSet->getCapabilities());

                if ($unitCapabilityCount === 0) {
                    $log->debug('Unit contains no capabilities: skipping');
                    continue;
                }

                // Do something now
                $log->debug(sprintf('Unit contains %d capabilities: checking compatibility', $unitCapabilityCount));
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
            foreach ($unitRowSet->getCapabilities() as $unitCapability) {
                if ($courseCapability->getName() === $unitCapability->getName()) {
                    $this->repairCapability($courseCapability, $unitCapability);
                }
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
        foreach ($unitCapability->getIndicators() as $unitIndicator) {
            $matchingCourseIndicator = null;
            foreach ($courseCapability->getIndicators() as $courseIndicator) {
                if ($courseIndicator->getName() === $unitIndicator->getName()) {
                    $matchingCourseIndicator = $courseIndicator;
                    break;
                }
            }

            // No matching indicator? make one
            if (!$matchingCourseIndicator) {
                $matchingCourseIndicator = (new Indicator())
                    ->setName($unitIndicator->getName());
                $courseCapability->getIndicators()->add($matchingCourseIndicator);
            }

            // Migrate any usages of this indicator.
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
        //
    }
});
