<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2017, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\InlineGrader\Archiver;

use Alaress\Database\Connector\IConnector;
use Alaress\Database\Statement\Query\IQuery;
use Guzzle\Http\Client as HttpClient;
use Psr\Log\LoggerInterface;
use Schoolbox\User;

/**
 * Archival tool for Crocodoc documents.
 */
class Crocodoc
{
    /**
     * @var IConnector
     */
    private $database;

    /**
     * @var HttpClient
     */
    private $httpClient;

    /**
     * @var LoggerInterface
     */
    private $log;

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
     * @param HttpClient      $httpClient
     * @param LoggerInterface $log
     */
    public function __construct(IConnector $database, HttpClient $httpClient, LoggerInterface $log)
    {
        $this->database   = $database;
        $this->httpClient = $httpClient;
        $this->log        = $log;
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
            INNER JOIN student_submission ss ON ssf.student_submission_id = ss.id
            INNER JOIN submission_box sb ON ss.box_id = sb.id
        ";
        $crocodocWhere = "
            WHERE ssf.uuid IS NOT NULL
            AND   ss.submitted_at IS NOT NULL
        ";
        $crocodocWhereUnarchived = $crocodocWhere . "AND log.status IS NULL";
        $crocodocWhereFailed    = $crocodocWhere . "AND log.status = 'failed'";

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
            SELECT id
            FROM submission_return_file
            WHERE submission_return_id = :feedbackId
            AND   hash                 = :hash
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

    public function archive(User $systemUser, $dryRun = false, $rescanFailed = false)
    {
        //
    }
}
