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

    // Insert assessment and related information queries
    // (Close date is the same as due date)
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
        VALUES (:title, :description, :publish, :open, :due, :close, :weight, :sequence, :created, :updated, :deleted)
    ");
    $insertAssessmentRelationQuery = $db->prepare("
        INSERT INTO assessment_course_unit_implementation (assessment_id, course_unit_implementation_id)
        VALUES (:assessmentId, :courseUnitInstanceId)
    ");

    // Insert relation between spec and implementation
    $insertAssessmentImplementationRelationQuery = $db->prepare("
        INSERT INTO assessment_specification_implementation (
            specification_assessment_id,
            implementation_assessment_id
        )
        SELECT
            task.assessment_id,
            :assessmentId
        FROM task_instance
        INNER JOIN task ON task_instance.task_id = task.id
        WHERE task_instance.id = :taskInstanceId
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
    // Use IDs from due work and quiz tables, because the task instance's quiz/duework IDs
    // can actually disappear, even though they have a duework/quiz.
    // I think this occurs when the duework/quiz is deleted, but the task instance isn't?
    $selectTaskInstanceQuery = $db->prepare("
        SELECT 
            ti.id,
            ti.course_unit_instance_id,
            ti.title,
            COALESCE(`as`.description, sb.description, '') AS description,
            ti.order AS sequence,
            `as`.assess_id,
            sb.id AS box_id,
            ti.created_at,
            ti.deleted_at,
            ci.category_id
        FROM task_instance ti
        INNER JOIN course_unit_implementation ci ON ti.course_unit_instance_id = ci.id
        LEFT JOIN submission_box sb ON ti.id = sb.task_instance_id
        LEFT JOIN assess `as` ON ti.id = `as`.task_instance_id
        ORDER BY ti.id ASC
    ");
    $selectQuizQuery               = $db->prepare("SELECT weight, start, finish FROM assess WHERE assess_id = :assessId");
    $selectDueWorkQuery            = $db->prepare("SELECT weight, publish_date, open_date, date, late_date FROM submission_box WHERE id = :boxId");
    $selectCourseUnitInstanceQuery = $db->prepare("SELECT open_date, due_date FROM course_unit_implementation WHERE id = :courseUnitInstanceId");

    // Update task completions
    $updateTaskCompletionQuery     = $db->prepare("UPDATE task_instance_completion SET task_id = :taskId WHERE task_instance_id = :instanceId");

    // Go
    $selectTaskInstanceQuery->execute();
    $log->debug(sprintf('%d task instances found', $selectTaskInstanceQuery->rowCount()));
    $successCount = 0;
    $warningCount = 0;
    $failureCount = 0;
    foreach ($selectTaskInstanceQuery->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $taskId               = $row['id'];
        $courseUnitInstanceId = $row['course_unit_instance_id'];
        $folderId             = $row['category_id'];
        $title                = $row['title'];
        $description          = $row['description'];
        $sequence             = $row['sequence'];
        $quizId               = $row['assess_id'];
        $boxId                = $row['box_id'];
        $created              = $row['created_at'];
        $deleted              = $row['deleted_at'];

        // Convert this task
        $log->info(sprintf('Converting task instance "%s" (id: %d)...', $title, $taskId));

        try {
            $db->beginTransaction();

            // Figure out assessment's weight, publish/open/due date
            $weight      = 0;
            $publishDate = null;
            $openDate    = null;
            $dueDate     = null;
            $closeDate   = null;
            if ($quizId) {
                // Use weight and dates from quiz
                $selectQuizQuery->execute(['assessId' => $quizId]);
                $quizRow = $selectQuizQuery->fetch(PDO::FETCH_ASSOC);
                if ($quizRow) {
                    $weight      = $quizRow['weight'];
                    $publishDate = $quizRow['start'];
                    $openDate    = $quizRow['start'];
                    $dueDate     = $quizRow['finish'];
                    $closeDate   = $quizRow['finish'];
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
                    $closeDate   = $boxRow['late_date'];
                }
            } else {
                // Use dates from the course unit instance
                $selectCourseUnitInstanceQuery->execute(['courseUnitInstanceId' => $courseUnitInstanceId]);
                $courseUnitInstanceRow = $selectCourseUnitInstanceQuery->fetch(PDO::FETCH_ASSOC);
                if ($courseUnitInstanceRow) {
                    $publishDate = $courseUnitInstanceRow['open_date'];
                    $openDate    = $courseUnitInstanceRow['open_date'];
                    $dueDate     = $courseUnitInstanceRow['due_date'];
                    $closeDate   = $courseUnitInstanceRow['due_date'];
                }
            }

            // Create assessment
            $insertAssessmentQuery->execute([
                'title'       => $title,
                'description' => $description,
                'weight'      => intval($weight),
                'sequence'    => intval($sequence),
                'publish'     => $publishDate,
                'open'        => $openDate,
                'due'         => $dueDate,
                'close'       => $closeDate,
                'created'     => $created,
                'updated'     => $created,
                'deleted'     => $deleted,
            ]);
            $assessmentId = $db->lastInsertId();
            $log->debug('Created assessment');

            // Associate this assessment with the task's folder
            $insertAssessmentRelationQuery->execute(['assessmentId' => $assessmentId, 'courseUnitInstanceId' => $courseUnitInstanceId]);
            $log->debug('Created assessment/course implementation relation');

            // Add a relation between assessment spec and implementation
            try {
                $params = [
                    'assessmentId'   => $assessmentId,
                    'taskInstanceId' => $taskId,
                ];
                $log->debug(print_r($params, true));
                $insertAssessmentImplementationRelationQuery->execute($params);

                if ($insertAssessmentImplementationRelationQuery->rowCount() > 0) {
                    $log->debug('Created implementation/specification assessment relation');

                } else {
                    $log->warning('No implementation/specification assessment relation was created!');
                }

            } catch (PDOException $e) {
                $log->warning('Could not create implementation/specification assessment relation!');
                $log->warning('This is probably because this implementation of an assessment has no specification.');
                $log->warning('This is probably okay, because an assessment to be added to an implementation separate from its specification.');
                $log->warning($e->getMessage());
                $warningCount++;
            }

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

                $newTaskId = $db->lastInsertId();

                $updateTaskCompletionQuery->execute([ 'taskId' => $newTaskId, 'instanceId' => $taskId ]);
                $log->debug('Bound task completions to new task');
            }

            $successCount++;
            $db->commit();

        } catch (PDOException $e) {
            $log->error(sprintf('Task instance "%s" could not be converted!', $title), [$e]);
            $log->warning($e->getMessage());
            $failureCount++;
            $db->rollBack();

        }
    }

    $log->info(sprintf('%d task instances converted (%d warnings), %d failed to convert', $successCount, $warningCount, $failureCount));
    exit($failureCount > 0 ? 1 : 0);
});
