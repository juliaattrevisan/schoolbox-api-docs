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
use Schoolbox\Learning\InlineGrader\Crocodoc\Archiver as CrocodocArchiver;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\MapperFactory;
use Schoolbox\Resource\Storage\Mapper as StorageMapper;
use Schoolbox\UserMapper;

$fields  = ['dry-run', 'retry-failed', 'help', 'max-attempts:', 'max-skips:'];
$options = require_once __DIR__ . '/../../conf/core/require-cli.php';

spl_autoload_call('\\Schoolbox\\Application\\Daemon');
$kernel = new Schoolbox\Application\Daemon\Kernel(
    'Crocodoc Archiver',
    [
        'verbose'     => isset($options['v']) ? $options['v'] : null,
        'errors'      => E_ALL,
        'dryRun'      => isset($options['dry-run']),
        'retryFailed' => isset($options['retry-failed']),
        'maxAttempts' => isset($options['max-attempts']) ? $options['max-attempts'] : null,
        'maxSkips'    => isset($options['max-skips']) ? $options['max-skips'] : null,
    ]
);

if (isset($options['help'])) {
    echo <<<HELP
Archives Crocodoc documents.

Usage: archive.php -v[vv] [--dry-run] [--retry-failed] [--max-attempts=3] [--max-skips=3]
    -v:                  Verbosity level, -vvv: most verbose
    --dry-run:           if set, don't actually archive anything, but show what
                         would be archived.
    --retry-failed:      if set, attempt to archive documents that failed
                         archive in a previous run of this script.
                         By default, it will only attempt to archive documents
                         that have not yet been attempted.
    --max-attempts:      The maximum number of attempts to download a document
                         before skipping that document.
                         Defaults to 3.
    --max-skips:         The maximum number of times in a row that a document
                         may be skipped before the script aborts.
                         Defaults to 3.

HELP;
    exit;
}

$kernel->run(function (Di\Container $di, LoopInterface $loop, Logger $log, array $options) {

    $dryRun = $options['dryRun'];
    if ($dryRun) {
        $log->notice('Performing dry run - no documents will be archived');
    }

    $retryFailed = $options['retryFailed'];
    if ($retryFailed) {
        $log->notice('Attempting to retry documents that have failed processing previously');
    }

    $maxAttempts = isset($options['maxAttempts']) ? (int)$options['maxAttempts'] : 3;
    $maxSkips = isset($options['maxSkips']) ? (int)$options['maxSkips'] : 3;

    /**
     * @var MapperFactory $mapperFactory
     * @var StorageMapper $storageMapper
     * @var UserMapper    $userMapper
     * @var IConnector    $db
     */
    $httpClient = new HttpClient();
    $mapperFactory = $di->get('mapperFactory');
    $userMapper = $mapperFactory->get(UserMapper::class);
    $storageMapper = $mapperFactory->get(StorageMapper::class);
    $jitter = new Jitter(new FloatGenerator(), $maxAttempts, 500);
    $db = $di->get('databaseInternal');

    $archiver = new CrocodocArchiver($db, $httpClient, $jitter, $storageMapper, $log);

    // Check timeout before anything else
    $lock = new Alaress_Lock(__DIR__ . '/.archive.lock');
    if (!$lock->acquire()) {
        $log->critical("An archiver is already running, checking timeout");

        // For now, hardcode timeout to 300s
        $timeout = 300;
        if ($archiver->isTimedOut($timeout)) {
            $log->warning("Previous archiver has timed out - marking these as failed, and continuing");
            $lock->killProcess();
            $lock->acquire();
            $archiver->timeout();
        } else {
            $log->info("No archiver process has yet timed out - exiting");
            exit;
        }
    }

    // Figure out a system user
    try {
        $systemUser = $userMapper->findOneByColumn('username', 'alaress');
    } catch (MapperException $e) {
        // Deleted user _might_ be fine, but let's not take the risk
        $log->error('User "alaress" not found! New due work feedback and files will be created for this user, so they must exist.');
        exit(1);
    }

    // Run the archiver
    $result = $archiver->archive($systemUser, $dryRun, $retryFailed, $maxSkips);
    if ($result['failed'] > 0) {
        exit(1);
    }
    exit(0);
});
