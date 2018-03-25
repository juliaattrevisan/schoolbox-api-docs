#!/usr/bin/php
<?php
/**
 * This script searches for all docs with status of 'error' and error_message of 'Conversion failed',
 * and puts them back into the queue for re-uploading.
 *
 * This *must* be deployed in a later release that the code-fix in SA-6354 to guarantee that this script
 * does not execute while we have old-format documents still asynchronously processing in Kami.
 *
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2017, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

use Alaress\Database\Connector\IConnector;
use Alaress\Database\Statement\Query\IQuery;
use Alaress\Jitter\Jitter;
use Aura\Di;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use Schoolbox\Application\Daemon\App;
use Schoolbox\Learning\InlineGrader\Kami;
use Alaress\Random;

$opts = require __DIR__ . '/../../conf/core/require-cli.php';
spl_autoload_call('\\Schoolbox\\Application\\Daemon');

$kernel = new Schoolbox\Application\Daemon\Kernel("'Re-queue kami conversion errors'", [
    'verbose' => $opts['v'] ?? [1, 2, 3],
    'errors'  => E_ALL,
]);


$reQueue = new class() extends App
{

    /** @var Kami\Connector */
    private $kamiConnector;

    /** @var Kami\Mapper */
    private $kamiMapper;

    /** @var LoggerInterface */
    private $log;

    /** @var IConnector */
    private $database;

    /** @var int */
    private $countSuccess;

    /** @var int */
    private $countWarning;

    /** @var int */
    private $countFailure;

    /** @var IQuery */
    private $queryGetKamiDoc;

    /** @var IQuery */
    private $queryReQueueKamiDoc;


    /**
     * Quasi-constructor and execution entry-point
     *
     * @param Di\Container    $di
     * @param LoopInterface   $loop
     * @param LoggerInterface $log
     * @param array           $opts
     * @return void
     * @throws Di\Exception\ServiceNotFound
     */
    public function run(Di\Container $di, LoopInterface $loop, LoggerInterface $log, array $opts): void
    {
        $this->log           = $log;
        $this->kamiConnector = $di->get('kamiConnector');
        $this->database      = $di->get('databaseInternal');
        $this->kamiMapper    = Kami\Mapper::getInstance();
        $this->countSuccess  = $this->countWarning = $this->countFailure = 0;

        try {
            $this->prepareQueries();
            $this->checkIfProcessingHaveFailedConversion();
            $this->reQueueAndPurgeConversionFailures();
        } catch (\Throwable $e) {
            $this->log->error($e->getMessage(), [$e]);
        }
    }


    /**
     * Prepares the DB queries used repeatedly in this script
     */
    private function prepareQueries()
    {
        $this->queryGetKamiDoc = $this->database->prepareQuery('SELECT kami_document_id FROM student_submission_file_kami WHERE id = :id');

        $this->queryReQueueKamiDoc = $this->database->prepareQuery("
            UPDATE student_submission_file_kami
            SET kami_document_id = NULL, status = 'queued', kami_created_at = NULL
            WHERE id = :id
        ");
    }


    /**
     * Returns a non-zero exit status if we are unable to re-queue any conversion failed documents
     *
     * @return int
     */
    public function getExitCode(): int
    {
        return $this->countFailure ? 1 : 0;
    }


    /**
     * For any kami document that has a status of 'error' because 'Conversion failed', deletes the document
     * from Kami and puts back into the upload queue
     *
     * @throws PDOException
     * @throws \Alaress\Database\Exception
     */
    private function reQueueAndPurgeConversionFailures()
    {
        $conversionFailedKamiIds = $this->getConversionFailedKamiIds();
        $count                   = 1;
        $total                   = \count($conversionFailedKamiIds);
        $this->log->info("Re-queuing {$total} documents that have failed to convert");
        foreach ($conversionFailedKamiIds as $kamiId) {
            $this->queryGetKamiDoc->execute(['id' => $kamiId]);
            $kamiDocumentId = $this->queryGetKamiDoc->fetchColumn();
            $this->log->info("{$count}/{$total} Kami Document id: {$kamiId}, guid: {$kamiDocumentId}");
            $this->reQueueAndPurge($kamiId, $kamiDocumentId);
            $count++;
        }
        $this->log->info("Success count: {$this->countSuccess}, Warning count: {$this->countWarning}, Failure count: {$this->countFailure}");
    }


    /**
     * Checks whether any Kami Documents that are still in the 'processing' status have now finished processing in Kami.
     *
     * This is required because if SB has not yet attempted to view an uploaded document, SB does not know it has
     * finished processing and will not yet know if has failed to convert.
     *
     * @throws Throwable
     */
    private function checkIfProcessingHaveFailedConversion()
    {
        $jitter = new Jitter(
            new Random\FloatGenerator(),
            Kami\StatusUpdater::DEFAULT_MAX_ATTEMPTS,
            Kami\StatusUpdater::DEFAULT_BASE_JITTER_BACK_OFF
        );
        $statusChecker = new Kami\StatusUpdater($this->kamiConnector, $this->kamiMapper, $jitter, $this->log);
        $statusChecker->checkProcessingDocumentsStatus();
    }


    /**
     * Returns the IDs of all Kami documents that have failed to convert
     *
     * @return string[]
     */
    private function getConversionFailedKamiIds(): array
    {
        return $this->database->executeQuery("
            SELECT id FROM student_submission_file_kami
            WHERE status = 'error' AND error_message = 'Conversion failed'
        ")->fetchAll(PDO::FETCH_COLUMN);
    }


    /**
     * @param int    $id
     * @param string $kamiDocumentId
     * @throws PDOException
     */
    private function reQueueAndPurge(int $id, string $kamiDocumentId)
    {
        $this->database->beginTransaction();
        try {
            // If we can't be sure the file has been deleted from Kami, we can't hard-delete in Schoolbox or
            // we'll end up with orphans in Kami which makes matching documents problematic and error-prone
            $this->queryReQueueKamiDoc->execute(['id' => $id]);
            if ($this->deleteFromKami($kamiDocumentId)) {
                $this->database->commit();
                $this->countSuccess++;
                $this->log->debug('Document successfully re-queued');
            }
        } catch (Throwable $e) {
            // If unable to update in schoolbox, or delete from kami, don't commit
            $this->log->warning($e->getMessage());
            $this->database->rollBack();
            $this->countFailure++;
            $this->log->debug('DB transaction rolled back - document not re-queued.');
            return;
        }
    }


    /**
     * Deletes this document from Kami. Returns TRUE if the document no longer exists in Kami
     * and hence we can effectively hard-delete in Schoolbox
     *
     * @param string $kamiDocumentId
     * @return bool
     */
    private function deleteFromKami(string $kamiDocumentId): bool
    {
        try {
            $this->kamiConnector->deleteDocument($kamiDocumentId);
            $this->log->debug('Document successfully deleted in Kami');
            return true;
        } catch (Kami\Exception\NotFound $e) {
            // Document was already deleted (ensures script is idempotent)
            $this->log->warning('Document already deleted in Kami');
            return true;
        } catch (\Throwable $e) {
            // We cannot be sure the document does not still exist in Kami
            $this->log->error('Unable to guarantee that document is deleted from Kami');
            $this->log->error($e->getMessage(), [$e]);
            return false;
        }
    }
};

try {
    $kernel->run($reQueue);
    exit($reQueue->getExitCode());
} catch (Exception $e) {
    exit(2);
}
