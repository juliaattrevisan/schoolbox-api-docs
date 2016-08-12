<?php
/**
 * Convert tasks from old format to new format.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

use Psr\Log\LoggerInterface;
use Schoolbox\Mapper\Exception as MapperException;

require_once __DIR__ . '/../../../conf/core/require.php';
spl_autoload_call('\\Schoolbox\\Application\\Daemon');

$kernel = new Schoolbox\Application\Daemon\Kernel('Convert Tasks', [
    'verbose' => [ false, false, false ],
    'errors'  => E_ALL,
]);

$kernel->run(function($di, $loop, LoggerInterface $log, $opts) {

    // Perform the task conversion in hand-crafted SQL.
    // This is because the task mapper needs to be fundamentally changed to work as part of assessments.

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
        INSERT INTO project_course_unit (project_id, course_unit_id)
        VALUES (:projectId, (SELECT id FROM course_unit WHERE folder_id = :folderId LIMIT 1))
    ");

    // Update assessment type queries
    // (Tasks which are related to a due work or quiz get deleted - the quiz gets related directly to the assessment)
    $updateTaskQuery    = $db->prepare("UPDATE task SET assessment_id = :assessmentId WHERE id = :taskId");
    $updateDueWorkQuery = $db->prepare("UPDATE submission_box SET assessment_id = :assessmentId WHERE id = :boxId");
    $updateQuizQuery    = $db->prepare("UPDATE assess SET assessment_id = :assessmentId WHERE assess_id = :assessId");
    $deleteTaskQuery    = $db->prepare("UPDATE task SET deleted_at = NOW() WHERE id = :taskId");

    // Select queries
    $selectTaskQuery = $db->prepare("
        SELECT id, category_id, title, assess_id, box_id 
        FROM task
        WHERE COALESCE(assessment_id, 0) = 0
    ");
    $selectQuizWeightQuery    = $db->prepare("SELECT weight FROM assess WHERE assess_id = :assessId");
    $selectDueWorkWeightQuery = $db->prepare("SELECT weight FROM submission_box WHERE id = :boxId");


    // Go
    $selectTaskQuery->execute();
    $log->debug(sprintf('%d tasks found', $selectTaskQuery->rowCount()));
    $successCount = 0;
    $failureCount = 0;
    foreach ($selectTaskQuery->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $taskId   = $row['id'];
        $folderId = $row['category_id'];
        $title    = $row['title'];
        $quizId   = $row['assess_id'];
        $boxId    = $row['box_id'];

        // Convert this task
        $log->info(sprintf('Converting task "%s" (id: %d)...', $title, $taskId));

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
            $insertProjectRelationQuery->execute(['projectId' => $projectId, 'folderId' => $folderId]);
            $log->debug('Created project/folder relation');

            // Create assessment
            $insertAssessmentQuery->execute(['projectId' => $projectId, 'title' => $title]);
            $assessmentId = $db->lastInsertId();
            $log->debug('Created assessment/project relation');

            // Associate this task with the assessment
            $updateTaskQuery->execute(['assessmentId' => $assessmentId, 'taskId' => $taskId]);
            $log->debug('Created task/assessment relation');

            if ($quizId) {
                // Tasks with a quiz: delete task, and associate quiz with assessment
                $updateQuizQuery->execute(['assessmentId' => $assessmentId, 'assessId' => $quizId]);
                $deleteTaskQuery->execute(['taskId' => $taskId]);
                $log->debug('Task has quiz - created quiz/assessment relation, deleted task');
            }
            if ($boxId) {
                // Tasks with a due work: delete task, and associate due work with assessment
                $updateDueWorkQuery->execute(['assessmentId' => $assessmentId, 'boxId' => $boxId]);
                $deleteTaskQuery->execute(['taskId' => $taskId]);
                $log->debug('Task has due work - created due work/assessment relation, deleted task');
            }

            $successCount++;
            $db->commit();

        } catch (PDOException $e) {
            $log->warning($e->getMessage());
            $log->error(sprintf('Task "%s" could not be converted!', $title), [$e]);
            $failureCount++;
            $db->rollBack();

        }
    }

    $log->info(sprintf('%d tasks converted, %d failed to convert', $successCount, $failureCount));
});
