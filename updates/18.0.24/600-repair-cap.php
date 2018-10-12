#!/usr/bin/php
<?php
/**
 * @copyright Copyright 2018, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

use Alaress\Database\Statement\Query\IQuery;
use Aura\Di;
use Monolog\Logger;
use React\EventLoop\LoopInterface;
use Schoolbox\Application\Daemon;
use Psr\Log\LoggerInterface;
use Alaress\Database;

$opts = require __DIR__ . '/../../conf/core/require-cli.php';
spl_autoload_call('\\Schoolbox\\Application\\Daemon');

$kernel = new Daemon\Kernel("Repair 'Custom Assessment Participation' (CAP)", [
    'verbose' => $opts['v'] ?? [1, 2, 3],
    'errors'  => E_ALL,
    'fields'  => array_keys($opts),
]);

/**
 * Relevant Business Rules:
 * * Every interaction, regardless of deleted state, should map to a participant object
 * * When creating participant objects due to an interaction, the participant must link to a dynamic plan
 * * An assessment can have 0:1 dynamic plans (regardless of whether the dynamic plan is soft-deleted)
 *
 * Tool Design Considerations:
 * * We could do some of this work is large SQL queries, but we've chosen to chop it up for understandability
 */
$capRepair = new class extends Daemon\App
{

    // A debug flag that can be used for testing to perform a dry run
    private const IS_DRY_RUN = false;

    /**
     * The create/update time to use on all inserted records
     *
     * @var string
     */
    private $nowDatabase;

    /** @var LoggerInterface */
    private $log;

    /** @var Database\Connector\IConnector */
    private $db;

    /** @var int */
    private $countPlansInserted;

    /** @var int */
    private $countParticipantsInserted;

    /** @var IQuery */
    private $insertPlanStatement;

    /** @var IQuery */
    private $insertParticipantStatement;


    /**
     * @inheritdoc
     */
    public function run(Di\Container $di, LoopInterface $loop, LoggerInterface $log, array $opts): void
    {
        $this->nowDatabase = Alaress_Date_Format::now()->getDatabase();
        $this->log         = $log;
        $this->db          = $di->get('databaseInternal');

        $this->prepareStatements();

        $this->resetCounts();

        $this->repairTask();
        $this->repairDueWork();
        $this->repairQuiz();

        $this->outputCounts();
    }


    /**
     * Prepares SQL statements that will be used potentially many times by this tool
     */
    private function prepareStatements(): void
    {
        $this->insertPlanStatement = $this->db->prepareQuery("
            INSERT INTO participant_plan(assessment_id, is_dynamic, created_at, updated_at)
            VALUES (:a_id, TRUE, :created_at, :updated_at)"
        );

        $this->insertParticipantStatement = $this->db->prepareQuery("
            INSERT INTO participant(participant_plan_id, user_id, created_at, updated_at)
            VALUES (:plan_id, :user_id, :created_at, :updated_at)"
        );
    }


    /**
     * Reset the counts ready for fresh run
     */
    private function resetCounts(): void
    {
        $this->countPlansInserted        = 0;
        $this->countParticipantsInserted = 0;
    }


    /**
     * Repairs Task plans and participants
     */
    private function repairTask(): void
    {
        $this->insertTaskPlans();
        $this->insertTaskParticipants();
    }


    /**
     * Repairs DueWork plans and participants
     */
    private function repairDueWork(): void
    {
        $this->insertDueWorkPlans();
        $this->insertDueWorkParticipants();
    }


    /**
     * Repairs Quiz plans and participants
     */
    private function repairQuiz(): void
    {
        $this->insertQuizPlans();
        $this->insertQuizParticipants();
    }


    /**
     * Inserts dynamic plans for any task where:
     * * task has interactions (even if deleted) that don't have a participation record, and
     * * the task does not have a dynamic plan
     */
    private function insertTaskPlans(): void
    {
        $sql = "
            SELECT a.id, a.created_at

            -- Get all the interactions
            FROM assessment a
            JOIN task t ON a.id = t.assessment_id
            JOIN task_completion tc ON t.id = tc.task_id
            
            -- Is there a participation record?
            LEFT JOIN participant_plan pp ON a.id = pp.assessment_id
            LEFT JOIN participant p on pp.id = p.participant_plan_id AND p.user_id = tc.student_id
            
            -- Also get assessments dynamic plan details
            LEFT JOIN participant_plan dpp ON a.id = dpp.assessment_id AND dpp.is_dynamic
            
            -- Where no participation record for the interaction, and no dynamic plan
            WHERE p.id IS NULL AND dpp.id IS NULL
            GROUP BY a.id
        ";

        $this->insertDynamicPlans('task', $sql);
    }


    /**
     * Inserts records for each task participant that does not have a participation record
     */
    private function insertTaskParticipants(): void
    {
        $sql = "
            SELECT dpp.id, tc.student_id
            
            -- Get interaction details
            FROM assessment a
            JOIN task t ON a.id = t.assessment_id 
            JOIN task_completion tc ON t.id = tc.task_id
            
            -- Is there a participation record?
            LEFT JOIN participant_plan pp ON a.id = pp.assessment_id
            LEFT JOIN participant p on pp.id = p.participant_plan_id AND p.user_id = tc.student_id
            
            -- Get dynamic plan details
            LEFT JOIN participant_plan dpp ON a.id = dpp.assessment_id AND dpp.is_dynamic
            
            -- Where no participation record
            WHERE p.id IS NULL
            GROUP BY dpp.id, tc.student_id
            ORDER BY a.id, tc.student_id";

        $this->insertParticipants('task', $sql);
    }


    /**
     * Inserts dynamic plans for any DueWork where:
     * * DueWork has interactions (even if deleted) that don't have a participation record, and
     * * the DueWork does not have a dynamic plan
     */
    private function insertDueWorkPlans(): void
    {
        $sql = "
            SELECT a.id, a.created_at

            -- Get all the interactions
            FROM assessment a
            JOIN (
              -- Get all assessment/student combos where the student has due-work interactions that are not deleted
              SELECT * FROM (
                SELECT sb.assessment_id, ss.owner
                FROM submission_box sb
                JOIN student_submission ss on sb.id = ss.box_id
                  UNION
                SELECT sb.assessment_id, sr.owner
                FROM submission_box sb
                JOIN submission_return sr on sb.id = sr.box_id
              ) t1 GROUP BY t1.assessment_id, t1.owner
            ) t2 ON t2.assessment_id = a.id
            
            -- Is there a participation record?
            LEFT JOIN participant_plan pp ON a.id = pp.assessment_id
            LEFT JOIN participant p on pp.id = p.participant_plan_id AND p.user_id = t2.owner
            
            -- Also get assessments dynamic plan details
            LEFT JOIN participant_plan dpp ON a.id = dpp.assessment_id AND dpp.is_dynamic
            
            -- Where no participation record for the interaction, and no dynamic plan
            WHERE p.id IS NULL AND dpp.id IS NULL
            GROUP BY a.id
        ";

        $this->insertDynamicPlans('due-work', $sql);
    }


    /**
     * Inserts records for each due-work participant that does not have a participation record
     */
    private function insertDueWorkParticipants(): void
    {
        $sql = "
            SELECT dpp.id, t2.owner
            
            -- Get interaction details
            FROM assessment a
            JOIN (
              -- Get all assessment/student combos where the student has due-work interactions that are not deleted
              SELECT * FROM (
                SELECT sb.id, sb.assessment_id, ss.owner
                FROM submission_box sb
                JOIN student_submission ss on sb.id = ss.box_id
                  UNION
                SELECT sb.id, sb.assessment_id, sr.owner
                FROM submission_box sb
                JOIN submission_return sr on sb.id = sr.box_id
              ) t1 GROUP BY t1.assessment_id, t1.owner
            ) t2 ON t2.assessment_id = a.id
            
            -- Is there a participation record?
            LEFT JOIN participant_plan pp ON a.id = pp.assessment_id
            LEFT JOIN participant p on pp.id = p.participant_plan_id AND p.user_id = t2.owner
            
            -- Get dynamic plan details
            LEFT JOIN participant_plan dpp ON a.id = dpp.assessment_id AND dpp.is_dynamic
            
            -- Where no participation record
            WHERE p.id IS NULL
            GROUP BY dpp.id, t2.owner
            ORDER BY a.id, t2.owner";

        $this->insertParticipants('due-work', $sql);
    }


    /**
     * Inserts dynamic plans for any Quiz where:
     * * Quiz has interactions (even if deleted) that don't have a participation record, and
     * * the Quiz does not have a dynamic plan
     */
    private function insertQuizPlans(): void
    {
        $sql = "
            SELECT a.id, a.created_at

            -- Get all the interactions
            FROM assessment a
            JOIN assess q on a.id = q.assessment_id
            JOIN assess_attempt qa ON q.assess_id = qa.assess_id
            
            -- Is there a participation record?
            LEFT JOIN participant_plan pp ON a.id = pp.assessment_id
            LEFT JOIN participant p on pp.id = p.participant_plan_id AND p.user_id = qa.user_id
            
            -- Also get assessments dynamic plan details
            LEFT JOIN participant_plan dpp ON a.id = dpp.assessment_id AND dpp.is_dynamic
            
            -- Where no participation record for the interaction, and no dynamic plan
            WHERE p.id IS NULL AND dpp.id IS NULL
            GROUP BY a.id
        ";

        $this->insertDynamicPlans('quiz', $sql);
    }


    /**
     * Inserts records for each quiz participant that does not have a participation record
     */
    private function insertQuizParticipants(): void
    {
        $sql = "
            SELECT dpp.id, qa.user_id
            
            -- Get interaction details
            FROM assessment a
            JOIN assess q ON q.assessment_id = a.id
            JOIN assess_attempt qa ON qa.assess_id = q.assess_id
            
            -- Is there a participation record?
            LEFT JOIN participant_plan pp ON a.id = pp.assessment_id
            LEFT JOIN participant p on pp.id = p.participant_plan_id AND p.user_id = qa.user_id
            
            -- Get dynamic plan details
            LEFT JOIN participant_plan dpp ON a.id = dpp.assessment_id AND dpp.is_dynamic
            
            -- Where no participation record
            WHERE p.id IS NULL
            GROUP BY dpp.id, qa.user_id
            ORDER BY a.id, qa.user_id";

        $this->insertParticipants('quiz', $sql);
    }


    /**
     * Executes a assessment-type specific query to find assessments that have:
     * * interactions without participant records, and
     * * no dynamic plan
     *
     * @param string $assessmentType One of ['task', 'due-work', 'quiz']
     * @param string $sql            A query that will return columns [assessment.id, assessment.created_at]
     */
    private function insertDynamicPlans(string $assessmentType, string $sql): void
    {
        $data = $this->db->executeQuery($sql)->fetchAll(PDO::FETCH_NUM);
        $msg  = \count($data) . " {$assessmentType}s found with interactions that lack a participation record, and no dynamic plan";
        $this->log->log(\count($data) ? Logger::WARNING : Logger::INFO, $msg);

        $planId = null;
        foreach ($data as [$aId, $aCreatedAt]) {
            if (!self::IS_DRY_RUN) {
                $this->insertPlanStatement->execute([
                    'a_id'       => $aId,
                    'created_at' => $aCreatedAt,
                    'updated_at' => $aCreatedAt,
                ]);
                $planId = $this->db->lastInsertId();
            }
            $this->log->debug("New dynamic plan ({$planId}) inserted for assessment {$aId}");
            $this->countPlansInserted++;
        }
    }


    /**
     * Executes a assessment-type specific query to find participants without participation records
     * and inserts a participant records into the dynamic plan.
     *
     * @param string $assessmentType One of ['task', 'due-work', 'quiz']
     * @param string $sql            A query that will return columns [plan.id, user.id]
     */
    private function insertParticipants(string $assessmentType, string $sql): void
    {
        $data = $this->db->executeQuery($sql)->fetchAll(PDO::FETCH_NUM);
        $msg  = \count($data) . " {$assessmentType} participants found without participant records. Linking to dynamic plan";
        $this->log->log(\count($data) ? Logger::WARNING : Logger::INFO, $msg);

        $participantId = null;
        foreach ($data as [$dynamicPlanId, $userId]) {
            if (!self::IS_DRY_RUN) {
                $this->insertParticipantStatement->execute([
                    'plan_id'    => $dynamicPlanId,
                    'user_id'    => $userId,
                    'created_at' => $this->nowDatabase,
                    'updated_at' => $this->nowDatabase,
                ]);
                $participantId = $this->db->lastInsertId();
            }
            $this->log->debug("New participant record ({$participantId}) inserted for user {$userId} in dynamic plan {$dynamicPlanId}");
            $this->countParticipantsInserted++;
        }
    }


    /**
     * Renders total insert counts
     */
    private function outputCounts(): void
    {
        $this->log->info("{$this->countPlansInserted} dynamic plans inserted");
        $this->log->info("{$this->countParticipantsInserted} participants inserted");
    }
};

/** @noinspection PhpUnhandledExceptionInspection */
$kernel->run($capRepair);

