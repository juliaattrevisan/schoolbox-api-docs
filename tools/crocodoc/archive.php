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
use Psr\Log\LoggerInterface as Logger;
use React\EventLoop\LoopInterface;
use Schoolbox\Assessment\Component\SubmissionBox\Submission\SingleFile\Mapper as ResponseFileMapper;
use Schoolbox\MapperFactory;

require_once __DIR__ . '/../../library/Bootstrap.php';

$options = getopt('v', ['skip-duplicates', 'enable-eportfolio', 'dry-run']);
$kernel = new Schoolbox\Application\Daemon\Kernel(
    'Crocodoc Archiver',
    [
        'verbose' => isset($options['v']) ? $options['v'] : null,
        'errors'  => E_ALL,
    ]
);

$kernel->run(function (Di\Container $di, LoopInterface $loop, Logger $log, array $options) {

    /** @var IConnector $db */
    $db = $di->get('databaseInternal');

    // Fetch first 10 remaining crocodocuments
    $selectCrocodocQuery = $db->prepareQuery("
        SELECT
            ssf.id   AS fileId,
            ssf.uuid AS crocodocDocumentId,
            ss.owner AS studentId,
            sb.id    AS boxId
        FROM student_submission_file ssf
        INNER JOIN student_submission ss ON ssf.student_submission_id = ss.id
        INNER JOIN submission_box sb ON ss.box_id = sb.id
        WHERE ssf.uuid IS NOT NULL
        ORDER BY ssf.created_at DESC
        LIMIT 10 OFFSET 0
    ");

    // Remove crocodocument link
    $clearCrocodocQuery = $db->prepareQuery("UPDATE student_submission_file SET uuid = NULL WHERE id = :fileId");

    // Which return does the crocodocument get put in?
    $selectMostRecentReturnQuery = $db->prepareQuery("
        SELECT id
        FROM submission_return
        WHERE owner  = :studentId
        AND   box_id = :boxId
        AND   published_at IS NOT NULL
        ORDER BY published_at DESC
        LIMIT 1
    ");

    // Get a count
    $crocodocCount = $db->executeQuery("
        SELECT COUNT(*)
        FROM student_submission_file ssf
        INNER JOIN student_submission ss ON ssf.student_submission_id = ss.id
        INNER JOIN submission_box sb ON ss.box_id = sb.id
        WHERE ssf.uuid IS NOT NULL
    ")->fetchColumn();
    $log->notice(sprintf('%d Crocodoc documents require archival', $crocodocCount));

    // Go thru the entire list
    foreach ($selectCrocodocQuery->executeIterator() as $row) {
        // Do stuff with the doc
        $log->notice(sprintf('Archiving Crocodoc document "%s"...', $row['crocodocDocumentId']));


    }

//    /** @var MapperFactory $mapperFactory */
//    $mapperFactory = $di->get('mapperFactory');
//
//    // Fetch a bunch of due work response files
//    $responseFileMapper = $mapperFactory->get(ResponseFileMapper::class);

    exit;
});
