<?php
/**
 * Convert tasks from old format to new format.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

use Psr\Log\LoggerInterface;
use Schoolbox\Assessment\Mapper as AssessmentMapper;
use Schoolbox\Assessment\Model as AssessmentModel;
use Schoolbox\Assessment\Course;
use Schoolbox\Assessment\Project;
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
        VALUES (:title, '', 0)
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

    // Select tasks queries
    $selectTaskQuery = $db->prepare("
        SELECT id, category_id, title, assess_id, box_id 
        FROM task
        WHERE COALESCE(assessment_id, 0) = 0
    ");


    // Go
    $selectTaskQuery->execute();
    $log->debug(sprintf('%d tasks found', $selectTaskQuery->rowCount()));
    foreach ($selectTaskQuery->fetchAll(PDO::FETCH_ASSOC) as $row) {

        // Convert this task
        $log->info(sprintf('Converting task "%s" (id: %d)...', $row['title'], $row['id']));

        // Create project
        $insertProjectQuery->execute(['title' => $row['title']]);
        $projectId = $db->lastInsertId();
        $log->debug('Created project');

        // Associate this project with the task's folder
        $insertProjectRelationQuery->execute(['projectId' => $projectId, 'folderId' => $row['folder_id']]);
        $log->debug('Created project/folder relation');

        // Create assessment
        $insertAssessmentQuery->execute(['projectId' => $projectId, 'title' => $row['title']]);
        $assessmentId = $db->lastInsertId();
        $log->debug('Created assessment/project relation');

        // Associate this task with the assessment
        $updateTaskQuery->execute(['assessmentId' => $assessmentId, 'taskId' => $row['id']]);
        $log->debug('Created task/assessment relation');

        if ($row['assess_id']) {
            // Tasks with a quiz: delete task, and associate quiz with assessment
            $updateQuizQuery->execute(['assessmentId' => $assessmentId, 'assessId' => $row['assess_id']]);
            $deleteTaskQuery->execute(['taskId' => $row['id']]);
            $log->debug('Task has quiz - created quiz/assessment relation, deleted task');
        }
        if ($row['box_id']) {
            // Tasks with a due work: delete task, and associate due work with assessment
            $updateDueWorkQuery->execute(['assessmentId' => $assessmentId, 'boxId' => $row['box_id']]);
            $deleteTaskQuery->execute(['taskId' => $row['id']]);
            $log->debug('Task has due work - created due work/assessment relation, deleted task');
        }
    }
});
