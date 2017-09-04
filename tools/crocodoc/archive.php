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
use Schoolbox\MapperFactory;
use Schoolbox\UserMapper;

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

    $httpClient = new HttpClient();

    /** @var MapperFactory $mapperFactory */
    $mapperFactory = $di->get('mapperFactory');

    /** @var UserMapper $userMapper */
    $userMapper = $mapperFactory->get(UserMapper::class);
    // If I die, I can do nothing
    $systemUser = $userMapper->findOneByColumn('username', 'alaress');

    /** @var IConnector $db */
    $db = $di->get('databaseInternal');

    // Fetch first 10 remaining crocodocuments
    $selectCrocodocQuery = $db->prepareQuery("
        SELECT
            ssf.id          AS fileId,
            ssf.realname    AS fileName,
            ssf.uuid        AS crocodocDocumentId,
            ss.owner        AS studentId,
            ss.submitted_at AS submittedDate,
            sb.id           AS boxId
        FROM student_submission_file ssf
        INNER JOIN student_submission ss ON ssf.student_submission_id = ss.id
        INNER JOIN submission_box sb ON ss.box_id = sb.id
        WHERE ssf.uuid IS NOT NULL
        AND   ss.submitted_at IS NOT NULL
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
        AND   ss.submitted_at IS NOT NULL
    ")->fetchColumn();
    $log->notice(sprintf('%d Crocodoc documents require archival', $crocodocCount));

    // Go thru the entire list
    foreach ($selectCrocodocQuery->executeIterator() as $row) {
        // Do stuff with the doc
        $log->notice(sprintf('Archiving Crocodoc document "%s"...', $row['crocodocDocumentId']));

        // Get the crocodocument
        // use getDownloadUrl
        // need mappers to use facade anyway

        // Finagle together the pieces the facade purports to require
        $response = (new DueWorkResponse())
            ->setSubmittedAt(new Alaress_Date_Format($row['submittedDate'], Alaress_Date_Format::DATABASE_FORMAT));
        $file     = (new DueWorkResponseFile())
            ->setId($row['fileId'])
            ->setCrocodocId($row['crocodocDocumentId'])
            ->setFilename($row['fileName']);
        $facade   = new Facade(new Authenticator(), $response, $file, $systemUser);

        // Try to download the Crocodocument
        $result   = $httpClient->get($facade->getDownloadUrl())->send();
        $contents = $result->getBody(true);

        file_put_contents(sprintf('%s.pdf', $row['fileId']), $contents);
    }

    exit;
});
