#!/usr/bin/php
<?php
/**
 * Finds all docs with the a status of 'error' and optional 'error_message' and puts them back into the queue for re-uploading.
 *
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2018, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
declare(strict_types=1);

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
    'perform',
    'dry-run',
    'error-message:',
    'max-attempts:',
    'max-skips:',
    'base-back-off:',
    'timeout:',
];
$opts = require __DIR__ . '/../../conf/core/require-cli.php';
$kernel = new Schoolbox\Application\Daemon\Kernel(
    'Kami Error Re-Queue',
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
    $opts         = $options['opts'];
    $displayHelp  = isset($opts['help']);
    $dryRun       = isset($opts['dry-run']);
    $perform      = isset($opts['perform']);
    $errorMessage = $opts['error-message'] ?? null;
    $maxAttempts  = array_key_exists('max-attempts', $opts) ? (int)$opts['max-attempts'] : Kami\ReQueueErrors::DEFAULT_MAX_ATTEMPTS;
    $maxSkips     = array_key_exists('max-skips', $opts) ? (int)$opts['max-skips'] : Kami\ReQueueErrors::DEFAULT_MAX_SKIPS;
    $baseBackOff  = array_key_exists('base-back-off', $opts) ? (int)$opts['base-back-off'] : Kami\ReQueueErrors::DEFAULT_BASE_JITTER_BACK_OFF;
    $timeout      = array_key_exists('timeout', $opts) ? (int)$opts['timeout'] : Kami\ReQueueErrors::DEFAULT_TIMEOUT;

    $colourRed   = "\033[0;31m";
    $colourWhite = "\033[0m";
    if (!$dryRun && !$perform) {
        echo "{$colourRed}Invalid syntax. You must specify the mode using '--dry-run' or '--perform'{$colourWhite}\n\n";
        $displayHelp = true;
    }
    if ($dryRun && $perform) {
        echo "{$colourRed}Invalid syntax. You must specify only one mode using '--dry-run' or '--perform'{$colourWhite}\n\n";
        $displayHelp = true;
    }

    if ($displayHelp) {
        $maxAttempts       = Kami\ReQueueErrors::DEFAULT_MAX_ATTEMPTS;
        $maxSkips          = Kami\ReQueueErrors::DEFAULT_MAX_SKIPS;
        $baseJitterBackOff = Kami\ReQueueErrors::DEFAULT_BASE_JITTER_BACK_OFF;
        $timeout           = Kami\ReQueueErrors::DEFAULT_TIMEOUT;
        echo <<<HELP
Deletes any 'error' status documents from Kami and re-queues for uploading.
 
Usage: requeue-errors.php --dry-run|--perform [--error-message=ERROR_MESSAGE][-v[v[v]]] [--max-attempts={$maxAttempts}] [--max-skips={$maxSkips}] [--base-back-off={$baseJitterBackOff}] [--timeout={$timeout}]
    --dry-run:           If set, do not delete from Kami or update the status in Schoolbox
    --perform:           If set, do delete in Kami and do update the status in Schoolbox
    --error-message:     Optional. If set, only delete and re-queue documents that match ERROR_MESSAGE
    -v:                  Optional verbosity level, -vvv: most verbose.
                         Defaults to most verbose.
    --max-attempts:      The maximum number of attempts to delete a document before skipping that document.
                         Defaults to {$maxAttempts}.
    --max-skips:         The maximum number of times in a row that a document may be skipped before the script aborts.
                         Defaults to {$maxSkips}.
    --base-back-off:     The average number of milliseconds to wait between retries if a deletion attempt fails.
                         Subsequent timeouts will increase exponentially.
                         Defaults to {$baseJitterBackOff}.
    --timeout:           The number of seconds after which we will assume a still-running instance has hung, and therefore needs to be killed.
                         Defaults to {$timeout}.


HELP;
        exit(0);
    }

    $runType = $dryRun ? 'dry' : 'live';
    $negate  = $dryRun ? 'not ' : '';
    $log->info("Performing {$runType} run - documents will {$negate}be deleted from Kami will {$negate}be updated in Schoolbox");
    $log->debug(null === $errorMessage ? "Not filtering by 'error_message'" : sprintf("Filtering to error_message: '%s'", $errorMessage));
    $log->debug(sprintf('Max attempts per document: %d', $maxAttempts));
    $log->debug(sprintf('Max successive failures: %d', $maxSkips));
    $log->debug(sprintf('Base jitter back off: %d ms', $baseBackOff));
    $log->debug(sprintf('Timeout: %d ms', $timeout));


    /**
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

    $jitter        = new Jitter(new Random\FloatGenerator(), $maxAttempts, $baseBackOff);
    $requeueErrors = new Kami\ReQueueErrors($connector, $kamiMapper, $jitter, $log);

    // Ensure only one instance of tool is running at a time
    $lockFile = "{$config->get('stream_dir')}/.kami_requeue_errors_".INSTANCE_ID.".lock";
    $lock     = new Alaress_Lock($lockFile);
    if (!$lock->acquire()) {
        $log->warning('A errors-requeue instance is already running. Checking last mod time');
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

    // Run the tool
    $exitStatus = 0;
    try {
        $exitStatus = $requeueErrors->deleteAndReQueue($dryRun, $maxSkips, $errorMessage);
    } catch (Throwable $e) {
        $log->error($e->getMessage(), $e->getTrace());
        $log->error($e->getTraceAsString());
    } finally {
        $lock->release();
    }
    exit($exitStatus);
});
