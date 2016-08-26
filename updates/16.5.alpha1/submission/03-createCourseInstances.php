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
            cui.created_at,
            cui.deleted_at,
            cu.course_id,
            f.name AS folder_name,
            cf.name AS course_name
        FROM course_unit_instance cui
        INNER JOIN folder f ON cui.category_id = f.id
        INNER JOIN course_unit cu ON cui.unit_category_id = cu.folder_id
        INNER JOIN course c ON cu.course_id = c.id
        INNER JOIN folder cf ON c.folder_id = cf.id
        ORDER BY cui.component_id
    ");

    // Insert and update objects
    $insertCourseInstanceQuery = $database->prepare("
        INSERT INTO course_instance (folder_id, course_id, created_at, deleted_at)
        VALUES (:folderId, :courseId, :created, :deleted)
    ");
    $updateUnitInstanceQuery = $database->prepare("
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
        $log->info(
            sprintf(
                'Creating course instance for course "%s" (#%d) on folder "%s" (#%d) (Component: #%d)',
                $unitInstance['course_name'],
                $unitInstance['course_id'],
                $unitInstance['folder_name'],
                $unitInstance['category_id'],
                $unitInstance['component_id']
            )
        );
        $database->beginTransaction();
        try {
            $insertCourseInstanceQuery->execute([
                'folderId' => $unitInstance['category_id'],
                'courseId' => $unitInstance['course_id'],
                'created'  => $unitInstance['created_at'],
                'deleted'  => $unitInstance['deleted_at'],
            ]);
            $log->debug('Course instance created');
            $courseInstanceId = $database->lastInsertId();
        } catch (PDOException $e) {
            $log->error('Course instance could not be created!', [$e]);
            $log->warning($e->getMessage());
            $failureCount++;
            $database->rollBack();
            continue;
        }


        // Assign this course instance to each unit instance
        $log->info('Attaching course instance to unit instances...');
        try {
            $updateUnitInstanceQuery->execute([
                'folderId'         => $unitInstance['category_id'],
                'componentId'      => $unitInstance['component_id'],
                'courseInstanceId' => $courseInstanceId,
            ]);
            $log->debug('Course instance attached to unit instances');
        } catch (PDOException $e) {
            $log->error('Course instance could not be attached!', [$e]);
            $log->warning($e->getMessage());
            $failureCount++;
            $database->rollBack();
            continue;
        }


        // Set course instance on component configuration
        // (If this doesn't work, it's okay, I guess? The course instance was still created.)
        $log->info('Attaching course instance to component...');
        try {
            $updateComponentConfigurationQuery->execute([
                'componentId'      => $unitInstance['component_id'],
                'courseInstanceId' => $courseInstanceId,
            ]);
            $log->debug('Course instance attached to component');
        } catch (PDOException $e) {
            $log->error('Course instance could not be attached!', [$e]);
            $log->error('What probably happened is that a single component somehow has unit instances that relate to more than one course.');
            $log->error('The course instance has still been created, so its coursework still exists on this homepage.');
            $log->warning($e->getMessage());
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
