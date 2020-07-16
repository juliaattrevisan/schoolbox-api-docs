#!/usr/bin/php
<?php
/**
 * Finds all docs with the a status of 'error' and optional 'error_message' and puts them back into the queue for re-uploading.
 *
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

use Alaress\Jitter\Jitter;
use Schoolbox\Learning\InlineGrader\Kami;
use Schoolbox\MapperFactory;
use Alaress\Random;
use Schoolbox\Application\CliTool;

require __DIR__ . '/../../conf/core/require.php';

$app = new class extends CliTool\App{

    /** @inheritDoc */
    public function getLongOpts(): array
    {
        return [
            'help',
            'error-message:',
            'max-attempts:',
            'max-skips:',
            'base-back-off:',
            'timeout:',
        ];
    }


    /** @inheritDoc */
    public function run(): void
    {
        $displayHelp  = $this->getOpt('help');
        $errorMessage = $this->getOpt('error-message:');
        $maxAttempts  = $this->getOptAsInt('max-attempts:', Kami\ReQueueErrors::DEFAULT_MAX_ATTEMPTS);
        $maxSkips     = $this->getOptAsInt('max-skips:', Kami\ReQueueErrors::DEFAULT_MAX_SKIPS);
        $baseBackOff  = $this->getOptAsInt('base-back-off:', Kami\ReQueueErrors::DEFAULT_BASE_JITTER_BACK_OFF);
        $timeout      = $this->getOptAsInt('timeout:', Kami\ReQueueErrors::DEFAULT_TIMEOUT);

        if ($displayHelp) {
            $maxAttempts       = Kami\ReQueueErrors::DEFAULT_MAX_ATTEMPTS;
            $maxSkips          = Kami\ReQueueErrors::DEFAULT_MAX_SKIPS;
            $baseJitterBackOff = Kami\ReQueueErrors::DEFAULT_BASE_JITTER_BACK_OFF;
            $timeout           = Kami\ReQueueErrors::DEFAULT_TIMEOUT;
            echo <<<HELP
Deletes any 'error' status documents from Kami and re-queues for uploading.

Usage: requeue-errors.php [--perform] [--error-message=ERROR_MESSAGE][-v[v[v]]] [--max-attempts={$maxAttempts}] [--max-skips={$maxSkips}] [--base-back-off={$baseJitterBackOff}] [--timeout={$timeout}]
    --perform:           If set, do delete in Kami and do update the status in Schoolbox (if absent, runs in dry-run mode)
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

        $runType = $this->perform() ? 'live' : 'dry';
        $negate  = $this->perform() ? ' ' : 'NOT ';
        $this->getLogger()->info("Performing {$runType} run - documents will {$negate}be deleted from Kami and will {$negate}be updated in Schoolbox");
        $this->getLogger()->debug(null === $errorMessage ? "Not filtering by 'error_message'" : sprintf("Filtering to error_message: '%s'", $errorMessage));
        $this->getLogger()->debug(sprintf('Max attempts per document: %d', $maxAttempts));
        $this->getLogger()->debug(sprintf('Max successive failures: %d', $maxSkips));
        $this->getLogger()->debug(sprintf('Base jitter back off: %d ms', $baseBackOff));
        $this->getLogger()->debug(sprintf('Timeout: %d ms', $timeout));

        /**
         * @var MapperFactory     $mapperFactory
         * @var Kami\Mapper       $kamiMapper
         */
        $mapperFactory = $this->getDi()->get('mapperFactory');
        $kamiMapper    = $mapperFactory->get(Kami\Mapper::class);
        $connector     = new Kami\Connector(
            new GuzzleHttp\Client(['curl' => Proxy\Server::getInstance()->buildCurlOptions()]),
            $this->getConfig()->get('kami_api_key'),
            $this->getConfig()->get('site_school_code')
        );

        if (!$connector->isEnabled()) {
            $this->getLogger()->warning('Kami has not been enabled by puppet for this instance. Aborting');
            exit(0);
        }

        $jitter        = new Jitter(new Random\FloatGenerator(), $maxAttempts, $baseBackOff);
        $requeueErrors = new Kami\ReQueueErrors($connector, $kamiMapper, $jitter, $this->getLogger());

        // Ensure only one instance of tool is running at a time
        $lockFile = "{$this->getConfig()->get('stream_dir')}/.kami_requeue_errors_".INSTANCE_ID.".lock";
        $lock     = new Alaress_Lock($lockFile);
        if (!$lock->acquire()) {
            $this->getLogger()->warning('A errors-requeue instance is already running. Checking last mod time');
            $lastModTime  = filemtime($lockFile);
            $lastModDelta = time() - $lastModTime;

            $kill = false;
            if (!$lastModTime) {
                $this->getLogger()->error('Unable to determine when lock file was last written');
                $kill = true;
            } elseif ($lastModDelta > $timeout) {
                $this->getLogger()->error("Lock file last modified {$lastModDelta} seconds ago");
                $kill = true;
            }

            if ($kill) {
                $this->getLogger()->error('Assuming process has timed out and killing it');
                $lock->killProcess();
                $lock->acquire();
            } else {
                $this->getLogger()->info('No instance has yet timed out - exiting');
                exit(1);
            }
        }

        // Run the tool
        $exitStatus = 0;
        try {
            $exitStatus = $requeueErrors->deleteAndReQueue(!$this->perform(), $maxSkips, $errorMessage);
        } catch (Throwable $e) {
            $this->getLogger()->error($e->getMessage(), $e->getTrace());
            $this->getLogger()->error($e->getTraceAsString());
        } finally {
            $lock->release();
        }
        exit($exitStatus);
    }
};


$kernel = new CliTool\Kernel($app, [CliTool\Kernel::OPTION_ERRORS => E_ALL]);
$kernel->runApp();
