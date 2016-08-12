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
        VALUES (:projectId, :title, '', :publish, :open, :due, :weight, 1)
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
    $selectQuizQuery       = $db->prepare("SELECT weight, start, finish FROM assess WHERE assess_id = :assessId");
    $selectDueWorkQuery    = $db->prepare("SELECT weight, publish_date, open_date, date FROM submission_box WHERE id = :boxId");
    $selectCourseUnitQuery = $db->prepare("SELECT open_date, due_date FROM course_unit_instance WHERE id = :courseUnitInstanceId");


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

            // Figure out project's weight, publish/open/due date
            $weight      = 0;
            $publishDate = null;
            $openDate    = null;
            $dueDate     = null;
            if ($quizId) {
                // Use weight and dates from quiz
                $selectQuizQuery->execute(['assessId' => $quizId]);
                $quizRow = $selectQuizQuery->fetch(PDO::FETCH_ASSOC);
                if ($quizRow) {
                    $weight      = $quizRow['weight'];
                    $publishDate = $quizRow['start'];
                    $openDate    = $quizRow['start'];
                    $dueDate     = $quizRow['finish'];
                }
            } elseif ($boxId) {
                // Use weight and dates from due work
                $selectDueWorkQuery->execute(['boxId' => $boxId]);
                $boxRow = $selectDueWorkQuery->fetch(PDO::FETCH_ASSOC);
                if ($boxRow) {
                    $weight      = $boxRow['weight'];
                    $publishDate = $boxRow['publish_date'];
                    $openDate    = $boxRow['open_date'];
                    $dueDate     = $boxRow['date'];
                }
            } else {
                // Use dates from the course unit instance
                $selectCourseUnitQuery->execute(['courseUnitInstanceId' => $unitId]);
                $courseUnitRow = $selectCourseUnitQuery->fetch(PDO::FETCH_ASSOC);
                if ($courseUnitRow) {
                    $publishDate = $courseUnitRow['open_date'];
                    $openDate    = $courseUnitRow['open_date'];
                    $dueDate     = $courseUnitRow['due_date'];
                }
            }

            // Create project
            $insertProjectQuery->execute([
                'title'   => $title,
                'weight'  => intval($weight),
            ]);
            $projectId = $db->lastInsertId();
            $log->debug('Created project');

            // Associate this project with the task's folder
            $insertProjectRelationQuery->execute(['projectId' => $projectId, 'courseUnitInstanceId' => $unitId]);
            $log->debug('Created project/course instance relation');

            // Create assessment
            $insertAssessmentQuery->execute([
                'projectId' => $projectId,
                'title'     => $title,
                'weight'    => intval($weight),
                'publish'   => $publishDate,
                'open'      => $openDate,
                'due'       => $dueDate,
            ]);
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
            $log->warning($e->getMessage());
            $log->error(sprintf('Task instance "%s" could not be converted!', $title), [$e]);
            $failureCount++;
            $db->rollBack();

        }
    }

    $log->info(sprintf('%d tasks converted, %d failed to convert', $successCount, $failureCount));
});
