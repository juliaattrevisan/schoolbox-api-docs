<?php
/**
 * Convert courses from old (parents of folders with course builders) to new (course table rows).
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

use Psr\Log\LoggerInterface;
use Schoolbox\Mapper\Exception as MapperException;

require_once __DIR__ . '/../../../conf/core/require.php';
spl_autoload_call('\\Schoolbox\\Application\\Daemon');

$kernel = new Schoolbox\Application\Daemon\Kernel('Create Course Instances', [
    'verbose' => [ false, false, false ],
    'errors'  => E_ALL,
]);

$kernel->run(function($di, $loop, LoggerInterface $log, $opts) {

    // Convert courses
    $successCount = 0;
    $failureCount = 0;

    $database = Database::getInstance();

    // Find unit instances
    $selectUnitInstancesQuery = $database->prepare("
        SELECT DISTINCT
            cui.category_id,
            cui.component_id,
            cu.course_id
        FROM course_unit_instance cui
        INNER JOIN course_unit cu ON cui.unit_category_id = cu.folder_id
        WHERE cui.deleted_at IS NULL
        ORDER BY cui.component_id
    ");

//    // Insert new courses and units
    $insertCourseInstanceQuery = $database->prepare("
        INSERT INTO course_instance (folder_id, course_id) 
        VALUES (:folderId, :courseId)
    ");
    $updateUnitInstanceQuery   = $database->prepare("
        UPDATE course_unit_instance 
        SET    course_instance_id = :courseInstanceId 
        WHERE  component_id = :componentId
        AND    category_id = :folderId
    ");
    $updateComponentConfigurationQuery = $database->prepare("
        INSERT INTO component_config_instance (
            component_config_definition_id,
            component_instance_id,
            `value`
        ) VALUES (
            (SELECT ccd.id
             FROM component_config_definition ccd
             INNER JOIN component_definition cd ON ccd.component_definition_id = cd.id
             WHERE ccd.`key` = 'courseInstance'
             AND cd.class = 'Component_Homepage_CourseOutlineController'),
            :componentId,
            :courseInstanceId
        )
    ");

    // Go
    $selectUnitInstancesQuery->execute();

    $log->debug(sprintf('%d unit instances found', $selectUnitInstancesQuery->rowCount()));
    foreach ($selectUnitInstancesQuery as $unitInstance) {

        // Create a new course instance
        $log->info(sprintf('Creating course instance for component %d...', $unitInstance['component_id']));
        $database->beginTransaction();
        try {
            $insertCourseInstanceQuery->execute([
                'folderId' => $unitInstance['category_id'],
                'courseId' => $unitInstance['course_id'],
            ]);
            $log->debug('Course instance created');
            $courseInstanceId = $database->lastInsertId();
        } catch (MapperException $e) {
            $log->error('Course instance could not be created!');
            $failureCount++;
            $database->rollBack();
            continue;
        }


//        // Assign this course instance to each unit instance
//        $log->info('Attaching course instance to unit instances...');
//        try {
//            $updateUnitInstanceQuery->execute([
//                'folderId'         => $unitInstance['category_id'],
//                'courseInstanceId' => $courseInstanceId,
//            ]);
//            $log->debug('Course instance attached to unit instances');
//        } catch (MapperException $e) {
//            $log->error('Course instance could not be attached!');
//            $failureCount++;
//            $database->rollBack();
//            continue;
//        }


        // Set course instance on component configuration
        // (If this doesn't work, it's okay, I guess?)
        $log->info('Attaching course instance to component...');
        try {
            $updateComponentConfigurationQuery->execute([
                'componentId'      => $unitInstance['component_id'],
                'courseInstanceId' => $courseInstanceId,
            ]);
            $log->debug('Course instance attached to component');
        } catch (MapperException $e) {
            $log->error('Course instance could not be attached!');
            $failureCount++;
            $database->rollBack();
            continue;
        }


        // Success
        $successCount++;
        $database->commit();
    }
    $log->info(sprintf('%d unit instances converted, %d failed to convert', $successCount, $failureCount));
});
