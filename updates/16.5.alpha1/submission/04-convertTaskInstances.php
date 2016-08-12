<?php
/**
 * Convert task instances from old format to new format.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

use Psr\Log\LoggerInterface;
use Schoolbox\Mapper\Exception as MapperException;

require_once __DIR__ . '/../../../conf/core/require.php';
spl_autoload_call('\\Schoolbox\\Application\\Daemon');

$kernel = new Schoolbox\Application\Daemon\Kernel('Convert Task Instances', [
    'verbose' => [ false, false, false ],
    'errors'  => E_ALL,
]);

$kernel->run(function($di, $loop, LoggerInterface $log, $opts) {

    // Perform the task instance conversion in hand-crafted SQL.
    // This is because the task instance mapper needs to be fundamentally changed to work as part of assessments.

    $db = Database::getInstance();

    // Insert project and related information queries
    $insertProjectQuery = $db->prepare("
        INSERT INTO project (title, description, weight)
        VALUES (:title, '', :weight)
    ");
    $insertAssessmentQuery = $db->prepare("
        INSERT INTO assessment (project_id, title, description, publish_date, open_date, due_date, weight, sequence)
        VALUES (:projectId, :title, '', NULL, NULL, NULL, 0, 1)
    ");
    $insertProjectRelationQuery = $db->prepare("
        INSERT INTO project_course_unit_instance (project_id, course_unit_instance_id)
        VALUES (:projectId, :courseUnitInstanceId)
    ");

    // Insert task query - most fields are just copied from the task instance
    $insertTaskQuery = $db->prepare("
        INSERT INTO task (
            assessment_id,
            category_id,
            title,
            link,
            requires_confirmation,
            assess_id,
            box_id,
            user_id,
            `order`,
            created_at,
            deleted_at
        )
        SELECT
            :assessmentId,
            :categoryId,
            title,
            link,
            requires_confirmation,
            assess_id,
            box_id,
            user_id,
            `order`,
            created_at,
            deleted_at
        FROM task_instance
        WHERE id = :taskInstanceId
    ");

    // Update assessment type queries
    $updateDueWorkQuery = $db->prepare("UPDATE submission_box SET assessment_id = :assessmentId WHERE id = :boxId");
    $updateQuizQuery    = $db->prepare("UPDATE assess SET assessment_id = :assessmentId WHERE assess_id = :assessId");

    // Select tasks queries
    $selectTaskInstanceQuery = $db->prepare("
        SELECT 
            ti.id,
            ti.course_unit_instance_id,
            ti.title,
            ti.assess_id,
            ti.box_id,
            ci.category_id
        FROM task_instance ti
        INNER JOIN course_unit_instance ci ON ti.course_unit_instance_id = ci.id
    ");
    $selectQuizWeightQuery    = $db->prepare("SELECT weight FROM assess WHERE assess_id = :assessId");
    $selectDueWorkWeightQuery = $db->prepare("SELECT weight FROM submission_box WHERE id = :boxId");


    // Go
    $selectTaskInstanceQuery->execute();
    $log->debug(sprintf('%d task instances found', $selectTaskInstanceQuery->rowCount()));
    $successCount = 0;
    $failureCount = 0;
    foreach ($selectTaskInstanceQuery->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $taskId   = $row['id'];
        $unitId   = $row['course_unit_instance_id'];
        $folderId = $row['category_id'];
        $title    = $row['title'];
        $quizId   = $row['assess_id'];
        $boxId    = $row['box_id'];

        // Convert this task
        $log->info(sprintf('Converting task instance "%s" (id: %d)...', $title, $taskId));

        try {
            $db->beginTransaction();

            // Figure out project's weight
            $weight = 0;
            if ($quizId) {
                $selectQuizWeightQuery->execute(['assessId' => $quizId]);
                $weight = $selectQuizWeightQuery->fetchColumn();
            } elseif ($boxId) {
                $selectDueWorkWeightQuery->execute(['boxId' => $boxId]);
                $weight = $selectDueWorkWeightQuery->fetchColumn();
            }

            // Create project
            $insertProjectQuery->execute(['title' => $title, 'weight' => intval($weight)]);
            $projectId = $db->lastInsertId();
            $log->debug('Created project');

            // Associate this project with the task's folder
            $insertProjectRelationQuery->execute(['projectId' => $projectId, 'courseUnitInstanceId' => $unitId]);
            $log->debug('Created project/course instance relation');

            // Create assessment
            $insertAssessmentQuery->execute(['projectId' => $projectId, 'title' => $title]);
            $assessmentId = $db->lastInsertId();
            $log->debug('Created assessment/project relation');

            // Create the task out of this assessment
            if ($quizId) {
                // Tasks with a quiz: associate quiz with assessment
                $updateQuizQuery->execute(['assessmentId' => $assessmentId, 'assessId' => $quizId]);
                $log->debug('Task instance has quiz - created quiz/assessment relation');
            }
            if ($boxId) {
                // Tasks with a due work: associate due work with assessment
                $updateDueWorkQuery->execute(['assessmentId' => $assessmentId, 'boxId' => $boxId]);
                $log->debug('Task instance has due work - created due work/assessment relation');
            }
            if (!$quizId && !$boxId) {
                // Copy the task directly
                $insertTaskQuery->execute([
                    'assessmentId'   => $assessmentId,
                    'categoryId'     => $folderId,
                    'taskInstanceId' => $taskId,
                ]);
                $log->debug('Task instance has no quiz or due work - copied task instance to task');
            }

            $successCount++;
            $db->commit();

        } catch (PDOException $e) {
            $log->error(sprintf('Task instance "%s" could not be converted!', $title), ['message' => $e->getMessage()]);
            $failureCount++;
            $db->rollBack();

        }
    }

    $log->info(sprintf('%d tasks converted, %d failed to convert', $successCount, $failureCount));
});
