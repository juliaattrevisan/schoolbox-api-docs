<?php
/**
 * Last Chance Saloon for orphaned assessments.
 *
 * Quizzes, due work and tasks that do not have an assessment get one here.
 * This assessment will not be linked to any folder, unit spec or instance
 * - it is in every sense an orphan.
 *
 * This exists because the script which adds assessment foreign keys needs
 * every assessment subtype to be associated with an assessment ID.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

use Psr\Log\LoggerInterface;
use Schoolbox\Mapper\Exception as MapperException;

require_once __DIR__ . '/../../../conf/core/require.php';
spl_autoload_call('\\Schoolbox\\Application\\Daemon');

$kernel = new Schoolbox\Application\Daemon\Kernel('Rescue Orphaned Assessments', [
    'verbose' => [ false, false, false ],
    'errors'  => E_ALL,
]);

$kernel->run(function($di, $loop, LoggerInterface $log, $opts) {

    $db = Database::getInstance();

    // Insert orphaned assessment query
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
        VALUES (:title, :description, :publish, :open, :due, :close, :weight, 1, :created, :updated, :deleted)
    ");

    // Update assessment type queries
    $updateTaskQuery    = $db->prepare("UPDATE task SET assessment_id = :assessmentId WHERE id = :taskId");
    $updateDueWorkQuery = $db->prepare("UPDATE submission_box SET assessment_id = :assessmentId WHERE id = :boxId");
    $updateQuizQuery    = $db->prepare("UPDATE assess SET assessment_id = :assessmentId WHERE assess_id = :assessId");

    // Select assessment type queries
    $selectTasksQuery = $db->prepare("
        SELECT
            id AS task_id,
            NULL AS box_id,
            NULL AS quiz_id,
            category_id AS folder_id,
            title,
            '' AS description,
            0 AS weight,
            NULL AS publish_date,
            NULL AS open_date,
            NULL AS due_date,
            NULL AS late_date,
            created_at,
            deleted_at
        FROM  task
        WHERE COALESCE(assessment_id, 0) = 0
        ORDER BY id ASC
    ");
    $selectDueWorksQuery = $db->prepare("
        SELECT
            NULL AS task_id,
            id AS box_id,
            NULL AS quiz_id,
            category AS folder_id,
            name AS title,
            description,
            weight,
            publish_date,
            open_date,
            date AS due_date,
            late_date AS close_date,
            created AS created_at,
            IF (deleted IS TRUE, created, NULL) AS deleted_at
        FROM  submission_box
        WHERE COALESCE(assessment_id, 0) = 0
        ORDER BY id ASC
    ");
    $selectQuizzesQuery = $db->prepare("
        SELECT
            NULL AS task_id,
            NULL AS box_id,
            assess_id AS quiz_id,
            category_id AS folder_id,
            title,
            description,
            weight,
            start AS publish_date,
            start AS open_date,
            finish AS due_date,
            finish AS close_date,
            created_at,
            deleted_at
        FROM  assess
        WHERE COALESCE(assessment_id, 0) = 0
        AND   availability = 'assess'
        ORDER BY assess_id ASC
    ");


    // Go
    $selectTasksQuery->execute();
    $selectDueWorksQuery->execute();
    $selectQuizzesQuery->execute();
    $log->debug(
        sprintf(
            '%d orphaned assessments found (%d tasks, %d due works, %d quizzes)',
            ($selectQuizzesQuery->rowCount() + $selectDueWorksQuery->rowCount()),
            $selectTasksQuery->rowCount(),
            $selectDueWorksQuery->rowCount(),
            $selectQuizzesQuery->rowCount()
        )
    );
    $successCount = 0;
    $failureCount = 0;
    $rows = array_merge(
        $selectTasksQuery->fetchAll(PDO::FETCH_ASSOC),
        $selectDueWorksQuery->fetchAll(PDO::FETCH_ASSOC),
        $selectQuizzesQuery->fetchAll(PDO::FETCH_ASSOC)
    );
    foreach ($rows as $row) {
        $taskId      = $row['task_id'];
        $quizId      = $row['quiz_id'];
        $boxId       = $row['box_id'];
        $title       = $row['title'] ?: '';
        $description = $row['description'];
        $weight      = $row['weight'];
        $publishDate = $row['publish_date'];
        $openDate    = $row['open_date'];
        $dueDate     = $row['due_date'];
        $closeDate   = $row['close_date'];
        $created     = $row['created_at'];
        $deleted     = $row['deleted_at'];

        // What type of assessable is this?
        if ($taskId) {
            $log->info(sprintf('Rescuing task "%s" (id: %d)...', $title, $taskId));
        } elseif ($quizId) {
            $log->info(sprintf('Rescuing quiz "%s" (id: %d)...', $title, $quizId));
        } elseif ($boxId) {
            $log->info(sprintf('Rescuing due work "%s" (id: %d)...', $title, $boxId));
        } else {
            $log->warning('Assessment is neither a task, quiz nor due work - not sure how that happened but it did!');
            continue;
        }

        // Convert this task
        try {
            $db->beginTransaction();

            // Create assessment
            $insertAssessmentQuery->execute([
                'title'       => $title,
                'description' => $description,
                'weight'      => intval($weight),
                'publish'     => $publishDate,
                'open'        => $openDate,
                'due'         => $dueDate,
                'close'       => $closeDate,
                'created'     => $created,
                'updated'     => $created,
                'deleted'     => $deleted,
            ]);
            $assessmentId = $db->lastInsertId();
            $log->debug('Created orphan assessment');

            // Update the orphaned assessment sub-object
            if ($taskId) {
                $updateTaskQuery->execute(['assessmentId' => $assessmentId, 'taskId' => $taskId]);
                $log->debug('Created task/assessment relation');
            } elseif ($quizId) {
                $updateQuizQuery->execute(['assessmentId' => $assessmentId, 'assessId' => $quizId]);
                $log->debug('Created quiz/assessment relation');
            } elseif ($boxId) {
                $updateDueWorkQuery->execute(['assessmentId' => $assessmentId, 'boxId' => $boxId]);
                $log->debug('Created due work/assessment relation');
            }

            $successCount++;
            $db->commit();

        } catch (PDOException $e) {
            $log->error(sprintf('%s "%s" could not be rescued!', ($quizId ? 'Quiz' : 'Due Work'), $title), [$e]);
            $log->warning($e->getMessage());
            $failureCount++;
            $db->rollBack();

        }
    }

    $log->info(sprintf('%d orphaned assessments rescued, %d unrescuable', $successCount, $failureCount));
    exit($failureCount > 0 ? 1 : 0);
});
