<?php

/**
 * We have an issue where assessments do not have a context (folder, project, unit-impl, or unit-spec)
 *
 * These assessments are orphans and need to be removed from the DB so we can ensure referential integrity
 *
 * We need to also delete all sub-entities linked to these orphaned assessments
 *
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

use Psr\Log\LoggerInterface;

require_once __DIR__ . '/../../../conf/core/require.php';
spl_autoload_call('\\Schoolbox\\Application\\Daemon');

$kernel = new Schoolbox\Application\Daemon\Kernel('Hard delete assessments linked to missing folders', [
    'verbose' => [false, false, false],
    'errors'  => E_ALL,
]);

$kernel->run(function ($di, $loop, LoggerInterface $log, $opts) {
    $deleter = new DeleteOrphanAssessments(Database::getInstance(), $log);
    $deleter->run();
});


/**
 * Encapsulates all the deletion logic to solve the scoping issue
 *
 * Will exit out with code=1 if any errors encountered during execution
 *
 */
class DeleteOrphanAssessments
{
    // Width of count column in log. Constant to vertically align and make log output easier to read.
    const WIDTH_COUNT = 7;

    /** @var Database */
    private $db;

    /** @var LoggerInterface */
    private $log;

    private $orphanAssessmentIds = [];
    private $orphanTaskIds       = [];
    private $orphanQuizIds       = [];
    private $orphanDueWorkIds    = [];

    private $countTablesDropped = 0;
    private $countTablesPurged  = 0;
    private $countRowsDeleted   = 0;


    /**
     * @param Database        $db
     * @param LoggerInterface $log
     */
    public function __construct(Database $db, LoggerInterface $log)
    {
        $this->db  = $db;
        $this->log = $log;
    }


    /**
     * Delete orphan assessments, and all their dependents, from leaf to root
     */
    public function run()
    {
        $this->fetchOrphanAssessmentIds();
        $this->deleteTasks($this->orphanTaskIds);
        $this->deleteQuizzes($this->orphanQuizIds);
        $this->deleteDueWork($this->orphanDueWorkIds);
        $this->deleteAssessments($this->orphanAssessmentIds);
        $this->dropDeprecatedTables();
        $this->printSummary();
        exit(0);
    }


    /**
     * Delete tasks and all their dependents
     *
     * @param int[] $taskIds
     */
    private function deleteTasks(array $taskIds)
    {
        $prefix             = 'task_id';
        $taskIdPlaceholders = $this->getPlaceHoldersString($taskIds, $prefix);
        $parameters         = $this->getArrayParameters($taskIds, $prefix);

        // Task Completions
        $statement = $this->db->prepare("
            DELETE FROM task_completion WHERE task_id IN ($taskIdPlaceholders)
        ");
        $this->executeDeleteQuery($statement, $parameters);


        // Tasks
        $statement = $this->db->prepare("
            DELETE FROM task WHERE id IN ($taskIdPlaceholders)
        ");
        $this->executeDeleteQuery($statement, $parameters);
    }


    /**
     * Delete quizzes and all their dependents
     *
     * @param int[] $quizIds
     */
    private function deleteQuizzes(array $quizIds)
    {
        $prefix             = 'quiz_id';
        $quizIdPlaceholders = $this->getPlaceHoldersString($quizIds, $prefix);
        $parameters         = $this->getArrayParameters($quizIds, $prefix);

        // delete quiz answers (fka responses) if either attempt, section, or question belongs to orphan Quiz
        // We need the convoluted WHERE clause as eg STK has 35 instances where response.section.quiz != response.attempt.quiz
        // NORMALISATION FTW!!!
        $statement = $this->db->prepare("
            DELETE response FROM assess_response response
            LEFT JOIN assess_attempt   attempt ON   attempt.attempt_id  = response.attempt_id
            LEFT JOIN assess_section   section ON   section.section_id  = response.section_id
            LEFT JOIN assess_question question ON  question.question_id = response.question_id
            LEFT JOIN assess_section q_section ON q_section.section_id  = question.section_id
            WHERE attempt.assess_id IN ($quizIdPlaceholders)
            OR    section.assess_id IN ($quizIdPlaceholders)
            OR  q_section.assess_id IN ($quizIdPlaceholders)
        ");
        $this->executeDeleteQuery($statement, $parameters);

        // quiz attempts
        $statement = $this->db->prepare("
            DELETE FROM assess_attempt WHERE assess_id IN ($quizIdPlaceholders)
        ");
        $this->executeDeleteQuery($statement, $parameters);


        // quiz solutions (fka answers)
        $statement = $this->db->prepare("
            DELETE answer FROM assess_answer answer
            JOIN assess_question question ON question.question_id = answer.question_id
            JOIN assess_section section ON  section.section_id  = question.section_id
            WHERE section.assess_id IN ($quizIdPlaceholders)
        ");
        $this->executeDeleteQuery($statement, $parameters);

        // quiz questions
        $statement = $this->db->prepare("
            DELETE question FROM assess_question question
            JOIN assess_section section ON section.section_id = question.section_id
            WHERE section.assess_id IN ($quizIdPlaceholders)

        ");
        $this->executeDeleteQuery($statement, $parameters);

        // quiz sections
        $statement = $this->db->prepare("
            DELETE FROM assess_section WHERE section_id IN ($quizIdPlaceholders)
        ");
        $this->executeDeleteQuery($statement, $parameters);


        // quiz
        $statement = $this->db->prepare("
            DELETE FROM assess WHERE assess_id IN ($quizIdPlaceholders)
        ");
        $this->executeDeleteQuery($statement, $parameters);
    }


    /**
     * Delete dueWork and all their dependents
     *
     * @param int[] $dueWorkIds
     */
    private function deleteDueWork(array $dueWorkIds)
    {
        $prefix                = 'dueWork_id';
        $dueWorkIdPlaceholders = $this->getPlaceHoldersString($dueWorkIds, $prefix);
        $parameters            = $this->getArrayParameters($dueWorkIds, $prefix);


        // eportfolio showcase items
        $statement = $this->db->prepare("
            DELETE FROM eportfolio_showcase_item WHERE submission_box_id IN ($dueWorkIdPlaceholders)
        ");
        $this->executeDeleteQuery($statement, $parameters);


        // rubric_mark
        $statement = $this->db->prepare("
            DELETE rm FROM rubric_mark rm
            JOIN rubric_descriptor rd ON rd.id = rm.descriptor_id
            JOIN rubric_level rl      ON rl.id = rd.level_id
            WHERE rl.box_id IN ($dueWorkIdPlaceholders)
        ");
        $this->executeDeleteQuery($statement, $parameters);


        // rubric_descriptor
        $statement = $this->db->prepare("
            DELETE rd FROM rubric_descriptor rd
            JOIN rubric_level rl ON rl.id = rd.level_id
            WHERE rl.box_id IN ($dueWorkIdPlaceholders)
        ");
        $this->executeDeleteQuery($statement, $parameters);


        // rubric_level
        $statement = $this->db->prepare("
            DELETE FROM rubric_level WHERE box_id IN ($dueWorkIdPlaceholders)
        ");
        $this->executeDeleteQuery($statement, $parameters);


        // rubric_usage
        $statement = $this->db->prepare("
            DELETE FROM rubric_usage WHERE submission_box_id IN ($dueWorkIdPlaceholders)
        ");
        $this->executeDeleteQuery($statement, $parameters);


        // submission return files
        $statement = $this->db->prepare("
            DELETE srf FROM submission_return_file srf
            JOIN submission_return sr ON srf.submission_return_id = sr.id
            WHERE sr.box_id IN ($dueWorkIdPlaceholders)
        ");
        $this->executeDeleteQuery($statement, $parameters);


        // submission returns
        $statement = $this->db->prepare("
            DELETE FROM submission_return WHERE box_id IN ($dueWorkIdPlaceholders)
        ");
        $this->executeDeleteQuery($statement, $parameters);


        // Plagscans
        $statement = $this->db->prepare("
            DELETE pd FROM plagscan_document pd
            JOIN student_submission_file ssf ON ssf.id = pd.student_submission_file_id
            JOIN student_submission ss       ON ss.id = ssf.student_submission_id
            WHERE ss.box_id IN ($dueWorkIdPlaceholders)
        ");
        $this->executeDeleteQuery($statement, $parameters);


        // student submission files
        $statement = $this->db->prepare("
            DELETE ssf FROM student_submission_file ssf
            JOIN student_submission ss ON ss.id = ssf.student_submission_id
            WHERE ss.box_id IN ($dueWorkIdPlaceholders)
        ");
        $this->executeDeleteQuery($statement, $parameters);


        // student submissions
        $statement = $this->db->prepare("
            DELETE FROM student_submission WHERE box_id IN ($dueWorkIdPlaceholders)
        ");
        $this->executeDeleteQuery($statement, $parameters);


        // submission_box_file
        $statement = $this->db->prepare("
            DELETE FROM submission_box_file WHERE box_id IN ($dueWorkIdPlaceholders)
        ");
        $this->executeDeleteQuery($statement, $parameters);


        // submission boxes
        $statement = $this->db->prepare("
            DELETE FROM submission_box WHERE id IN ($dueWorkIdPlaceholders)
        ");
        $this->executeDeleteQuery($statement, $parameters);

    }


    /**
     * Delete assessments and all their dependents
     *
     * @param int[] $assessmentIds
     */
    private function deleteAssessments(array $assessmentIds)
    {
        $prefix                   = 'a_id';
        $assessmentIdPlaceholders = $this->getPlaceHoldersString($assessmentIds, $prefix);
        $parameters               = $this->getArrayParameters($assessmentIds, $prefix);

        // curriculum_node_for_assessment
        $statement = $this->db->prepare("
            DELETE FROM curriculum_node_for_assessment WHERE assessment_id IN ($assessmentIdPlaceholders)
        ");
        $this->executeDeleteQuery($statement, $parameters);

        // assessment_participant
        $statement = $this->db->prepare("
            DELETE FROM assessment_participant WHERE assessment_id IN ($assessmentIdPlaceholders)
        ");
        $this->executeDeleteQuery($statement, $parameters);

        // assessment_specification_implementation
        $statement = $this->db->prepare("
            DELETE FROM assessment_specification_implementation 
            WHERE specification_assessment_id IN ($assessmentIdPlaceholders)
            OR implementation_assessment_id IN ($assessmentIdPlaceholders)
        ");
        $this->executeDeleteQuery($statement, $parameters);

        // Assessments
        $statement = $this->db->prepare("
            DELETE FROM assessment WHERE id IN ($assessmentIdPlaceholders)
        ");
        $this->executeDeleteQuery($statement, $parameters);
    }


    /**
     * Drop deprecated tables
     */
    private function dropDeprecatedTables()
    {
        $this->executeDropQuery('submission_notification');
        $this->executeDropQuery('curriculum_node_for_assess_deprecated');
        $this->executeDropQuery('curriculum_node_for_submission_box_deprecated');
    }


    /**
     * Print an summary at execution completion.
     */
    private function printSummary()
    {
        $width = self::WIDTH_COUNT;

        $this->log->info(sprintf("%{$width}s tables purged of orphan data", number_format($this->countTablesPurged)));
        $this->log->info(sprintf("%{$width}s rows deleted", number_format($this->countRowsDeleted)));
        $this->log->info(sprintf("%{$width}s deprecated tables dropped", number_format($this->countTablesDropped)));
    }


    /**
     * Fetch the ids of all assessments that do not have a context (ie they are orphans).
     *
     * These most likely exist because the folder they belong to was hard deleted prior to 2014
     */
    private function fetchOrphanAssessmentIds()
    {
        // Fetch ids of orphan tasks
        $selectOrphanAssessments = $this->db->prepare("
            SELECT a.id AS 'a.id', tk.id AS 'tk.id', qz.assess_id AS 'qz.id', sb.id AS 'dw.id'
            FROM assessment a
            LEFT JOIN task tk           ON tk.assessment_id = a.id
            LEFT JOIN submission_box sb ON sb.assessment_id = a.id
            LEFT JOIN assess qz         ON qz.assessment_id = a.id
        
            LEFT JOIN assessment_folder af                      ON  af.assessment_id = a.id
            LEFT JOIN project_assessment pa                     ON  pa.assessment_id = a.id
            LEFT JOIN assessment_course_unit_implementation aui ON aui.assessment_id = a.id
            LEFT JOIN assessment_course_unit_specification aus  ON aus.assessment_id = a.id
        
            WHERE (af.assessment_id IS NULL AND pa.assessment_id IS NULL AND aui.assessment_id IS NULL AND aus.assessment_id IS NULL)
            AND (qz.assess_id IS NULL OR qz.availability = 'assess')
        ");

        $selectOrphanAssessments->execute();
        $width = self::WIDTH_COUNT;
        $this->log->info(sprintf("%{$width}s assessments found without an assessment context", number_format($selectOrphanAssessments->rowCount())));

        while ($row = $selectOrphanAssessments->fetch(PDO::FETCH_ASSOC)) {
            $assessmentId = $row['a.id'];
            $taskId       = $row['tk.id'];
            $quizId       = $row['qz.id'];
            $dueWorkId    = $row['dw.id'];

            $this->orphanAssessmentIds[] = (int)$assessmentId;

            if ($taskId) {
                $this->orphanTaskIds[] = (int)$taskId;
            } elseif ($quizId) {
                $this->orphanQuizIds[] = (int)$quizId;
            } elseif ($dueWorkId) {
                $this->orphanDueWorkIds[] = (int)$dueWorkId;
            } else {
                $this->log->error(sprintf('Unable to determine assessment type: a.id "%d"', $assessmentId));
                exit(1);
            }
        }


        $this->log->info(sprintf("%{$width}s tasks found without an assessment context", number_format(count($this->orphanTaskIds))));
        $this->log->info(sprintf("%{$width}s quizzes found without an assessment context", number_format(count($this->orphanQuizIds))));
        $this->log->info(sprintf("%{$width}s due works found without an assessment context", number_format(count($this->orphanDueWorkIds))));
    }


    /**
     * @param array       $input
     * @param null|string $prefix
     * @return string
     */
    private function getPlaceHoldersString(array $input = [], $prefix = null)
    {
        $placeholders = [];
        foreach ($input as $i => $param) {
            $placeholders[] = null !== $prefix ? (':' . $prefix . '_' . $this->escapePlaceholder($i)) : '?';
        }
        return implode(',', $placeholders);
    }


    /**
     * @param array $input
     * @param null  $prefix
     * @return string[]
     */
    private function getArrayParameters(array $input = [], $prefix = null)
    {
        $params = [];
        foreach ($input as $i => $param) {
            if (null !== $prefix) {
                $params[$prefix . '_' . $this->escapePlaceholder($i)] = $param;
            } else {
                $params[] = $param;
            }
        }
        return $params;
    }


    /**
     * Strips dis-allowed characters from placeholders.
     *
     * @param string $placeholder
     * @return string
     */
    private function escapePlaceholder($placeholder)
    {
        return preg_replace('/\W/', '_', $placeholder);
    }


    /**
     * Executes a DELETE pdo statement
     *
     * @param PDOStatement $statement
     * @param string[]     $parameters
     */
    private function executeDeleteQuery(PDOStatement $statement, $parameters)
    {
        // Get the name of the table we are deleting from
        $pattern = '/DELETE\s+(?:\S+\s+)?FROM\s+(?:[^\s\.]+\.)?([^\s\.]+)/i';

        if (!preg_match($pattern, $statement->queryString, $matches)) {
            $this->log->error('Unable to parse table name from query string');
            exit(1);
        }
        $tableName = $matches[1];

        $width = self::WIDTH_COUNT;

        // Do we have no parameters? (ie no assessments for the WHERE clause)
        if(!count($parameters)){
            $this->log->info(sprintf("%{$width}s rows deleted from '$tableName'", '0'));
            $this->countTablesPurged++;
            return;
        }


        try {
            $statement->execute($parameters);
            $this->log->info(sprintf("%{$width}s rows deleted from '$tableName'", number_format($statement->rowCount())));
            $this->countTablesPurged++;
            $this->countRowsDeleted += $statement->rowCount();
        } catch (PDOException $e) {
            $this->log->error("Unable to delete from '$tableName'", [$e]);
            $this->log->warning($e->getMessage());
            exit(1);
        }
    }

    /**
     * Executes a DELETE pdo statement
     *
     * @param string $table
     */
    private function executeDropQuery($table)
    {
        $statement = $this->db->prepare("
            DROP TABLE IF EXISTS `$table`
        ");

        try {
            $statement->execute();
            $this->log->info("TABLE '$table' dropped");
            $this->countTablesDropped++;
        } catch (PDOException $e) {
            $this->log->error("Unable to drop table '$table'", [$e]);
            $this->log->warning($e->getMessage());
            exit(1);
        }
    }
}
