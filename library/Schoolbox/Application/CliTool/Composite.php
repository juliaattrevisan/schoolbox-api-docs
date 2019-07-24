<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Application\CliTool;

use Aura\Di;
use Psr\Log\LoggerInterface;
use Throwable;
use UnexpectedValueException;

/**
 * A composite app: contains one or more sub-apps to be run in sequence.
 */
class Composite extends App
{
    /**
     * A set of sub-apps to run.
     *
     * @var array|App[]
     */
    private $subApps = [];

    /** @var null|bool */
    private $runAsRoot;


    /**
     * Constructor
     *
     * Throws an exception if we attempt to add an invalid combination of sub-apps
     *
     * @param App ...$subApps
     * @throws UnexpectedValueException
     */
    public function __construct(App... $subApps)
    {
        $this->subApps = $subApps;

        // Ensure all sub-apps are consistent regarding their permitted execution
        $mustBeRoot = $mustBeNonRoot = false;
        foreach ($this->subApps as $subApp) {
            if ($subApp->runAsRoot()) {
                $mustBeRoot = true;
            } elseif ($subApp->runAsRoot() === false) {
                $mustBeNonRoot = true;
            }
        }
        if ($mustBeRoot && $mustBeNonRoot) {
            throw new UnexpectedValueException('Composite sub-apps require being run as both root and non-root, this is impossible');
        }
        if ($mustBeRoot) {
            $this->runAsRoot = true;
        } elseif ($mustBeNonRoot) {
            $this->runAsRoot = false;
        }
    }


    /**
     * @inheritDoc
     */
    public function runAsRoot(): ?bool
    {
        return $this->runAsRoot;
    }


    /**
     * Sets up this app, then all of its sub-apps.
     *
     * @inheritDoc
     */
    public function setup(array $appOptions, Di\Container $di, $outStream, LoggerInterface $logger, bool $perform): App
    {
        parent::setup($appOptions, $di, $outStream, $logger, $perform);
        foreach ($this->subApps as $subApp) {
            $this->setupSubApp($subApp, $appOptions, $di, $outStream, $logger, $perform);
        }
        return $this;
    }


    /**
     * Overridable setup for sub-apps.
     *
     * @param App             $subApp
     * @param array           $appOptions
     * @param Di\Container    $di
     * @param                 $outStream
     * @param LoggerInterface $logger
     * @param bool            $perform
     * @throws Di\Exception\ServiceNotFound
     */
    protected function setupSubApp(
        App $subApp,
        array $appOptions,
        Di\Container $di,
        $outStream,
        LoggerInterface $logger,
        bool $perform
    ): void {
        $subApp->setup($appOptions, $di, $outStream, $logger, $perform);
    }


    /**
     * Runs all sub-apps.
     *
     * @throws Throwable The last exception from a sub-app
     */
    public function run(): void
    {
        $lastException = null;
        foreach ($this->subApps as $subApp) {
            try {
                $subApp->run();
            } catch (Throwable $e) {
                $lastException = $e;
            } finally {
                $this->getLogger()->notice('==========');
            }
        }
        if ($lastException) {
            throw $e;
        }
    }


    /**
     * Options are the union of all sub-app options.
     *
     * @return array
     */
    public function getShortOpts(): array
    {
        $opts = parent::getShortOpts();
        foreach ($this->subApps as $subApp) {
            $opts = array_merge($opts, $subApp->getShortOpts());
        }
        return array_unique($opts);
    }

    /**
     * Options are the union of all sub-app options.
     *
     * @return array
     */
    public function getLongOpts(): array
    {
        $opts = parent::getLongOpts();
        foreach ($this->subApps as $subApp) {
            $opts = array_merge($opts, $subApp->getLongOpts());
        }
        return array_unique($opts);
    }
}
