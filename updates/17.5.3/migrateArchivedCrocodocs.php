#!/usr/bin/php
<?php
/**
 * Migrates archived Crocodoc documents.
 *
 * The archive script `tools/crocodoc/archive.php` originally created a new
 * floating feedback if there was no *published* feedback for that item.
 *
 * This decision caused existing draft feedback to be clobbered by a new, empty
 * draft feedback; this script will, if there's a feedback actually created by
 * an instructor:
 * * soft-delete that empty feedback
 * * move its file to the most recent feedback not created by the archive script
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2017, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

use Alaress\Database\Connector\IConnector;
use Aura\Di;
use Psr\Log\LoggerInterface as Logger;
use React\EventLoop\LoopInterface;

$options = require_once __DIR__ . '/../../conf/core/require-cli.php';

spl_autoload_call('\\Schoolbox\\Application\\Daemon');
$kernel = new Schoolbox\Application\Daemon\Kernel(
    'Crocodoc Archive Migrator',
    [
        'verbose' => [false, false, false],
        'errors'  => E_ALL,
    ]
);

$kernel->run(function (Di\Container $di, LoopInterface $loop, Logger $log) {

    /** @var IConnector $db */
    $db = $di->get('databaseInternal');

    // Get the system user
    $selectSystemUserQuery = $db->prepareQuery("SELECT id FROM user WHERE username='alaress' AND deleted_at IS NULL");
    $selectSystemUserQuery->execute();
    $systemUserId = $selectSystemUserQuery->fetchColumn();
    if (!$systemUserId) {
        $log->error('No "alaress" user found; cannot identify feedback to archive!');
        exit(1);
    }

    // Get return ID, student ID, assessment ID
    $selectArchivedFeedbackQuery = $db->prepareQuery("
        SELECT
            srf.id    AS fileId,
            sr.id     AS archiveFeedbackId,
            sr.owner  AS studentId,
            sr.box_id AS dueWorkId
        FROM       student_submission_file_crocodoc_archive_log log
        INNER JOIN submission_return_file                       srf ON log.submission_return_file_id = srf.id
        INNER JOIN submission_return                            sr  ON srf.submission_return_id      = sr.id
        WHERE sr.published_at IS NULL
        AND   sr.marker = :systemUserId
    ");

    // Get ID of most recent feedback for participant NOT created by archive tool
    $selectMostRecentFeedbackQuery = $db->prepareQuery("
        SELECT sr.id AS actualFeedbackId
        FROM  submission_return sr
        WHERE sr.deleted_at IS NULL
        AND   sr.marker != :systemUserId
        AND   sr.box_id = :dueWorkId
        AND   sr.owner  = :studentId
        ORDER BY created_at DESC
        LIMIT 1
    ");

    // Move file to most recent feedback
    $moveCrocodocArchiveFileQuery = $db->prepareQuery("
        UPDATE submission_return_file
        SET    submission_return_id = :mostRecentFeedbackId
        WHERE  id                   = :fileId
    ");

    // Delete archived feedback
    $deleteArchiveFeedbackQuery = $db->prepareQuery("
        UPDATE submission_return
        SET    deleted_at = COALESCE(created_at, NOW())
        WHERE  id         = :archiveFeedbackId
    ");


    // Go
    $selectArchivedFeedbackQuery->execute(['systemUserId' => $systemUserId]);
    $migrateCount = $selectArchivedFeedbackQuery->rowCount();
    $successCount = 0;
    $failureCount = 0;
    foreach ($selectArchivedFeedbackQuery->fetchAll() as $i => $row) {

        $log->notice(
            sprintf(
                'Migrating archived feedback #%d (student #%d, due work #%d, %d/%d)',
                $row['archiveFeedbackId'],
                $row['studentId'],
                $row['dueWorkId'],
                $i + 1,
                $migrateCount
            )
        );

        // Figure out which feedback to attach to
        $selectMostRecentFeedbackQuery->execute([
            'systemUserId' => $systemUserId,
            'dueWorkId'    => $row['dueWorkId'],
            'studentId'    => $row['studentId'],
        ]);
        $mostRecentFeedbackId = $selectMostRecentFeedbackQuery->fetchColumn();
        if (empty($mostRecentFeedbackId)) {
            $log->debug('No other feedback exists for student/work; skipping');
            $successCount++;
            continue;
        }

        // Move it
        $log->debug(sprintf('Migrating Crocodoc archive to most recent feedback #%d', $mostRecentFeedbackId));
        $moveCrocodocArchiveFileQuery->execute([
            'mostRecentFeedbackId' => $mostRecentFeedbackId,
            'fileId'               => $row['fileId'],
        ]);

        // Delete old feedback
        $log->debug(sprintf('Deleting archived feedback #%d', $row['archiveFeedbackId']));
        $deleteArchiveFeedbackQuery->execute([
            'archiveFeedbackId' => $row['archiveFeedbackId'],
        ]);

        $log->info('Feedback successfully migrated');
        $successCount++;
    }

    // Output a message.
    // Currently no failure state, but it's here anyway.
    $message = sprintf(
        'Migration completed - %d archived, %d failed, %d to do',
        $successCount,
        $failureCount,
        $migrateCount - $successCount - $failureCount
    );
    if ($failureCount > 0) {
        $log->warning($message);
    } else {
        $log->notice($message);
    }
});
