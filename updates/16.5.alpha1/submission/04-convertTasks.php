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

    // Insert assessment and related information queries
    $insertAssessmentQuery = $db->prepare("
        INSERT INTO assessment (
            title,
            description,
            publish_date,
            open_date,
            due_date,
            close_date,
            weight,
            sequence,
            created_at,
            updated_at,
            deleted_at
        )
        VALUES (:title, '', NULL, NULL, NULL, NULL, :weight, 1, :created, :updated, :deleted)
    ");
    $insertAssessmentRelationQuery = $db->prepare("
        INSERT INTO assessment_course_unit_specification (assessment_id, course_unit_specification_id)
        VALUES (:assessmentId, (SELECT id FROM course_unit_specification WHERE folder_id = :folderId LIMIT 1))
    ");

    // Update assessment type queries
    // (Tasks which are related to a due work or quiz get deleted - the quiz gets related directly to the assessment)
    $updateTaskQuery    = $db->prepare("UPDATE task SET assessment_id = :assessmentId WHERE id = :taskId");
    $updateDueWorkQuery = $db->prepare("UPDATE submission_box SET assessment_id = :assessmentId WHERE id = :boxId");
    $updateQuizQuery    = $db->prepare("UPDATE assess SET assessment_id = :assessmentId WHERE assess_id = :assessId");
    $deleteTaskQuery    = $db->prepare("UPDATE task SET deleted_at = NOW() WHERE id = :taskId");

    // Select queries
    $selectTaskQuery = $db->prepare("
        SELECT 
            t.id,
            t.category_id,
            t.title,
            `as`.assess_id,
            sb.id AS box_id,
            t.created_at,
            t.deleted_at
        FROM task t
        LEFT JOIN submission_box sb ON t.id = sb.task_id
        LEFT JOIN assess `as` ON t.id = `as`.task_id
        WHERE COALESCE(t.assessment_id, 0) = 0
    ");
    $selectQuizWeightQuery    = $db->prepare("SELECT weight FROM assess WHERE assess_id = :assessId");
    $selectDueWorkWeightQuery = $db->prepare("SELECT weight FROM submission_box WHERE id = :boxId");

    // Add missing course and unit specifications
    // This is required if a course builder is deleted.
    $insertNewCourseQuery = $db->prepare("
        INSERT INTO course_specification (
            folder_id,
            created_at,
            updated_at,
            deleted_at
        ) VALUES (
            (SELECT parent FROM folder WHERE id = :folderId),
            NOW(),
            NOW(),
            NOW()
        )
    ");
    $selectCourseIdQuery = $db->prepare("
        SELECT id
        FROM course_specification
        WHERE folder_id = (SELECT parent FROM folder WHERE id = :folderId)
    ");
    $insertNewUnitQuery = $db->prepare("
        INSERT INTO course_unit_specification (
            folder_id,
            course_specification_id,
            created_at,
            updated_at,
            deleted_at
        ) VALUES (
            :folderId,
            :courseId,
            NOW(),
            NOW(),
            NOW()
        )
    ");


    // Go
    $selectTaskQuery->execute();
    $log->debug(sprintf('%d tasks found', $selectTaskQuery->rowCount()));
    $successCount = 0;
    $warningCount = 0;
    $failureCount = 0;
    foreach ($selectTaskQuery->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $taskId   = $row['id'];
        $folderId = $row['category_id'];
        $title    = $row['title'];
        $quizId   = $row['assess_id'];
        $boxId    = $row['box_id'];
        $created  = $row['created_at'];
        $deleted  = $row['deleted_at'];

        // Convert this task
        $log->info(sprintf('Converting task "%s" (id: %d)...', $title, $taskId));

        try {
            $db->beginTransaction();

            // Figure out assessment's weight
            $weight = 0;
            if ($quizId) {
                $selectQuizWeightQuery->execute(['assessId' => $quizId]);
                $weight = $selectQuizWeightQuery->fetchColumn();
            } elseif ($boxId) {
                $selectDueWorkWeightQuery->execute(['boxId' => $boxId]);
                $weight = $selectDueWorkWeightQuery->fetchColumn();
            }

            // Create assessment
            $insertAssessmentQuery->execute([
                'title'   => $title,
                'weight'  => intval($weight),
                'created' => $created,
                'updated' => $created,
                'deleted' => $deleted,
            ]);
            $assessmentId = $db->lastInsertId();
            $log->debug('Created assessment');

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

            // Associate this assessment with the task's course spec
            try {
                $insertAssessmentRelationQuery->execute(['assessmentId' => $assessmentId, 'folderId' => $folderId]);
                $log->debug('Created assessment/unit specification relation');
            } catch (PDOException $e) {
                // If this failed - most likely there WAS a course builder here, but there is no longer.
                // I won't count this as a warning yet.
                $log->info('Could not create assessment/unit specification relation.');
                $log->info('This is probably because the course builder that was here was deleted.');
                $log->info('Attempting to recreate the relation.');

                // Try to rebuild the course/unit that was here, but create it as deleted.
                $selectCourseIdQuery->execute(['folderId' => $folderId]);
                $courseId = $selectCourseIdQuery->fetchColumn();

                try {
                    if (!$courseId) {
                        $log->debug('Course specification does not exist - attempting to create it');
                        $insertNewCourseQuery->execute(['folderId' => $folderId]);
                        $courseId = $db->lastInsertId();
                    } else {
                        $log->debug('Course specification already exists');
                    }

                    $log->debug('Attempting to create unit specification');
                    $insertNewUnitQuery->execute(['folderId' => $folderId, 'courseId' => $courseId]);

                    $log->debug('Attempting to create assessment/unit specification relation again');
                    $insertAssessmentRelationQuery->execute(['assessmentId' => $assessmentId, 'folderId' => $folderId]);

                } catch (PDOException $e) {
                    $log->warning('Could not recreate assessment/unit specification relation!');
                    $log->warning('This is probably because either the course or unit specification folder was deleted.');
                    $log->warning('This assessment will be orphaned (it likely was already).');
                    $log->warning($e->getMessage());
                    $warningCount++;
                }
            }

            $successCount++;
            $db->commit();

        } catch (PDOException $e) {
            $log->error(sprintf('Task "%s" could not be converted!', $title), [$e]);
            $log->warning($e->getMessage());
            $failureCount++;
            $db->rollBack();

        }
    }

    $log->info(sprintf('%d tasks converted (%d warnings), %d failed to convert', $successCount, $warningCount, $failureCount));
    exit($failureCount > 0 ? 1 : 0);
});
