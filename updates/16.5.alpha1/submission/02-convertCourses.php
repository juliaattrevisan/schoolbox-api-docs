<?php
/**
 * Convert courses from old (parents of folders with course builders) to new (course table rows).
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

use Psr\Log\LoggerInterface;

require_once __DIR__ . '/../../../conf/core/require.php';
spl_autoload_call('\\Schoolbox\\Application\\Daemon');

$kernel = new Schoolbox\Application\Daemon\Kernel('Convert Courses and Units', [
    'verbose' => [ false, false, false ],
    'errors'  => E_ALL,
]);

$kernel->run(function($di, $loop, LoggerInterface $log, $opts) {

    // Convert courses
    $successCount = 0;
    $failureCount = 0;

    $database = Database::getInstance();

    // Find old courses and units
    $selectOldCoursesQuery = $database->prepare("
        SELECT DISTINCT
            `folder`.`id`,
            `folder`.`name`
        FROM folder
        LEFT JOIN `folder` AS `unit` ON `folder`.`id` = `unit`.`parent`
        INNER JOIN component_instance ci ON ci.identifier = unit.id AND ci.module = 'homepage'
        LEFT JOIN component_definition cd ON ci.component_definition_id = cd.id
        WHERE `folder`.`deleted_at` IS NULL
        AND `cd`.`class` = 'Component_Homepage_CourseBuilderController'
        AND `unit`.`deleted_at` IS NULL
    ");
    $selectOldUnitsQuery = $database->prepare("
        SELECT DISTINCT
            `folder`.`id`,
            `folder`.`name`
        FROM `folder`
        LEFT JOIN folder AS course ON `folder`.parent = course.id
        RIGHT JOIN component_instance ci ON ci.identifier = `folder`.id AND ci.module = 'homepage'
        LEFT JOIN component_definition cd ON ci.component_definition_id = cd.id
        LEFT JOIN `task` AS `t` ON `folder`.`id` = `t`.`category_id` AND `t`.`deleted_at` IS NULL
        WHERE `folder`.`deleted_at` IS NULL
        AND `cd`.`class` = 'Component_Homepage_CourseBuilderController'
        AND `course`.`deleted_at` IS NULL
        AND `folder`.`parent` = :parent
    ");

    // Insert new courses and units
    $insertNewCourseQuery = $database->prepare("INSERT INTO course (folder_id) VALUES (:folderId)");
    $insertNewUnitQuery   = $database->prepare("INSERT INTO course_unit (folder_id, course_id) VALUES (:folderId, :courseId)");


    // Go
    $selectOldCoursesQuery->execute();

    $log->debug(sprintf('%d courses found', $selectOldCoursesQuery->rowCount()));
    foreach ($selectOldCoursesQuery as $deprecatedCourse) {

        // Convert just this course
        $log->info(sprintf('Converting course "%s" (id: %d)...', $deprecatedCourse['name'], $deprecatedCourse['id']));
        try {
            $insertNewCourseQuery->execute(['folderId' => $deprecatedCourse['id']]);
            $log->debug(sprintf('Course "%s" converted', $deprecatedCourse['name']));
            $courseId = $database->lastInsertId();
            $successCount++;
        } catch (PDOException $e) {
            $log->error(sprintf('Course "%s" could not be converted!', $deprecatedCourse['name']), [$e]);
            $failureCount++;
            continue;
        }


        // Convert units for this course
        $selectOldUnitsQuery->execute(['parent' => $deprecatedCourse['id']]);
        $unitSuccessCount = 0;
        $unitFailureCount = 0;

        $log->debug(sprintf('%d units found in course', $selectOldUnitsQuery->rowCount()));
        foreach ($selectOldUnitsQuery as $deprecatedUnit) {
            $log->info(sprintf('Converting unit "%s" (id: %d)...', $deprecatedUnit['name'], $deprecatedUnit['id']));
            try {
                $insertNewUnitQuery->execute(['folderId' => $deprecatedUnit['id'], 'courseId' => $courseId]);
                $log->debug(sprintf('Unit "%s" converted', $deprecatedUnit['name']));
                $unitSuccessCount++;
            } catch (PDOException $e) {
                $log->error(sprintf('Unit "%s" could not be converted!', $deprecatedUnit['name']), [$e]);
                $unitFailureCount++;
            }
        }
        $log->info(sprintf('%d units converted, %d failed to convert', $unitSuccessCount, $unitFailureCount));

    }
    $log->info(sprintf('%d courses converted, %d failed to convert', $successCount, $failureCount));
});
