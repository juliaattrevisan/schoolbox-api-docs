#!/usr/bin/php
<?php
/**
 * Archives Crocodoc documents.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2017, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

use Alaress\Database\Connector\IConnector;
use Aura\Di;
use Guzzle\Http\Client as HttpClient;
use Psr\Log\LoggerInterface as Logger;
use React\EventLoop\LoopInterface;
use Schoolbox\Learning\Assessment\DueWork\Submission\Model as DueWorkResponse;
use Schoolbox\Learning\Assessment\DueWork\Submission\File\Model as DueWorkResponseFile;
use Schoolbox\Learning\InlineGrader\Authenticator;
use Schoolbox\Learning\InlineGrader\Facade;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\MapperFactory;
use Schoolbox\Resource\Storage\Mapper as StorageMapper;
use Schoolbox\UserMapper;

require_once __DIR__ . '/../../library/Bootstrap.php';

$options = getopt('v', ['dry-run', 'rescan-failed']);
$kernel = new Schoolbox\Application\Daemon\Kernel(
    'Crocodoc Archiver',
    [
        'verbose'      => isset($options['v']) ? $options['v'] : null,
        'errors'       => E_ALL,
        'dryRun'       => isset($options['dry-run']),
        'rescanFailed' => isset($options['rescan-failed']),
    ]
);

$kernel->run(function (Di\Container $di, LoopInterface $loop, Logger $log, array $options) {

    $dryRun = $options['dryRun'];
    if ($dryRun) {
        $log->notice('Performing dry run - no documents will be archived');
    }

    $rescanFailed = $options['rescanFailed'];
    if ($dryRun) {
        $log->notice('Attempting to rescan documents that have failed processing previously');
    }

    /**
     * @var MapperFactory $mapperFactory
     * @var StorageMapper $storageMapper
     * @var UserMapper    $userMapper
     */
    $httpClient    = new HttpClient();
    $mapperFactory = $di->get('mapperFactory');
    $userMapper    = $mapperFactory->get(UserMapper::class);
    $storageMapper = $mapperFactory->get(StorageMapper::class);


    // Figure out a system user
    try {
        $systemUser = $userMapper->findOneByColumn('username', 'alaress');
    } catch (MapperException $e) {
        // Deleted user _might_ be fine, but let's not take the risk
        $log->error('User "alaress" not found! New due work feedback and files will be created for this user, so they must exist.');
        exit(1);
    }

    /** @var IConnector $db */
    $db = $di->get('databaseInternal');

    // Fetch first x remaining crocodocuments
    $selectCrocodocJoin  = "
        LEFT  JOIN student_submission_file_crocodoc_archive_log log ON ssf.id = log.student_submission_file_id
        INNER JOIN student_submission ss ON ssf.student_submission_id = ss.id
        INNER JOIN submission_box sb ON ss.box_id = sb.id
    ";
    $selectCrocodocWhere = "
        WHERE ssf.uuid IS NOT NULL
        AND   ss.submitted_at IS NOT NULL
    ";
    if ($rescanFailed) {
        $selectCrocodocWhere .= "AND log.status = 'failed'";
    } else {
        $selectCrocodocWhere .= 'AND log.status IS NULL';
    }
    $selectCrocodocQuery = $db->prepareQuery("
        SELECT
            ssf.id          AS fileId,
            ssf.realname    AS fileName,
            ssf.uuid        AS crocodocDocumentId,
            ss.id           AS responseId,
            ss.owner        AS studentId,
            ss.submitted_at AS submittedDate,
            sb.id           AS boxId
        FROM student_submission_file ssf
        $selectCrocodocJoin
        $selectCrocodocWhere
        ORDER BY ssf.created_at DESC
        LIMIT 10 OFFSET 0
    ");

    // Log
    $updateLogQuery = $db->prepareQuery("
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


    // Which feedback does the crocodocument get put in?
    // This query fetches the most recent published feedback attached to the response.
    $selectAttachedFeedbackQuery = $db->prepareQuery("
        SELECT id AS feedbackId
        FROM submission_return
        WHERE student_submission_id = :responseId
        AND   published_at IS NOT NULL
        ORDER BY published_at DESC
        LIMIT 1
    ");

    // This query fetches the most recent published floating feedback for this assessment,
    // for when there's no directly attached feedback.
    $selectFloatingFeedbackQuery = $db->prepareQuery("
        SELECT id AS feedbackId
        FROM submission_return
        WHERE owner  = :studentId
        AND   box_id = :boxId
        AND   published_at IS NOT NULL
        AND   student_submission_id IS NULL
        ORDER BY published_at DESC
        LIMIT 1
    ");

    // This query inserts a new draft feedback, for if there is no published feedback at all.
    $insertNewFeedbackQuery = $db->prepareQuery("
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

    // Query for figuring out if this file is already attached to this feedback
    $selectExistingFeedbackFileQuery = $db->prepareQuery("
        SELECT id
        FROM submission_return_file
        WHERE submission_return_id = :feedbackId
        AND   hash                 = :hash
    ");

    // Query for attaching a new file to the feedback.
    $insertNewFeedbackFileQuery = $db->prepareQuery("
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


    // Get a count
    $crocodocCount = $db->executeQuery("
        SELECT COUNT(*)
        FROM student_submission_file ssf
        $selectCrocodocJoin
        $selectCrocodocWhere
    ")->fetchColumn();
    $log->notice(sprintf('%d Crocodoc documents require archival', $crocodocCount));


    // Go
    $successCount = 0;
    $failureCount = 0;
    foreach ($selectCrocodocQuery->executeIterator() as $row) {

        $log->notice(sprintf('Archiving Crocodoc document "%s"...', $row['crocodocDocumentId']));


        // Figure out where to put the document. In order of preference:
        // 1. most recent feedback for this response
        // 2. most recent feedback with no response
        // 3. a new draft feedback
        $selectAttachedFeedbackQuery->execute([
            'responseId' => $row['responseId'],
        ]);
        $feedbackId = $selectAttachedFeedbackQuery->fetchColumn();
        if ($feedbackId) {
            $log->info(sprintf('Attaching document to most recent non-floating feedback (id: %d)', $feedbackId));
        } else {
            $log->debug('No non-floating feedback found - searching for non-floating feedback');
            $selectFloatingFeedbackQuery->execute([
                'studentId' => $row['studentId'],
                'boxId'     => $row['boxId'],
            ]);
            $feedbackId = $selectFloatingFeedbackQuery->fetchColumn();
            if ($feedbackId) {
                $log->info(sprintf('Attaching document to most recent floating feedback (id: %d)', $feedbackId));
            } else {
                $log->debug('No floating feedback found - creating a new draft floating feedback');
                // create one, if not a dry run
                if ($dryRun) {
                    $log->info(sprintf('Attaching document to new draft floating feedback'));
                } else {
                    $insertNewFeedbackQuery->execute([
                        'systemUserId' => $systemUser->getId(),
                        'studentId'    => $row['studentId'],
                        'boxId'        => $row['boxId'],
                        'now'          => Alaress_Date_Format::now(),
                    ]);
                    $feedbackId = $db->lastInsertId();
                    $log->info(sprintf('Attaching document to new draft floating feedback (id: %d)', $feedbackId));
                }
            }
        }
        if ($dryRun) {
            $successCount++;
            $log->info('Crocodoc document successfully attached to feedback');
            continue;
        }


        // All systems go
        $updateLogQuery->execute([
            'fileId'             => $row['fileId'],
            'feedbackFileId'     => null,
            'crocodocDocumentId' => $row['crocodocDocumentId'],
            'status'             => 'processing',
            'message'            => '',
            'now'                => Alaress_Date_Format::now(),
        ]);

        // Finagle together the pieces the facade requires
        $response = (new DueWorkResponse())
            ->setSubmittedAt(new Alaress_Date_Format($row['submittedDate'], Alaress_Date_Format::DATABASE_FORMAT));
        $file = (new DueWorkResponseFile())
            ->setId($row['fileId'])
            ->setCrocodocId($row['crocodocDocumentId'])
            ->setFilename($row['fileName']);
        $facade = new Facade(new Authenticator(), $response, $file, $systemUser);


        // Try to download the annotated Crocodocument
        try {
            $result = $httpClient->get($facade->getDownloadUrl())->send();
        } catch (RuntimeException $e) {
            // skip this one; consider jittering
            $log->warning(
                sprintf(
                    'Exception raised when fetching Crocodoc document: skipping! (code: %d, message: "%s")',
                    $e->getCode(),
                    $e->getMessage()
                )
            );
            $updateLogQuery->execute([
                'fileId'             => $row['fileId'],
                'feedbackFileId'     => null,
                'crocodocDocumentId' => $row['crocodocDocumentId'],
                'status'             => 'failed',
                'message'            => $e->getMessage(),
                'now'                => Alaress_Date_Format::now(),
            ]);
            $failureCount++;
            continue;
        }
        if ($result->isError()) {
            // skip this one; consider jittering
            // Also consider exiting completely if there's too many errors
            $response = json_decode($result, true);
            $log->warning(
                sprintf(
                    'Error occurred when fetching Crocodoc document: skipping! (code: %d, message: "%s")',
                    $response['error'],
                    $result->getMessage()
                )
            );
            $updateLogQuery->execute([
                'fileId'             => $row['fileId'],
                'feedbackFileId'     => null,
                'crocodocDocumentId' => $row['crocodocDocumentId'],
                'status'             => 'failed',
                'message'            => $response['error'],
                'now'                => Alaress_Date_Format::now(),
            ]);
            $failureCount++;
            continue;
        }
        $log->info('Crocodoc document successfully retrieved');


        // Shoehorn it into storage
        $matches = [];
        if (preg_match('/filename="(.*?)"$/', $result->getContentDisposition(), $matches)) {
            list(, $filename) = $matches;
        } else {
            // Make something up
            $filename = 'return_' . $file->getFilename() . '.pdf';
        }
        $storageModel = $storageMapper->acceptStream($filename, $result->getBody());


        // Attach to the feedback, if it isn't already
        $selectExistingFeedbackFileQuery->execute([
            'feedbackId' => $feedbackId,
            'hash'       => $storageModel->getHash(),
        ]);
        $feedbackFileId = $selectExistingFeedbackFileQuery->fetchColumn();
        if (!$feedbackFileId) {
            $insertNewFeedbackFileQuery->execute([
                'feedbackId' => $feedbackId,
                'hash'       => $storageModel->getHash(),
                'fileName'   => $row['fileName'],
                'now'        => Alaress_Date_Format::now(),
            ]);
            $feedbackFileId = $db->lastInsertId();
            $log->info('Crocodoc document successfully attached to feedback');
        } else {
            $log->info('Crocodoc document already attached to feedback - not attaching again');
        }

        $updateLogQuery->execute([
            'fileId'             => $row['fileId'],
            'feedbackFileId'     => $feedbackFileId,
            'crocodocDocumentId' => $row['crocodocDocumentId'],
            'status'             => 'completed',
            'message'            => '',
            'now'                => Alaress_Date_Format::now(),
        ]);
        $successCount++;
    }


    // At the end of the day: log something
    $message = sprintf(
        'Archival completed - %d archived, %d failed, %d to do',
        $successCount,
        $failureCount,
        $crocodocCount - $successCount - $failureCount
    );
    if ($failureCount > 0) {
        $log->warning($message);
    } else {
        $log->notice($message);
    }
    exit;
});
