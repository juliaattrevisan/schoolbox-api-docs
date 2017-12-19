#!/usr/bin/php
<?php
/**
 * For each due work participant, if they have multiple draft feedbacks, soft-delete
 * all but the most recent draft feedback
 *
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2017, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

use Alaress\Database\Connector\IConnector;
use Alaress\Database\Statement\IStatement;
use Aura\Di;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use Schoolbox\Application\Daemon\App;

$opts = require __DIR__ . '/../../conf/core/require-cli.php';
spl_autoload_call('\\Schoolbox\\Application\\Daemon');

$kernel = new Schoolbox\Application\Daemon\Kernel("'Soft-Delete erroneous DueWork draft feedbacks'", [
    'verbose' => $opts['v'] ?? null,
    'errors'  => E_ALL,
]);

$deletor = new class extends App
{

    /** @var LoggerInterface */
    private $log;

    /** @var IStatement */
    private $findParticipantsStatement;

    /** @var IStatement */
    private $findDraftFeedbackStatement;

    /** @var IStatement */
    private $deleteDraftFeedbackStatement;

    /** @var int */
    private $countFeedbacksSoftDeleted = 0;


    /**
     * @param Di\Container    $di
     * @param LoopInterface   $loop
     * @param LoggerInterface $log
     * @param array           $opts
     * @return void
     * @throws Di\Exception\ServiceNotFound
     * @throws PDOException
     */
    public function run(Di\Container $di, LoopInterface $loop, LoggerInterface $log, array $opts): void
    {
        $this->log = $log;

        /** @var IConnector $database */
        $database = $di->get('databaseInternal');

        $this->buildStatements($database);
        $this->execute($database);
    }


    /**
     * @param IConnector $database
     */
    private function buildStatements(IConnector $database)
    {
        $this->findParticipantsStatement = $database->prepareQuery('
            SELECT owner, box_id
            FROM submission_return
            WHERE published_at IS NULL
            AND deleted_at IS NULL
            GROUP BY owner, box_id
            HAVING COUNT(*) > 1;
        ');

        $this->findDraftFeedbackStatement = $database->prepareQuery('
            SELECT id, created_at, updated_at
            FROM submission_return
            WHERE owner = :student_id
            AND box_id = :box_id
            AND published_at IS NULL
            AND deleted_at IS NULL
            ORDER BY created_at DESC, updated_at DESC, id DESC;
        ');

        $this->deleteDraftFeedbackStatement = $database->prepareQuery('
            UPDATE submission_return
            SET deleted_at = NOW()
            WHERE id = :id;
        ');
    }


    /**
     * @param IConnector $database
     * @throws PDOException
     */
    private function execute(IConnector $database)
    {
        $database->beginTransaction();
        try {
            foreach ($this->findParticipantsWithMultipleDrafts() as $participant) {
                $this->processParticipant($participant['owner'], $participant['box_id']);
            }
            $database->commit();
            $this->log->info($this->countFeedbacksSoftDeleted . ' draft feedbacks soft deleted');
        } catch (\Alaress\Database\Exception $e) {
            $this->log->error($e->getMessage(), [$e]);
            $database->rollBack();
        }
    }


    /**
     * @return int[] with keys 'owner' and 'box_id'
     * @throws \Alaress\Database\Exception If execution failed for some reason
     */
    private function findParticipantsWithMultipleDrafts(): array
    {
        $this->findParticipantsStatement->execute();
        $results = $this->findParticipantsStatement->fetchAll();
        $this->log->info((\count($results) ?: 'No') . ' due work participants found with multiple draft feedbacks');
        return $results;
    }


    /**
     * @param int $studentId
     * @param int $boxId
     * @throws \Alaress\Database\Exception If execution failed for some reason
     */
    private function processParticipant($studentId, $boxId)
    {
        $this->log->info("Fetching all draft feedback for student ($studentId) and box ($boxId) ...");

        $this->findDraftFeedbackStatement->execute([
            'student_id' => $studentId,
            'box_id'     => $boxId,
        ]);

        $results = $this->findDraftFeedbackStatement->fetchAll();
        $this->log->info(\count($results) . ' draft feedbacks found');

        /** @var string[][] $results */
        foreach ($results as $idx => $row) {
            $id        = $row['id'];
            $createdAt = $row['created_at'];
            $updatedAt = $row['updated_at'];
            if (!$idx) {
                $this->log->info(sprintf('Retaining most recent draft. Created: %s, Updated: %s, Id: %d', $createdAt, $updatedAt, $id));
                continue;
            }
            $this->log->info(sprintf('Deleting earlier draft.      Created: %s, Updated: %s, Id: %d', $createdAt, $updatedAt, $id));
            $this->deleteDraftFeedbackStatement->execute(['id' => $id]);
            $this->countFeedbacksSoftDeleted++;
        }
    }
};

try {
    $kernel->run($deletor);
} catch (Exception $e) {
    throw $e;
}
