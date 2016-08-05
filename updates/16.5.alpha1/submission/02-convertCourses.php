<?php
/**
 * Convert courses from old (parents of folders with course builders) to new (course table rows).
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

use Psr\Log\LoggerInterface;
use Schoolbox\Assessment\Course;
use Schoolbox\Mapper\Exception as MapperException;

require_once __DIR__ . '/../../../conf/core/require.php';
spl_autoload_call('\\Schoolbox\\Application\\Daemon');

$kernel = new Schoolbox\Application\Daemon\Kernel('Convert Courses and Units', [
    'verbose' => [ false, false, false ],
    'errors'  => E_ALL,
]);

$kernel->run(function($di, $loop, LoggerInterface $log, $opts) {

    // Convert courses
    /** @var Course\DeprecatedModel[] $deprecatedCourses */
    $deprecatedCourses = Course\DeprecatedMapper::getInstance()->findAll();
    $newCourseMapper   = Course\Mapper::getInstance();
    $newUnitMapper     = Course\Unit\Mapper::getInstance();
    $successCount      = 0;
    $failureCount      = 0;

    $log->debug(sprintf('%d courses found', count($deprecatedCourses)));
    foreach ($deprecatedCourses as $deprecatedCourse) {

        // Convert just this course
        $log->info(sprintf('Converting course "%s" (id: %d)...', $deprecatedCourse->getName(), $deprecatedCourse->getId()));
        try {
            $newCourseMapper->save($newCourseMapper->convertDeprecatedModel($deprecatedCourse));
            $log->debug(sprintf('Course "%s" converted', $deprecatedCourse->getName()));
            $successCount++;
        } catch (MapperException $e) {
            $log->error(sprintf('Course "%s" could not be converted!', $deprecatedCourse->getName()), ['message' => $e->getMessage()]);
            $failureCount++;
        }


        // Convert units for this course
        $deprecatedUnits  = Course\Unit\DeprecatedMapper::getInstance()->findByCourseId($deprecatedCourse->getId());
        $unitSuccessCount = 0;
        $unitFailureCount = 0;

        $log->debug(sprintf('%d units found in course', count($deprecatedUnits)));
        foreach ($deprecatedUnits as $deprecatedUnit) {
            $log->info(sprintf('Converting unit "%s" (id: %d)...', $deprecatedUnit->getName(), $deprecatedUnit->getId()));
            try {
                $newUnitMapper->save($newUnitMapper->convertDeprecatedModel($deprecatedUnit));
                $log->debug(sprintf('Unit "%s" converted', $deprecatedUnit->getName()));
                $unitSuccessCount++;
            } catch (MapperException $e) {
                $log->error(sprintf('Unit "%s" could not be converted!', $deprecatedUnit->getName()), ['message' => $e->getMessage()]);
                $unitFailureCount++;
            }
        }
        $log->info(sprintf('%d units converted, %d failed to convert', $unitSuccessCount, $unitFailureCount));

    }
    $log->info(sprintf('%d courses converted, %d failed to convert', $successCount, $failureCount));
});
