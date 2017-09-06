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
use Alaress\Jitter\Jitter;
use Alaress\Random\FloatGenerator;
use Aura\Di;
use GuzzleHttp\Client as HttpClient;
use Psr\Log\LoggerInterface as Logger;
use React\EventLoop\LoopInterface;
use Schoolbox\Learning\InlineGrader\Archiver\Crocodoc as CrocodocArchiver;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\MapperFactory;
use Schoolbox\Resource\Storage\Mapper as StorageMapper;
use Schoolbox\UserMapper;

require_once __DIR__ . '/../../library/Bootstrap.php';

$options = getopt('v', ['dry-run', 'rescan-failed', 'help']);
$kernel = new Schoolbox\Application\Daemon\Kernel(
    'Crocodoc Archiver',
    [
        'verbose'      => isset($options['v']) ? $options['v'] : null,
        'errors'       => E_ALL,
        'dryRun'       => isset($options['dry-run']),
        'rescanFailed' => isset($options['rescan-failed']),
    ]
);

if (isset($options['help'])) {
    echo <<<HELP
Archives Crocodoc documents.

Usage: archive.php -v[vv] [--dry-run] [--rescan-failed]
    -v:                  Verbosity level, -vvv: most verbose
    --dry-run:           if set, don't actually archive anything, but show what
                         would be archived.
    --rescan-failed:     if set, attempt to archive documents that failed
                         archive in a previous run of this script.
                         By default, it will only attempt to archive documents
                         that have not yet been attempted.

HELP;
    exit;
}

$kernel->run(function (Di\Container $di, LoopInterface $loop, Logger $log, array $options) {

    $dryRun = $options['dryRun'];
    if ($dryRun) {
        $log->notice('Performing dry run - no documents will be archived');
    }

    $rescanFailed = $options['rescanFailed'];
    if ($rescanFailed) {
        $log->notice('Attempting to rescan documents that have failed processing previously');
    }

    /**
     * @var MapperFactory $mapperFactory
     * @var StorageMapper $storageMapper
     * @var UserMapper    $userMapper
     */
    $httpClient = new HttpClient();
    $mapperFactory = $di->get('mapperFactory');
    $userMapper = $mapperFactory->get(UserMapper::class);
    $storageMapper = $mapperFactory->get(StorageMapper::class);
    $jitter = new Jitter(new FloatGenerator(), 3, 500);


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

    $archiver = new CrocodocArchiver($db, $httpClient, $jitter, $storageMapper, $log);
    $result = $archiver->archive($systemUser, $dryRun, $rescanFailed);
    if ($result['failed'] > 0) {
        exit(1);
    }
    exit(0);
});
