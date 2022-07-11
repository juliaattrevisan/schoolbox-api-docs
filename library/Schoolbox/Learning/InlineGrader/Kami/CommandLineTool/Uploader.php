<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\InlineGrader\Kami\CommandLineTool;

use Alaress\Http\UnreliableMiddleware;
use Alaress\Jitter\Jitter;
use Alaress\Random;
use GuzzleHttp;
use GuzzleHttp\HandlerStack;
use Proxy;
use Schoolbox\Application\CliTool;
use Schoolbox\Learning\InlineGrader\Kami;
use Schoolbox\MapperFactory;
use Sentry\State\HubInterface;
use Throwable;

/**
 * Uploads eligible documents from Schoolbox into Kami.
 */
class Uploader extends CliTool\App
{
    public const LOCK_NAME = 'kami_upload';


    /**
     * Default timeout for symfony lock: falsey values trigger exception
     */
    private const DEFAULT_TIMEOUT = 10 * 60;


    private const DEFAULT_BATCH_SIZE           = 100;
    private const DEFAULT_MAX_ATTEMPTS         = 3;
    private const DEFAULT_MAX_SKIPS            = 3;
    private const DEFAULT_BASE_JITTER_BACK_OFF = 500;

    /** @inheritDoc */
    public function getLongOpts(): array
    {
        return [
            'help',
            'retry-failed',
            'batch-size:',
            'max-attempts:',
            'max-skips:',
            'base-back-off:',
            'timeout:',
            'failure-rate:',
        ];
    }

    /** @inheritDoc */
    public function run(): void
    {
        if ($this->getOpt('help')) {
            $this->displayHelp();
            exit;
        }

        $dryRun      = !$this->perform();
        $retryFailed = $this->getOpt('retry-failed');
        $batchSize   = $this->getOptAsInt('batch-size:', self::DEFAULT_BATCH_SIZE);
        $maxAttempts = $this->getOptAsInt('max-attempts:', self::DEFAULT_MAX_ATTEMPTS);
        $maxSkips    = $this->getOptAsInt('max-skips:', self::DEFAULT_MAX_SKIPS);
        $baseBackOff = $this->getOptAsInt('base-back-off:', self::DEFAULT_BASE_JITTER_BACK_OFF);
        $timeout     = $this->getOptAsInt('timeout:', self::DEFAULT_TIMEOUT);
        $failureRate = $this->getOptAsFloat('failure-rate:', 0.0);

        /* Force default timeout since falsey timeout means something in symfony lock
         */
        if (!$timeout) {
            $timeout = self::DEFAULT_TIMEOUT;
        }

        $dryRun
            ? $this->getLogger()->notice('Performing dry run - no documents will be uploaded to Kami or saved to Database')
            : $this->getLogger()->notice('Performing live run - documents will be uploaded to Kami and saved to Database');

        $retryFailed
            ? $this->getLogger()->notice('Attempting to retry documents that have failed uploading previously')
            : $this->getLogger()->notice('Attempting documents that have yet to be attempted');

        $this->getLogger()->info(sprintf('Batch size: %d', $batchSize));
        $this->getLogger()->info(sprintf('Max attempts per document: %d', $maxAttempts));
        $this->getLogger()->info(sprintf('Max successive failures: %d', $maxSkips));
        $this->getLogger()->info(sprintf('Base jitter back off: %d ms', $baseBackOff));
        $this->getLogger()->info(sprintf('Upload timeout: %d s', $timeout));
        $this->getLogger()->info(sprintf('Simulated network failure rate: %f', $failureRate));

        /** @var MapperFactory $mapperFactory */
        $mapperFactory = $this->getDi()->get('mapperFactory');
        /** @var Kami\Mapper $kamiMapper */
        $kamiMapper = $mapperFactory->get(Kami\Mapper::class);

        $stack = HandlerStack::create();
        if ($failureRate > 0.0) {
            $stack->before(
                'http_errors',
                new UnreliableMiddleware($failureRate, new Random\FloatGenerator()),
            );
        }
        $client = new GuzzleHttp\Client([
            'curl'    => Proxy\Server::getInstance()->buildCurlOptions(),
            'handler' => $stack,
        ]);

        $connector = $dryRun
            ? new Kami\MockConnector($client)
            : new Kami\Connector(
                $client,
                $this->getConfig()->get('kami_enabled'),
                $this->getConfig()->get('kami_api_key'),
                $this->getConfig()->get('site_school_code')
            );

        if (!$connector->isEnabled()) {
            $this->getLogger()->warning('Kami has not been enabled by puppet for this instance. Aborting');
            exit(0);
        }

        $sentry = null;
        if ($this->getDi()->has('sentry')) {
            $sentry = $this->getDi()->get('sentry');
        }

        try {
            $lock = $this->acquireLock(self::LOCK_NAME, $timeout);
        } catch (\RuntimeException $e) {
            exit(1);
        }

        $this->setMaxExecutionTime($timeout);

        /* Only start up an Uploader if we can actually go ahead and do something
         */
        $uploader = new Kami\Uploader(
            $dryRun,
            $connector,
            new Jitter(new Random\FloatGenerator(), $maxAttempts, $baseBackOff),
            $kamiMapper,
            $this->getLogger(),
            $lock,
            $sentry instanceof HubInterface ? $sentry : null,
            $this->getDi()->get('filesystem.storageProxy'),
        );

        // Run the uploader
        try {
            $results = $uploader->upload($retryFailed, $batchSize, $maxSkips);
        } catch (Throwable $e) {
            $this->getLogger()->error($e->getMessage(), ['exception' => $e]);
            $this->getLogger()->error($e->getTraceAsString());
            $lock->release();
            exit(1);
        }
        $this->getLogger()->notice(sprintf(
            'Uploader complete. %d success, %d failure',
            $results->getCountSuccess(),
            $results->getCountFailure()
        ));
        $lock->release();
        exit($results->getCountFailure() ? 1 : 0);
    }


    /**
     * Displays a help message
     */
    private function displayHelp(): void
    {
        $batchSize         = self::DEFAULT_BATCH_SIZE;
        $maxAttempts       = self::DEFAULT_MAX_ATTEMPTS;
        $maxSkips          = self::DEFAULT_MAX_SKIPS;
        $baseJitterBackOff = self::DEFAULT_BASE_JITTER_BACK_OFF;
        $timeOut           = self::DEFAULT_TIMEOUT;
        echo <<<HELP
Upload eligible documents into Kami.

Usage: upload.php -v[vv] [--perform] [--retry-failed] [--max-attempts=3] [--max-skips=3] [--base-back-off=500] [--timeout=300] [--failure-rate=0.0]
    -v:                  Verbosity level, -vvv: most verbose
    --perform:           If set, use the prod Kami API and execute real actions (otherwise run in dry-run mode)
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
    }
}
