#!/usr/bin/php
<?php
/**
 * Uploads eligible documents from Schoolbox into Kami
 *
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2017, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

use Alaress\Database\Connector\IConnector;
use Alaress\Http\UnreliableClient;
use Alaress\Jitter\Jitter;
use Aura\Di;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use Schoolbox\IConfigLoader;
use Schoolbox\Learning\InlineGrader\Kami;
use Schoolbox\MapperFactory;
use Schoolbox\Assessment\Component\SubmissionBox\Submission\SingleFile;
use Schoolbox\Resource\Storage;
use Alaress\Random;

const DEFAULT_BATCH_SIZE           = 100;
const DEFAULT_MAX_ATTEMPTS         = 3;
const DEFAULT_MAX_SKIPS            = 3;
const DEFAULT_BASE_JITTER_BACK_OFF = 500;
const DEFAULT_TIMEOUT              = 300;

$fields  = [
    'help',
    'dry-run',
    'perform',
    'retry-failed',
    'batch-size:',
    'max-attempts:',
    'max-skips:',
    'base-back-off:',
    'timeout:',
    'failure-rate:',
];
$options = require __DIR__ . '/../../conf/core/require-cli.php';


spl_autoload_call('\\Schoolbox\\Application\\Daemon');
$kernel = new Schoolbox\Application\Daemon\Kernel(
    'Kami Uploader',
    [
        'verbose'     => [true,true,true],//$options['v'] ?? null,
        'errors'      => E_ALL,
        'dryRun'      => isset($options['dry-run']),
        'retryFailed' => isset($options['retry-failed']),
        'batchSize'   => isset($options['batch-size']) ? (int)$options['batch-size'] : DEFAULT_BATCH_SIZE,
        'maxAttempts' => isset($options['max-attempts']) ? (int)$options['max-attempts'] : DEFAULT_MAX_ATTEMPTS,
        'maxSkips'    => isset($options['max-skips']) ? (int)$options['max-skips'] : DEFAULT_MAX_SKIPS,
        'baseBackOff' => isset($options['base-back-off']) ? (int)$options['base-back-off'] : DEFAULT_BASE_JITTER_BACK_OFF,
        'timeout'     => isset($options['timeout']) ? (int)$options['timeout'] : DEFAULT_TIMEOUT,
        'failureRate' => isset($options['failure-rate']) ? (float)$options['failure-rate'] : 0.0,
    ]
);

$colourRed   = "\033[0;31m";
$colourWhite = "\033[0m";
$displayHelp = isset($options['help']);
if (!isset($options['dry-run']) && !isset($options['perform'])) {
    echo "{$colourRed}Invalid syntax. You must specify the mode using '--dry-run' or '--perform'{$colourWhite}\n\n";
    $displayHelp = true;
}
if (isset($options['dry-run'], $options['perform'])) {
    echo "{$colourRed}Invalid syntax. You must specify only one mode using '--dry-run' or '--perform'{$colourWhite}\n\n";
    $displayHelp = true;
}
if ($displayHelp) {
    $batchSize         = DEFAULT_BATCH_SIZE;
    $maxAttempts       = DEFAULT_MAX_ATTEMPTS;
    $maxSkips          = DEFAULT_MAX_SKIPS;
    $baseJitterBackOff = DEFAULT_BASE_JITTER_BACK_OFF;
    $timeOut           = DEFAULT_TIMEOUT;
    echo <<<HELP
Upload eligible documents into Kami.

Usage: upload.php -v[vv] --dry-run|--perform [--retry-failed] [--max-attempts=3] [--max-skips=3] [--base-back-off=500] [--timeout=300] [--failure-rate=0.0]
    -v:                  Verbosity level, -vvv: most verbose
    --dry-run:           If set, use the mock Kami API and simulate what would occur.
    --perform:           If set, use the prod Kami API and execute real actions
    --retry-failed:      If set, only attempt to upload documents that have failed to be uploaded
                         in a previous run of this script. Will not attempt to upload any
                         documents that have not yet been attempted.
    --batch-size:        The number of documents to upload per script execution.
                         Defaults to {$batchSize}.
    --max-attempts:      The maximum number of attempts to upload a document
                         before skipping that document.
                         Defaults to {$maxAttempts}.
    --max-skips:         The maximum number of times in a row that a document
                         may be skipped before the script aborts.
                         Defaults to {$maxSkips}.
    --base-back-off:     The average number of milliseconds to wait between retries if an upload fails.
                         Subsequent timeouts will increase exponentially.
                         Defaults to {$baseJitterBackOff}.
    --timeout            Number of seconds after which a pending upload will be assumed failed
                         Defaults to {$timeOut}.
    --failure-rate       For testing, set from 0.0 to 1.0 inclusive to simulate proportion of network calls that fail.
                         Defaults to 0.0

HELP;
    exit;
}

/** @noinspection PhpUnusedParameterInspection */
$kernel->run(function (Di\Container $di, LoopInterface $loop, LoggerInterface $log, array $options) {

    $dryRun      = $options['dryRun'];
    $retryFailed = $options['retryFailed'];
    $batchSize   = $options['batchSize'];
    $maxAttempts = $options['maxAttempts'];
    $maxSkips    = $options['maxSkips'];
    $baseBackOff = $options['baseBackOff'];
    $timeout     = $options['timeout'];
    $failureRate = $options['failureRate'];

    $dryRun
        ? $log->notice('Performing dry run - no documents will be uploaded to Kami or saved to Database')
        : $log->notice('Performing live run - documents will be uploaded to Kami and saved to Database');

    $retryFailed
        ? $log->notice('Attempting to retry documents that have failed uploading previously')
        : $log->notice('Attempting documents that have yet to be attempted');

    $log->info(sprintf('Batch size: %d', $batchSize));
    $log->info(sprintf('Max attempts per document: %d', $maxAttempts));
    $log->info(sprintf('Max successive failures: %d', $maxSkips));
    $log->info(sprintf('Base jitter back off: %d ms', $baseBackOff));
    $log->info(sprintf('Upload timeout: %d s', $timeout));
    $log->info(sprintf('Simulated network failure rate: %f', $failureRate));

    /**
     * @var IConnector        $db
     * @var IConfigLoader     $config
     * @var MapperFactory     $mapperFactory
     * @var SingleFile\Mapper $submissionFileMapper
     * @var Storage\Mapper    $storageMapper
     * @var Kami\Mapper       $kamiMapper
     */
    $config        = $di->get('config');
    $mapperFactory = $di->get('mapperFactory');
    $kamiMapper    = $mapperFactory->get(Kami\Mapper::class);
    $client        = $failureRate === 0.0
        ? new GuzzleHttp\Client()
        : new UnreliableClient($failureRate, new Random\FloatGenerator());

    $connector = $dryRun ? new Kami\MockConnector($client) : new Kami\Connector($config, $client);

    if(!$connector->isEnabled()) {
        $log->warning('Kami has not been enabled by puppet for this instance. Aborting');
        exit(0);
    }

    $uploader = new Kami\Uploader(
        $dryRun,
        $connector,
        new Jitter(new Random\FloatGenerator(), $maxAttempts, $baseBackOff),
        $kamiMapper,
        $log
    );

    // Check timeout before anything else
    $lock = new Alaress_Lock($config->get('stream_dir') . '/.kami_upload.lock');
    if (!$lock->acquire()) {
        $log->critical('An uploader is already running, checking timeout');

        // For now, hardcode timeout to 300s
        if ($uploader->isTimedOut($timeout)) {
            $log->warning('Previous uploader has timed out - continuing');
            $lock->killProcess();
            $lock->acquire();
            $uploader->timeout();
        } else {
            $log->info('No uploader process has yet timed out - exiting');
            exit(1);
        }
    }

    // Run the uploader
    try {
        $results = $uploader->upload($retryFailed, $batchSize, $maxSkips);
    } catch (Throwable $e) {
        $log->error($e->getMessage(), $e->getTrace());
        $log->error($e->getTraceAsString());
        exit(1);
    }
    $log->notice(sprintf(
        'Uploader complete. %d success, %d failure',
        $results->getCountSuccess(),
        $results->getCountFailure()
    ));
    exit($results->getCountFailure() ? 1 : 0);
});
