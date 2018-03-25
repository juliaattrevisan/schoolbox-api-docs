#!/usr/bin/php
<?php
/**
 * Checks whether documents that are still 'processing' in Kami have finished processing
 * If so, updates the document's status in Schoolbox
 *
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

use Alaress\Database\Connector\IConnector;
use Alaress\Jitter\Jitter;
use Aura\Di;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use Schoolbox\IConfigLoader;
use Schoolbox\Learning\InlineGrader\Kami;
use Schoolbox\MapperFactory;
use Alaress\Random;

$fields = [
    'help',
    'dry-run',
    'max-attempts:',
    'max-skips:',
    'base-back-off:',
    'timeout:',
];
$opts = require __DIR__ . '/../../conf/core/require-cli.php';

spl_autoload_call('\\Schoolbox\\Application\\Daemon');
$kernel = new Schoolbox\Application\Daemon\Kernel(
    'Kami Status Checker',
    [
        'verbose' => $opts['v'] ?? [1,2,3],
        'errors'  => E_ALL,
        'opts'    => $opts,
    ]
);

/** @noinspection PhpUnhandledExceptionInspection */
$kernel->run(function (
    Di\Container $di,
    /** @noinspection PhpUnusedParameterInspection */
    LoopInterface $loop,
    LoggerInterface $log,
    array $options
) {
    $opts        = $options['opts'];
    $displayHelp = isset($opts['help']);
    $dryRun      = isset($opts['dry-run']);
    $maxAttempts = array_key_exists('max-attempts', $opts) ? (int)$opts['max-attempts'] : Kami\StatusChecker::DEFAULT_MAX_ATTEMPTS;
    $maxSkips    = array_key_exists('max-skips', $opts) ? (int)$opts['max-skips'] : Kami\StatusChecker::DEFAULT_MAX_SKIPS;
    $baseBackOff = array_key_exists('base-back-off', $opts) ? (int)$opts['base-back-off'] : Kami\StatusChecker::DEFAULT_BASE_JITTER_BACK_OFF;
    $timeout     = array_key_exists('timeout', $opts) ? (int)$opts['timeout'] : Kami\StatusChecker::DEFAULT_TIMEOUT;

    if ($displayHelp) {
        $maxAttempts       = Kami\StatusChecker::DEFAULT_MAX_ATTEMPTS;
        $maxSkips          = Kami\StatusChecker::DEFAULT_MAX_SKIPS;
        $baseJitterBackOff = Kami\StatusChecker::DEFAULT_BASE_JITTER_BACK_OFF;
        $timeout           = Kami\StatusChecker::DEFAULT_TIMEOUT;
        echo <<<HELP
Checks whether documents 'processing' in Kami have finished processing.
 
Usage: check-status.php [-v[v[v]]] [--dry-run] [--max-attempts={$maxAttempts}] [--max-skips={$maxSkips}] [--base-back-off={$baseJitterBackOff}] [--timeout={$timeout}]
    -v:                  Optional verbosity level, -vvv: most verbose. Defaults to most verbose.
    --dry-run:           If set, do not update the status in Schoolbox
    --max-attempts:      The maximum number of attempts to check a document before skipping that document.
                         Defaults to {$maxAttempts}.
    --max-skips:         The maximum number of times in a row that a document may be skipped before the script aborts.
                         Defaults to {$maxSkips}.
    --base-back-off:     The average number of milliseconds to wait between retries if a check attempt fails.
                         Subsequent timeouts will increase exponentially.
                         Defaults to {$baseJitterBackOff}.
    --timeout:           The number of seconds after which we will assume a still-running instance has hung, and therefore needs to be killed.
                         Defaults to {$timeout}.


HELP;
        exit(0);
    }

    $runType = $dryRun ? 'dry' : 'live';
    $negate  = $dryRun ? 'not ' : '';
    $log->info("Performing {$runType} run - document status will {$negate}be updated in Schoolbox");
    $log->debug(sprintf('Max attempts per document: %d', $maxAttempts));
    $log->debug(sprintf('Max successive failures: %d', $maxSkips));
    $log->debug(sprintf('Base jitter back off: %d ms', $baseBackOff));
    $log->debug(sprintf('Timeout: %d ms', $timeout));


    /**
     * @var IConnector        $db
     * @var IConfigLoader     $config
     * @var MapperFactory     $mapperFactory
     * @var Kami\Mapper       $kamiMapper
     */
    $config        = $di->get('config');
    $mapperFactory = $di->get('mapperFactory');
    $kamiMapper    = $mapperFactory->get(Kami\Mapper::class);
    $connector     = new Kami\Connector(new GuzzleHttp\Client(), $config->get('kami_api_key'), $config->get('site_school_code'));

    if (!$connector->isEnabled()) {
        $log->warning('Kami has not been enabled by puppet for this instance. Aborting');
        exit(0);
    }

    $jitter = new Jitter(new Random\FloatGenerator(), $maxAttempts, $baseBackOff);
    $StatusChecker = new Kami\StatusChecker($connector, $kamiMapper, $jitter, $log);

    // Ensure only one instance of checker is running at a time
    $lockFile = "{$config->get('stream_dir')}/.kami_status_check.lock";
    $lock     = new Alaress_Lock($lockFile);
    if (!$lock->acquire()) {
        $log->warning('A status-checker is already running. Checking last mod time');
        $lastModTime  = filemtime($lockFile);
        $lastModDelta = time() - $lastModTime;

        $kill = false;
        if (!$lastModTime) {
            $log->error('Unable to determine when lock file was last written');
            $kill = true;
        } elseif ($lastModDelta > $timeout) {
            $log->error("Lock file last modified {$lastModDelta} seconds ago");
            $kill = true;
        }

        if ($kill) {
            $log->error('Assuming process has timed out and killing it');
            $lock->killProcess();
            $lock->acquire();
        } else {
            $log->info('No instance has yet timed out - exiting');
            exit(1);
        }
    }

    // Run the status checker
    $exitStatus = 0;
    try {
        $exitStatus = $StatusChecker->checkProcessingDocumentsStatus($dryRun, $maxSkips);
    } catch (Throwable $e) {
        $log->error($e->getMessage(), $e->getTrace());
        $log->error($e->getTraceAsString());
    } finally {
        $lock->release();
    }
    exit($exitStatus);
});
