<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2017, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\InlineGrader\Archiver;

use Alaress\Database\Connector\IConnector;
use Alaress\Database\Exception as DatabaseException;
use Alaress\Database\Statement\Query\IQuery;
use Alaress\Jitter\IJitter;
use Alaress_Date_Format;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Request;
use PDO;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Schoolbox\Learning\Assessment\DueWork\Submission\File\Model as DueWorkResponseFile;
use Schoolbox\Learning\Assessment\DueWork\Submission\Model as DueWorkResponse;
use Schoolbox\Learning\InlineGrader\Authenticator;
use Schoolbox\Learning\InlineGrader\Facade;
use Schoolbox\Resource\Storage\Mapper as StorageMapper;
use Schoolbox\Resource\Storage\Model as StorageModel;
use Schoolbox\User;

/**
 * Archival tool for Crocodoc documents.
 */
class Crocodoc
{

    const STATUS_PENDING = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';

    /**
     * @var IConnector
     */
    private $database;

    /**
     * @var ClientInterface
     */
    private $httpClient;

    /**
     * @var StorageMapper
     */
    private $storageMapper;

    /**
     * @var LoggerInterface
     */
    private $log;

    /**
     * @var IJitter
     */
    private $jitter;

    // -- Queries

    /**
     * Query for fetching the first remaining crocodocuments that are not archived.
     *
     * @var IQuery
     */
    private $selectUnarchivedCrocodocQuery;

    /**
     * Query for fetching the number of crocodocuments that are not archived.
     *
     * @var IQuery
     */
    private $countUnarchivedCrocodocQuery;

    /**
     * Query for fetching the first crocodocuments that previously failed archival.
     *
     * @var IQuery
     */
    private $selectFailedCrocodocQuery;

    /**
     * Query for fetching the number of crocodocuments that previously failed archival.
     *
     * @var IQuery
     */
    private $countFailedCrocodocQuery;

    /**
     * Query for fetching a log of what we've done.
     *
     * @var IQuery
     */
    private $updateLogQuery;

    /**
     * Query for fetching the most recent published feedback attached to the
     * response that a crocodocument belongs to.
     *
     * @var IQuery
     */
    private $selectAttachedFeedbackQuery;

    /**
     * Query for fetching the most recent published floating feedback for the
     * crocodoc's participant.
     *
     * This is only used if `$this->selectAttachedFeedbackQuery` returns nothing.
     *
     * @var IQuery
     */
    private $selectFloatingFeedbackQuery;

    /**
     * Query for inserting a new draft feedback for the crocodoc's participant.
     *
     * This is only used if `$this->selectFloatingFeedbackQuery` returns nothing.
     *
     * @var IQuery
     */
    private $insertNewFeedbackQuery;

    /**
     * Query for selecting the existing file for a crocodoc in a feedback.
     *
     * @var IQuery
     */
    private $selectExistingFeedbackFileQuery;

    /**
     * Query for attaching a new file for a crocodoc in a feedback.
     *
     * @var IQuery
     */
    private $insertNewFeedbackFileQuery;

    /**
     * Build the archiver.
     *
     * @param IConnector      $database
     * @param ClientInterface $httpClient
     * @param IJitter         $jitter
     * @param StorageMapper   $storageMapper
     * @param LoggerInterface $log
     */
    public function __construct(
        IConnector $database,
        ClientInterface $httpClient,
        IJitter $jitter,
        StorageMapper $storageMapper,
        LoggerInterface $log
    ) {
        $this->database = $database;
        $this->httpClient = $httpClient;
        $this->jitter = $jitter;
        $this->storageMapper = $storageMapper;
        $this->log = $log;
        $this->setupQueries();
    }

    /**
     * Prepare all required queries.
     */
    private function setupQueries()
    {
        $crocodocSelect = "
            SELECT
                ssf.id           AS fileId,
                ssf.realname     AS fileName,
                ssf.uuid         AS crocodocDocumentId,
                ss.id            AS responseId,
                ss.owner         AS studentId,
                ss.submitted_at  AS submittedDate,
                sb.id            AS boxId,
                sb.assessment_id AS assessmentId
        ";
        $crocodocFrom = "
            FROM student_submission_file ssf
            LEFT  JOIN student_submission_file_crocodoc_archive_log log ON ssf.id = log.student_submission_file_id
            INNER JOIN student_submission ss ON ssf.student_submission_id = ss.id AND ss.deleted_at IS NULL
            INNER JOIN submission_box sb ON ss.box_id = sb.id
            INNER JOIN assessment a ON sb.assessment_id = a.id AND a.deleted_at IS NULL
        ";
        $crocodocWhere = "
            WHERE ssf.uuid IS NOT NULL
            AND   ss.submitted_at IS NOT NULL
        ";
        $crocodocWhereUnarchived = $crocodocWhere . "AND log.status IS NULL OR log.status = 'pending'";
        $crocodocWhereFailed = $crocodocWhere . "AND log.status IS NOT NULL AND log.status IN ('processing', 'failed')";

        $this->selectUnarchivedCrocodocQuery = $this->database->prepareQuery("
            $crocodocSelect
            $crocodocFrom
            $crocodocWhereUnarchived
            ORDER BY ssf.created_at DESC
            LIMIT 10 OFFSET :offset
        ");

        $this->countUnarchivedCrocodocQuery = $this->database->prepareQuery("
             SELECT COUNT(*)
             $crocodocFrom
             $crocodocWhereUnarchived
        ");

        $this->selectFailedCrocodocQuery = $this->database->prepareQuery("
            $crocodocSelect
            $crocodocFrom
            $crocodocWhereFailed
            ORDER BY ssf.created_at DESC
            LIMIT 10 OFFSET :offset
        ");

        $this->countFailedCrocodocQuery = $this->database->prepareQuery("
             SELECT COUNT(*)
             $crocodocFrom
             $crocodocWhereFailed
        ");

        $this->updateLogQuery = $this->database->prepareQuery("
            REPLACE INTO student_submission_file_crocodoc_archive_log (
                student_submission_file_id,
                submission_return_file_id,
                crocodoc_document_id,
                status,
                message,
                date
            ) VALUES (
                :fileId,
                :feedbackFileId,
                :crocodocDocumentId,
                :status,
                :message,
                :now
            )
        ");

        $this->selectAttachedFeedbackQuery = $this->database->prepareQuery("
            SELECT id AS feedbackId
            FROM submission_return
            WHERE student_submission_id = :responseId
            AND   published_at IS NOT NULL
            ORDER BY published_at DESC
            LIMIT 1
        ");

        $this->selectFloatingFeedbackQuery = $this->database->prepareQuery("
            SELECT id AS feedbackId
            FROM submission_return
            WHERE owner  = :studentId
            AND   box_id = :boxId
            AND   published_at IS NOT NULL
            AND   student_submission_id IS NULL
            ORDER BY published_at DESC
            LIMIT 1
        ");

        $this->insertNewFeedbackQuery = $this->database->prepareQuery("
            INSERT INTO submission_return (
                box_id,
                marker,
                owner,
                created_at,
                published_at,
                updated_at
            ) VALUES (
                :boxId,
                :systemUserId,
                :studentId,
                :now,
                NULL,
                :now
            ) 
        ");

        $this->selectExistingFeedbackFileQuery = $this->database->prepareQuery("
            SELECT submission_return_file_id
            FROM student_submission_file_crocodoc_archive_log
            WHERE student_submission_file_id = :fileId
            AND   submission_return_file_id IS NOT NULL
        ");

        $this->insertNewFeedbackFileQuery = $this->database->prepareQuery("
            INSERT INTO submission_return_file (
                submission_return_id,
                hash,
                realname,
                created_at,
                updated_at
            ) VALUES (
                :feedbackId,
                :hash,
                :fileName,
                :now,
                :now
            )
        ");
    }

    /**
     * Start archiving
     *
     * @param User $systemUser
     * @param bool $dryRun
     * @param bool $retryFailed
     * @param int  $maxSkips Maximum tolerable skipped documents in a row.
     *                       Sequential failures suggest something broken
     *                       in either Crocodoc or our configuration.
     * @return array attempted:  number of items we attempted to archive
     *               successful: number of items successfully archived
     *               failed:     number that failed for whatever reason
     * @throws DatabaseException if a query went wrong
     */
    public function archive(User $systemUser, $dryRun = false, $retryFailed = false, $maxSkips = 3)
    {
        // Get a count
        if ($retryFailed) {
            $this->countFailedCrocodocQuery->execute();
            $crocodocCount = $this->countFailedCrocodocQuery->fetchColumn();
            $selectCrocodocQuery = $this->selectFailedCrocodocQuery;
        } else {
            $this->countUnarchivedCrocodocQuery->execute();
            $crocodocCount = $this->countUnarchivedCrocodocQuery->fetchColumn();
            $selectCrocodocQuery = $this->selectUnarchivedCrocodocQuery;
        }
        $this->log->notice(sprintf('%d Crocodoc documents require archival', $crocodocCount));


        // Go
        $successCount = 0;
        $failureCount = 0;
        // How many times has this failed in a row? Use this to determine jitter
        $sequentialFailures = 0;

        // Rerun the select query over and over again in batches
        $stepCount = $crocodocCount;
        while ($stepCount > 0) {
            // Fetch the next rowset.
            // For wet runs: offset by 0 always
            // (as each attempt marks the crocodoc as processed, and therefore not fetchable)
            // For dry runs: offset by the processed count each step
            $selectCrocodocQuery->bindInputs([
                'offset' => [
                    'value' => $dryRun ? $successCount + $failureCount : 0,
                    'type'  => PDO::PARAM_INT,
                ],
            ]);

            $breakCompletely = false;
            foreach ($selectCrocodocQuery->executeIterator() as $row) {
                $fileId = (int)$row['fileId'];
                $crocodocDocumentId = $row['crocodocDocumentId'];
                $filename = $row['fileName'];

                $stepCount--;
                $this->log->notice(
                    sprintf(
                        'Archiving Crocodoc document "%s" (%d/%d)',
                        $row['crocodocDocumentId'],
                        $successCount + $failureCount + 1,
                        $crocodocCount
                    )
                );


                // What feedback does this belong in?
                $feedbackId = $this->findFeedbackId(
                    (int)$row['responseId'],
                    (int)$row['studentId'],
                    (int)$row['boxId'],
                    $systemUser->getId(),
                    $dryRun
                );


                // Log where we're at
                if (!$dryRun) {
                    $this->logToDatabase($fileId, null, $crocodocDocumentId, self::STATUS_PROCESSING, '');
                }


                // Connect to the Crocodoc thing
                $facade = $this->buildFacade(
                    $systemUser,
                    $fileId,
                    $crocodocDocumentId,
                    $filename,
                    new Alaress_Date_Format($row['submittedDate'], Alaress_Date_Format::DATABASE_FORMAT)
                );


                // Try to download the annotated Crocodocument
                try {
                    /** @var ResponseInterface $response */
                    $response = $this->jitter->retry(function () use ($facade, $dryRun) {
                        try {
                            $response = $this->httpClient->send(new Request('GET', $facade->getDownloadUrl()));
                        } catch (RuntimeException $e) {
                            $this->log->warning(
                                sprintf(
                                    'Exception raised when fetching Crocodoc document (code: %d, message: "%s")',
                                    $e->getCode(),
                                    $e->getMessage()
                                )
                            );
                            throw $e;
                        }
                        return $response;
                    });
                } catch (\Exception $e) {
                    if (!$dryRun) {
                        $this->logToDatabase(
                            $fileId,
                            null,
                            $crocodocDocumentId,
                            self::STATUS_FAILED,
                            $e->getMessage()
                        );
                    }
                    $this->log->warning('Fetch failed too many times: skipping this document!');
                    $failureCount++;
                    $sequentialFailures++;

                    // Break if sequential failures amount is past a threshold
                    if ($sequentialFailures >= $maxSkips) {
                        $this->log->error('Too many sequential failures: aborting!');
                        $breakCompletely = true;
                        break;
                    }

                    continue;
                }


                // Dry run? Break out
                if ($dryRun) {
                    $successCount++;
                    $sequentialFailures = 0;
                    $this->log->info('Crocodoc document would have been successfully archived');
                    continue;
                }


                // Now store it and create a feedback for it
                $storageModel = $this->uploadCrocodocToStorage($response->getBody(), $filename);
                $feedbackFileId = $this->archiveCrocodocInFeedback($storageModel, $fileId, $feedbackId, $filename);


                // Done
                // Have a helpful link to the inline grader so you can see the doc, too
                $this->log->info(
                    sprintf(
                        'Crocodoc document successfully archived; visible at: /learning/assessments/%d/%d/responses/',
                        $row['assessmentId'],
                        $row['studentId']
                    )
                );

                $this->logToDatabase($fileId, $feedbackFileId, $crocodocDocumentId, self::STATUS_COMPLETED, '');
                $successCount++;
                $sequentialFailures = 0;
            }
            $selectCrocodocQuery->close();


            // Break the loop completely if we've had too many failures
            if ($breakCompletely) {
                break;
            }
        }


        // At the end of the day: log something
        $message = sprintf(
            'Archival completed - %d archived, %d failed, %d to do',
            $successCount,
            $failureCount,
            $crocodocCount - $successCount - $failureCount
        );
        if ($failureCount > 0) {
            $this->log->warning($message);
        } else {
            $this->log->notice($message);
        }
        return [
            'attempted'  => $successCount + $failureCount,
            'successful' => $successCount,
            'failed'     => $failureCount,
        ];
    }

    /**
     * Build a facade as required for downloading a Crocodoc document.
     *
     * @param User                $systemUser
     * @param int                 $fileId
     * @param string              $crocodocDocumentId
     * @param string              $filename
     * @param Alaress_Date_Format $submittedDate
     * @return Facade
     */
    private function buildFacade(User $systemUser, $fileId, $crocodocDocumentId, $filename, $submittedDate)
    {
        $response = (new DueWorkResponse())
            ->setSubmittedAt($submittedDate);
        $file = (new DueWorkResponseFile())
            ->setId($fileId)
            ->setCrocodocId($crocodocDocumentId)
            ->setFilename($filename);
        return new Facade(new Authenticator(), $response, $file, $systemUser);
    }

    /**
     * Uploads a Crocodoc document into storage, for later attachment to a
     * feedback file.
     *
     * @param StreamInterface $result
     * @param string          $filename
     * @return StorageModel
     */
    private function uploadCrocodocToStorage(StreamInterface $result, $filename)
    {
        return $this->storageMapper->acceptStream('return_' . $filename . '.pdf', $result->getContents());
    }

    /**
     * Finds the feedback in which to upload the Crocodoc document. In order of preference:
     * 1. most recent feedback for this response
     * 2. most recent floating feedback for this participant
     * 3. a new draft feedback for this participant (not created if `$dryRun` is on)
     *
     * @param int  $responseId   The ID of the student's response
     * @param int  $studentId    The student's ID
     * @param int  $boxId        The ID of the submission box to which the response belongs to
     * @param int  $systemUserId A user ID to use as the owner for a new feedback, if required
     * @param bool $dryRun
     * @return int The PK of the feedback ID in which to upload
     * @throws DatabaseException if a query breaks
     */
    private function findFeedbackId($responseId, $studentId, $boxId, $systemUserId, $dryRun)
    {
        $this->selectAttachedFeedbackQuery->execute(['responseId' => $responseId,]);
        $feedbackId = $this->selectAttachedFeedbackQuery->fetchColumn();
        if ($feedbackId) {
            $this->log->debug(sprintf('Attaching document to most recent non-floating feedback (id: %d)', $feedbackId));
        } else {
            $this->log->debug('No non-floating feedback found - searching for floating feedback');
            $this->selectFloatingFeedbackQuery->execute([
                'studentId' => $studentId,
                'boxId'     => $boxId,
            ]);
            $feedbackId = $this->selectFloatingFeedbackQuery->fetchColumn();
            if ($feedbackId) {
                $this->log->debug(sprintf('Attaching document to most recent floating feedback (id: %d)', $feedbackId));
            } else {
                $this->log->debug('No floating feedback found - creating a new draft floating feedback');
                // create one, if not a dry run
                if ($dryRun) {
                    $this->log->debug(sprintf('Attaching document to new draft floating feedback'));
                } else {
                    $this->insertNewFeedbackQuery->execute([
                        'systemUserId' => $systemUserId,
                        'studentId'    => $studentId,
                        'boxId'        => $boxId,
                        'now'          => Alaress_Date_Format::now(),
                    ]);
                    $feedbackId = $this->database->lastInsertId();
                    $this->log->debug(
                        sprintf(
                            'Attaching document to new draft floating feedback (id: %d)',
                            $feedbackId
                        )
                    );
                }
            }
        }
        return (int)$feedbackId;
    }

    /**
     * Archives the uploaded Crocodoc document in a feedback.
     *
     * @param StorageModel $storageModel
     * @param int          $fileId
     * @param int          $feedbackId
     * @param string       $filename
     * @return int The PK of the new feedback file ID in which the Crocodoc was uploaded
     * @throws DatabaseException if a query breaks
     */
    private function archiveCrocodocInFeedback(StorageModel $storageModel, $fileId, $feedbackId, $filename)
    {
        // Attach to the feedback, if it isn't already
        $this->selectExistingFeedbackFileQuery->execute(['fileId' => $fileId,]);
        $feedbackFileId = $this->selectExistingFeedbackFileQuery->fetchColumn();
        if (!$feedbackFileId) {
            $this->insertNewFeedbackFileQuery->execute([
                'feedbackId' => $feedbackId,
                'hash'       => $storageModel->getHash(),
                'fileName'   => $filename,
                'now'        => Alaress_Date_Format::now(),
            ]);
            $feedbackFileId = $this->database->lastInsertId();
            $this->log->info('Crocodoc document successfully attached to feedback');
        } else {
            $this->log->info('Crocodoc document already attached to feedback - not attaching again');
        }
        return (int)$feedbackFileId;
    }

    /**
     * Log what is happening with the archive process to the database.
     *
     * @param int      $fileId
     * @param int|null $feedbackFileId
     * @param string   $crocodocDocumentId
     * @param string   $status
     * @param string   $message
     *
     * @throws DatabaseException if the log query breaks
     */
    private function logToDatabase($fileId, $feedbackFileId, $crocodocDocumentId, $status, $message)
    {
        $this->updateLogQuery->execute([
            'fileId'             => $fileId,
            'feedbackFileId'     => $feedbackFileId,
            'crocodocDocumentId' => $crocodocDocumentId,
            'status'             => $status,
            'message'            => $message,
            'now'                => Alaress_Date_Format::now(),
        ]);
    }
}
