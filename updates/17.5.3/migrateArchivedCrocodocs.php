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
use Alaress\Jitter\Jitter;
use Alaress\Random\FloatGenerator;
use Aura\Di;
use GuzzleHttp\Client as HttpClient;
use Psr\Log\LoggerInterface as Logger;
use React\EventLoop\LoopInterface;
use Schoolbox\Learning\InlineGrader\Crocodoc\Archiver as CrocodocArchiver;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\MapperFactory;
use Schoolbox\Resource\Storage\Mapper as StorageMapper;
use Schoolbox\UserMapper;

$options = require_once __DIR__ . '/../../conf/core/require-cli.php';

spl_autoload_call('\\Schoolbox\\Application\\Daemon');
$kernel = new Schoolbox\Application\Daemon\Kernel(
    'Crocodoc Archive Migrator',
    [
        'verbose' => [false, false, false],
        'errors'  => E_ALL,
    ]
);

$kernel->run(function (Di\Container $di, LoopInterface $loop, Logger $log, array $options) {

    /** @var IConnector $db */
    $db = $di->get('databaseInternal');

    $selectSystemUserQuery = $db->prepareQuery("SELECT id FROM user WHERE username='alaress' AND deleted_at IS NULL");

    // Get return ID, student ID, assessment ID
    $selectArchivedFeedbackQuery = $db->prepareQuery("
        SELECT
            sr.id     AS returnId,
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
        SELECT sr.id
        FROM  submission_return sr
        WHERE sr.deleted_at IS NULL
        AND   sr.marker != :systemUserId
        AND   sr.box_id = :dueWorkId
        AND   sr.owner  = :studentId
        ORDER BY created_at DESC
        LIMIT 1
    ");
});
