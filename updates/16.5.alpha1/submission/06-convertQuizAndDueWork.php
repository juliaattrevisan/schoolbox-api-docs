<?php
/**
 * Convert quiz and due work from old format to new format.
 *
 * This only converts quizzes or due works that aren't part of a Unit Specification
 * or Unit Implementation - they have already been converted in previous steps.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

use Psr\Log\LoggerInterface;
use Schoolbox\Mapper\Exception as MapperException;

require_once __DIR__ . '/../../../conf/core/require.php';
spl_autoload_call('\\Schoolbox\\Application\\Daemon');

$kernel = new Schoolbox\Application\Daemon\Kernel('Convert Quiz and Due Work', [
    'verbose' => [ false, false, false ],
    'errors'  => E_ALL,
]);

$kernel->run(function($di, $loop, LoggerInterface $log, $opts) {

    // Perform the task instance conversion in hand-crafted SQL.
    // This is because the task instance mapper needs to be fundamentally changed to work as part of assessments.

    $db = Database::getInstance();

    // Insert folder and related information queries
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
        VALUES (:title, '', :publish, :open, :due, :close, :weight, 1, :created, :updated, :deleted)
    ");
    $insertAssessmentFolderRelationQuery = $db->prepare("
        INSERT INTO assessment_folder (assessment_id, folder_id)
        VALUES (:assessmentId, :folderId)
    ");

    // Update assessment type queries
    $updateDueWorkQuery = $db->prepare("UPDATE submission_box SET assessment_id = :assessmentId WHERE id = :boxId");
    $updateQuizQuery    = $db->prepare("UPDATE assess SET assessment_id = :assessmentId WHERE assess_id = :assessId");

    // Select quiz/duework queries
    $selectQuizzesQuery = $db->prepare("
        SELECT
            NULL AS box_id,
            assess_id AS quiz_id,
            category_id AS folder_id,
            title,
            weight,
            start AS publish_date,
            start AS open_date,
            finish AS due_date,
            finish AS close_date,
            created_at,
            deleted_at
        FROM  assess
        WHERE task_id IS NULL
        AND   task_instance_id IS NULL
        AND   COALESCE(assessment_id, 0) = 0
        AND   availability = 'assess'
        ORDER BY assess_id ASC
    ");
    $selectDueWorksQuery = $db->prepare("
        SELECT
            id AS box_id,
            NULL AS quiz_id,
            category AS folder_id,
            name AS title,
            weight,
            publish_date,
            open_date,
            date AS due_date,
            late_date AS close_date,
            created AS created_at,
            IF (deleted IS TRUE, created, NULL) AS deleted_at
        FROM  submission_box
        WHERE task_id IS NULL
        AND   task_instance_id IS NULL
        AND   COALESCE(assessment_id, 0) = 0
        ORDER BY id ASC
    ");


    // Go
    $selectQuizzesQuery->execute();
    $selectDueWorksQuery->execute();
    $log->debug(
        sprintf(
            '%d assessments found (%d due works, %d quizzes)',
            ($selectQuizzesQuery->rowCount() + $selectDueWorksQuery->rowCount()),
            $selectDueWorksQuery->rowCount(),
            $selectQuizzesQuery->rowCount()
        )
    );
    $successCount = 0;
    $failureCount = 0;
    $rows = array_merge($selectDueWorksQuery->fetchAll(PDO::FETCH_ASSOC), $selectQuizzesQuery->fetchAll(PDO::FETCH_ASSOC));
    foreach ($rows as $row) {
        $quizId      = $row['quiz_id'];
        $boxId       = $row['box_id'];
        $folderId    = $row['folder_id'];
        $title       = $row['title'];
        $weight      = $row['weight'];
        $publishDate = $row['publish_date'];
        $openDate    = $row['open_date'];
        $dueDate     = $row['due_date'];
        $closeDate   = $row['close_date'];
        $created     = $row['created_at'];
        $deleted     = $row['deleted_at'];

        // What type of assessable is this?
        if ($quizId) {
            $log->info(sprintf('Converting quiz "%s" (id: %d)...', $title, $quizId));
        } elseif ($boxId) {
            $log->info(sprintf('Converting due work "%s" (id: %d)...', $title, $boxId));
        } else {
            $log->warning('Assessment is neither a quiz or due work - not sure how that happened but it did!');
            continue;
        }

        // Convert this task
        try {
            $db->beginTransaction();

            // Create assessment
            $insertAssessmentQuery->execute([
                'title'   => $title,
                'weight'  => intval($weight),
                'publish' => $publishDate,
                'open'    => $openDate,
                'due'     => $dueDate,
                'close'   => $closeDate,
                'created' => $created,
                'updated' => $created,
                'deleted' => $deleted,
            ]);
            $assessmentId = $db->lastInsertId();
            $log->debug('Created assessment');

            // Create the quiz or due work
            if ($quizId) {
                $updateQuizQuery->execute(['assessmentId' => $assessmentId, 'assessId' => $quizId]);
                $log->debug('Created quiz/assessment relation');
            } elseif ($boxId) {
                $updateDueWorkQuery->execute(['assessmentId' => $assessmentId, 'boxId' => $boxId]);
                $log->debug('Created due work/assessment relation');
            }

            // Associate this assessment with its folder
            try {
                $insertAssessmentFolderRelationQuery->execute(['assessmentId' => $assessmentId, 'folderId' => $folderId]);
                $log->debug('Created assessment/folder relation');
            } catch (PDOException $e) {
                $log->warning('Could not create assessment/folder relation!');
                $log->warning('This is probably because the folder in which this assessment lives has been deleted.');
                $log->warning('This assessment will be orphaned (it likely was already).');
                $log->warning($e->getMessage());
            }

            $successCount++;
            $db->commit();

        } catch (PDOException $e) {
            $log->error(sprintf('%s "%s" could not be converted!', ($quizId ? 'Quiz' : 'Due Work'), $title), [$e]);
            $log->warning($e->getMessage());
            $failureCount++;
            $db->rollBack();

        }
    }

    $log->info(sprintf('%d tasks converted, %d failed to convert', $successCount, $failureCount));
    exit($failureCount > 0 ? 1 : 0);
});
