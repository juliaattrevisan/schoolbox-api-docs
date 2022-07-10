<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\InlineGrader\Kami\CommandLineTool;

use Alaress\Jitter\Jitter;
use Alaress\Random;
use GuzzleHttp;
use Proxy;
use Schoolbox\Application\CliTool;
use Schoolbox\Learning\InlineGrader\Kami;
use Schoolbox\MapperFactory;
use Symfony\Component\Lock\LockInterface;
use Throwable;

/**
 * Checks whether documents that are still 'processing' in Kami have finished
 * processing: if so, updates the document's status in Schoolbox.
 */
class StatusChecker extends CliTool\App
{
    public const LOCK_NAME = 'kami_status_check';


    /**
     * Default timeout for symfony lock: falsey values trigger exception
     */
    private const DEFAULT_TIMEOUT = 10 * 60;


    /** @inheritDoc */
    public function getLongOpts(): array
    {
        return [
            'help',
            'max-attempts:',
            'max-skips:',
            'base-back-off:',
            'timeout:',
        ];
    }


    /** @inheritDoc */
    public function run(): void
    {
        if ($this->getOpt('help')) {
            $this->displayHelp();
            exit(0);
        }

        $maxAttempts = $this->getOptAsInt('max-attempts:', Kami\StatusUpdater::DEFAULT_MAX_ATTEMPTS);
        $maxSkips    = $this->getOptAsInt('max-skips:', Kami\StatusUpdater::DEFAULT_MAX_SKIPS);
        $baseBackOff = $this->getOptAsInt('base-back-off:', Kami\StatusUpdater::DEFAULT_BASE_JITTER_BACK_OFF);
        $timeout     = $this->getOptAsInt('timeout:', Kami\StatusUpdater::DEFAULT_TIMEOUT);

        /* Force default timeout since falsey timeout means something in symfony lock
         */
        if (!$timeout) {
            $timeout = self::DEFAULT_TIMEOUT;
        }

        $this->logConfigurationState($maxAttempts, $maxSkips, $baseBackOff, $timeout);

        try {
            $lock = $this->acquireLock(self::LOCK_NAME, $timeout);
        } catch (\RuntimeException $e) {
            exit(1);
        }

        $this->setMaxExecutionTime($timeout);

        $statusUpdater = $this->buildStatusUpdater($maxAttempts, $baseBackOff, $lock);

        // Run the status checker
        try {
            $exitStatus = $statusUpdater->checkProcessingDocumentsStatus(!$this->perform(), $maxSkips);
        } catch (Throwable $e) {
            $this->getLogger()->error($e->getMessage(), $e->getTrace());
            $this->getLogger()->error($e->getTraceAsString());
            $exitStatus = 1;
        } finally {
            $lock->release();
        }
        exit($exitStatus);
    }


    /**
     * Displays a help message
     */
    private function displayHelp(): void
    {
        $maxAttempts       = Kami\StatusUpdater::DEFAULT_MAX_ATTEMPTS;
        $maxSkips          = Kami\StatusUpdater::DEFAULT_MAX_SKIPS;
        $baseJitterBackOff = Kami\StatusUpdater::DEFAULT_BASE_JITTER_BACK_OFF;
        $timeout           = Kami\StatusUpdater::DEFAULT_TIMEOUT;
        echo <<<HELP
Checks whether documents 'processing' in Kami have finished processing.

Usage: check-status.php --dry-run|--perform [-v[v[v]]] [--max-attempts={$maxAttempts}] [--max-skips={$maxSkips}] [--base-back-off={$baseJitterBackOff}] [--timeout={$timeout}]
    --dry-run:           If set, do not update the status in Schoolbox
    --perform:           If set, do update the status in Schoolbox
    -v:                  Optional verbosity level, -vvv: most verbose. Defaults to most verbose.
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
    }


    /**
     * Logs some information regarding the app's configuration state.
     *
     * @param int $maxAttempts
     * @param int $maxSkips
     * @param int $baseBackOff
     * @param int $timeout
     */
    private function logConfigurationState(int $maxAttempts, int $maxSkips, int $baseBackOff, int $timeout): void
    {
        $runType = $this->perform() ? 'live' : 'dry';
        $negate  = $this->perform() ? '' : 'not ';
        $this->getLogger()->info("Performing {$runType} run - document status will {$negate}be updated in Schoolbox");
        $this->getLogger()->debug(sprintf('Max attempts per document: %d', $maxAttempts));
        $this->getLogger()->debug(sprintf('Max successive failures: %d', $maxSkips));
        $this->getLogger()->debug(sprintf('Base jitter back off: %d ms', $baseBackOff));
        $this->getLogger()->debug(sprintf('Timeout: %d ms', $timeout));
    }


    /**
     * Builds and returns a StatusUpdater
     *
     * @param int           $maxAttempts
     * @param int           $baseBackOff
     * @param LockInterface $lock
     * @return Kami\StatusUpdater
     * @throws GuzzleHttp\Exception\InvalidArgumentException
     * @throws \Psr\Container\NotFoundExceptionInterface|\Psr\Container\ContainerExceptionInterface
     * @throws \InvalidArgumentException
     */
    private function buildStatusUpdater(int $maxAttempts, int $baseBackOff, LockInterface $lock): Kami\StatusUpdater
    {
        $config = $this->getConfig();
        /** @var MapperFactory $mapperFactory */
        $mapperFactory = $this->getDi()->get('mapperFactory');
        /** @var Kami\Mapper $kamiMapper */
        $kamiMapper = $mapperFactory->get(Kami\Mapper::class);
        $connector  = new Kami\Connector(
            new GuzzleHttp\Client(['curl' => Proxy\Server::getInstance()->buildCurlOptions()]),
            $this->getConfig()->get('kami_enabled'),
            $config->get('kami_api_key'),
            $config->get('site_school_code')
        );

        if (!$connector->isEnabled()) {
            $this->getLogger()->warning('Kami has not been enabled by puppet for this instance. Aborting');
            exit(0);
        }

        $jitter = new Jitter(new Random\FloatGenerator(), $maxAttempts, $baseBackOff);
        return new Kami\StatusUpdater($connector, $kamiMapper, $jitter, $this->getLogger(), $lock);
    }
}
