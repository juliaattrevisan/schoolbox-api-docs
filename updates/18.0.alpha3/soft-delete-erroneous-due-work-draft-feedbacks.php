#!/usr/bin/php
<?php
/**
 * For each due work participant, if they have multiple draft feedbacks, soft-delete
 * all but the most recent draft feedback.
 *
 * If a participant's latest published feedback was published after their latest draft feedback was updated,
 * we can also soft-delete that draft. This is to account for the case where we had multiple drafts, and the latest
 * was converted to published.
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

    /**
     * Time of script invocation. Set all deleted_at dates to this value to make identification easier.
     *
     * @var string 'Y-m-d H:i:s' format
     */
    private $now;

    /** @var LoggerInterface */
    private $log;

    /** @var IConnector */
    private $database;

    /** @var IStatement */
    private $findLatestPublishedStatement;

    /** @var IStatement */
    private $findPriorDraftFeedbackStatement;

    /** @var IStatement */
    private $findDraftFeedbackStatement;

    /** @var IStatement */
    private $deleteDraftFeedbackStatement;

    /** @var int */
    private $countFeedbacksSoftDeleted = 0;


    public function __construct()
    {
        $this->now = Alaress_Date_Format::now()->getDatabase();
    }


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
        $this->log      = $log;
        $this->database = $di->get('databaseInternal');

        $this->prepareStatements();

        $this->database->beginTransaction();
        try {
            $this->execute();
            $this->database->commit();
        } catch (\Alaress\Database\Exception $e) {
            $this->log->error($e->getMessage(), [$e]);
            $this->database->rollBack();
        }
    }


    /**
     * Prepare statements that will be re-used to avoid re-compile time
     */
    private function prepareStatements()
    {
        // Find participant's latest published feedback, if one exists
        $this->findLatestPublishedStatement = $this->database->prepareQuery('
        SELECT sr1.updated_at, sr1.published_at
        FROM submission_return sr1
        JOIN (SELECT MAX(published_at) AS max_published_at
            FROM submission_return sr2
            WHERE sr2.published_at IS NOT NULL
            AND sr2.deleted_at IS NULL
            AND sr2.owner = :student_id
            AND sr2.box_id = :box_id) t2 ON t2.max_published_at = sr1.published_at
        WHERE sr1.published_at IS NOT NULL
        AND sr1.deleted_at IS NULL
        AND sr1.owner = :student_id
        AND sr1.box_id = :box_id
        ');

        // Find all draft feedbacks that were last updated before the specified date
        $this->findPriorDraftFeedbackStatement = $this->database->prepareQuery('
            SELECT id, created_at, updated_at
            FROM submission_return
            WHERE owner = :student_id
            AND box_id = :box_id
            AND published_at IS NULL
            AND deleted_at IS NULL
            AND updated_at <= :updated_at
            ORDER BY updated_at DESC, created_at DESC, id DESC;
        ');

        // Find all non-deleted drafts for specified participant
        $this->findDraftFeedbackStatement = $this->database->prepareQuery('
            SELECT id, created_at, updated_at
            FROM submission_return
            WHERE owner = :student_id
            AND box_id = :box_id
            AND published_at IS NULL
            AND deleted_at IS NULL
            ORDER BY updated_at DESC, created_at DESC, id DESC;
        ');

        $this->deleteDraftFeedbackStatement = $this->database->prepareQuery('
            UPDATE submission_return
            SET deleted_at = :deleted_at
            WHERE id = :id;
        ');
    }


    /**
     * @throws \Alaress\Database\Exception
     */
    private function execute()
    {
        $this->log->info("All matching `submission_return`.`deleted_at` values will be set to '{$this->now}'");

        foreach ($this->findParticipantsWithPublishedAfterDrafts() as $participant) {
            $this->processParticipantWithLaterPublished((int)$participant['owner'], (int)$participant['box_id']);
        }

        $this->log->debug("\n");

        foreach ($this->findParticipantsWithMultipleDrafts() as $participant) {
            $this->processParticipantWithMultipleDrafts((int)$participant['owner'], (int)$participant['box_id']);
        }

        $this->log->info($this->countFeedbacksSoftDeleted . ' draft feedbacks soft deleted');
    }



    /**
     * Find participants that have a draft feedbacks that were last updated prior to a published feedback was published.
     *
     * This handles the case where a participant had multiple drafts, but the latest draft was then published.
     * In such cases, the prior drafts should have been soft-deleted.
     *
     * @return int[][] Keys: ['owner', 'box_id']
     */
    private function findParticipantsWithPublishedAfterDrafts(): array
    {
        $this->log->info('Searching for participants with drafts having subsequent published feedback...');
        $participants = $this->database->executeQuery('
            SELECT sr.owner, sr.box_id
            FROM submission_return sr
            JOIN (
                -- get most recent published feedback date per participant
                SELECT MAX(published_at) AS max_published_at, owner, box_id
                FROM submission_return
                WHERE published_at IS NOT NULL
                AND deleted_at IS NULL
                GROUP BY owner, box_id) t2 ON t2.owner = sr.owner AND t2.box_id = sr.box_id
            WHERE sr.published_at IS NULL
            AND sr.deleted_at IS NULL
            AND sr.updated_at <= t2.max_published_at
            GROUP BY sr.owner, sr.box_id;
        ')->fetchAll();

        $this->log->info(\count($participants) . ' participants found with non-deleted drafts updated prior to latest published');
        return $participants;
    }


    /**
     * @return int[][] Keys: ['owner', 'box_id']
     */
    private function findParticipantsWithMultipleDrafts(): array
    {
        $this->log->info('Searching for participants having multiple draft feedbacks...');
        $participants = $this->database->executeQuery('
            SELECT owner, box_id
            FROM submission_return
            WHERE published_at IS NULL
            AND deleted_at IS NULL
            GROUP BY owner, box_id
            HAVING COUNT(*) > 1;
        ')->fetchAll();

        $this->log->info(\count($participants) . ' due work participants found with multiple non-deleted draft feedbacks');
        return $participants;
    }


    /**
     * @param int $studentId
     * @param int $boxId
     * @throws \Alaress\Database\Exception If execution failed for some reason
     */
    private function processParticipantWithLaterPublished(int $studentId, int $boxId)
    {
        $this->log->info("Processing: student ($studentId), box ($boxId) ...");
        $this->findLatestPublishedStatement->execute([
            'student_id' => $studentId,
            'box_id'     => $boxId,
        ]);
        $latestPublishedFeedback = $this->findLatestPublishedStatement->fetch();
        $updatedAt = $latestPublishedFeedback['updated_at'];
        $publishedAt = $latestPublishedFeedback['published_at'];
        $this->log->debug("Latest published feedback: Published: '$publishedAt', Updated '$updatedAt'. Deleting prior drafts...");

        // Most likely the 'updated_at' and 'published_at' are the same value. (or a few seconds out due to issue ADF::now() vs SQL::NOW())
        // But we are strictly interested in drafts that were last updated prior to another feedback being published.
        $this->findPriorDraftFeedbackStatement->execute([
            'student_id' => $studentId,
            'box_id'     => $boxId,
            'updated_at' => $publishedAt,
        ]);

        $this->log->debug($this->findPriorDraftFeedbackStatement->rowCount() . ' prior draft feedbacks found');

        /** @noinspection PhpAssignmentInConditionInspection */
        while ([$id, $createdAt, $updatedAt] = $this->findPriorDraftFeedbackStatement->fetch(PDO::FETCH_NUM)) {
            $this->log->debug(sprintf('Deleting earlier draft.    Updated:    %s, Created: %s, Id: %d', $createdAt, $updatedAt, $id));
            $this->deleteDraftFeedbackStatement->execute([
                'id'         => $id,
                'deleted_at' => $this->now,
            ]);
            $this->countFeedbacksSoftDeleted++;
        }
    }


    /**
     * Deletes all but the most-recent draft for the specfied participant
     *
     * @param int $studentId
     * @param int $boxId
     * @throws \Alaress\Database\Exception
     */
    private function processParticipantWithMultipleDrafts(int $studentId, int $boxId)
    {
        $this->log->info("Processing: student ($studentId), box ($boxId) ...");

        $this->findDraftFeedbackStatement->execute([
            'student_id' => $studentId,
            'box_id'     => $boxId,
        ]);

        $drafts = $this->findDraftFeedbackStatement->fetchAll();
        $this->log->debug(\count($drafts) . ' prior draft feedbacks found');

        /** @var string[][] $drafts */
        foreach ($drafts as $idx => $row) {
            $id        = $row['id'];
            $createdAt = $row['created_at'];
            $updatedAt = $row['updated_at'];
            if (0 === $idx) {
                $this->log->debug(sprintf('Retaining most recent draft.    Updated: %s, Created: %s, Id: %d', $createdAt, $updatedAt, $id));
                continue;
            }
            $this->log->debug(sprintf('Deleting earlier draft.         Updated: %s, Created: %s, Id: %d', $createdAt, $updatedAt, $id));
            $this->deleteDraftFeedbackStatement->execute([
                'id' => $id,
                'deleted_at' => $this->now,
            ]);
            $this->countFeedbacksSoftDeleted++;
        }
    }
};

try {
    $kernel->run($deletor);
} catch (Exception $e) {
    throw $e;
}
