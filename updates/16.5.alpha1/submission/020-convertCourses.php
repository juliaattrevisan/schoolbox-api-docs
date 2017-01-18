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
            `course`.`id`,
            `course`.`name`,
            `course`.`deleted_at`
        FROM `folder` AS `course`
        LEFT JOIN `folder` AS `unit` ON `course`.`id` = `unit`.`parent`
        INNER JOIN component_instance ci ON ci.identifier = unit.id AND ci.module = 'homepage'
        LEFT JOIN component_definition cd ON ci.component_definition_id = cd.id
        WHERE `cd`.`class` = 'Component_Homepage_CourseBuilderController'
    ");
    $selectOldUnitsQuery = $database->prepare("
        SELECT DISTINCT
            `unit`.`id`,
            `unit`.`name`,
            `unit`.`deleted_at`
        FROM `folder` AS `unit`
        LEFT JOIN folder AS course ON `unit`.parent = course.id
        RIGHT JOIN component_instance ci ON ci.identifier = `unit`.id AND ci.module = 'homepage'
        LEFT JOIN component_definition cd ON ci.component_definition_id = cd.id
        LEFT JOIN `task` AS `t` ON `unit`.`id` = `t`.`category_id` AND `t`.`deleted_at` IS NULL
        WHERE `cd`.`class` = 'Component_Homepage_CourseBuilderController'
        AND   `unit`.`parent` = :parent
    ");

    // Insert new courses and units
    // TODO find out if there's a viable created_at field to use for either of these tables
    $insertNewCourseQuery = $database->prepare("
        INSERT INTO course_specification (folder_id, created_at, updated_at, deleted_at) VALUES (:folderId, NOW(), NOW(), :deleted)
    ");
    $insertNewUnitQuery   = $database->prepare("
        INSERT INTO course_unit_specification (folder_id, course_specification_id, created_at, updated_at, deleted_at)
        VALUES (:folderId, :courseId, NOW(), NOW(), :deleted)
    ");

    $deleteNewCourseQuery = $database->prepare("UPDATE `course_specification` SET `deleted_at` = NOW() WHERE `id` = :courseId");

    // Go
    $selectOldCoursesQuery->execute();

    $log->debug(sprintf('%d courses found', $selectOldCoursesQuery->rowCount()));
    foreach ($selectOldCoursesQuery as $deprecatedCourse) {

        // Convert just this course
        $log->info(sprintf('Converting course "%s" (id: %d)...', $deprecatedCourse['name'], $deprecatedCourse['id']));
        try {
            $insertNewCourseQuery->execute([
                'folderId' => $deprecatedCourse['id'],
                'deleted'  => $deprecatedCourse['deleted_at'],
            ]);
            $log->debug(sprintf('Course "%s" converted', $deprecatedCourse['name']));
            $courseId = $database->lastInsertId();
            $successCount++;
        } catch (PDOException $e) {
            $log->error(sprintf('Course "%s" could not be converted!', $deprecatedCourse['name']), [$e]);
            $log->warning($e->getMessage());
            $failureCount++;
            continue;
        }


        // Convert units for this course
        $selectOldUnitsQuery->execute(['parent' => $deprecatedCourse['id']]);
        $unitSuccessCount = 0;
        $unitExistCount = 0;
        $unitFailureCount = 0;

        $log->debug(sprintf('%d units found in course', $selectOldUnitsQuery->rowCount()));
        foreach ($selectOldUnitsQuery as $deprecatedUnit) {
            $log->info(sprintf('Converting unit "%s" (id: %d)...', $deprecatedUnit['name'], $deprecatedUnit['id']));
            try {
                $insertNewUnitQuery->execute([
                    'folderId' => $deprecatedUnit['id'],
                    'courseId' => $courseId,
                    'deleted'  => $deprecatedUnit['deleted_at'],
                ]);
                $log->debug(sprintf('Unit "%s" converted', $deprecatedUnit['name']));
                $unitSuccessCount++;
                if(!$deprecatedUnit['deleted_at']){
                    $unitExistCount++;
                }
            } catch (PDOException $e) {
                $log->error(sprintf('Unit "%s" could not be converted!', $deprecatedUnit['name']), [$e]);
                $log->warning($e->getMessage());
                $unitFailureCount++;
            }
        }
        $log->info(sprintf('%d units converted, %d failed to convert', $unitSuccessCount, $unitFailureCount));

        if(!$deprecatedCourse['deleted_at'] && $unitExistCount == 0) {
            $log->info("No units are active, deleting the course");
            $deleteNewCourseQuery->execute(['courseId' =>$courseId]);
        }
    }
    $log->info(sprintf('%d courses converted, %d failed to convert', $successCount, $failureCount));
    exit($failureCount > 0 ? 1 : 0);
});
