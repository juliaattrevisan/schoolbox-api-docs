<?php
/**
 * @copyright Copyright 2019, Alaress Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Application\CliTool;

use Aura\Di;
use Psr\Log\LoggerInterface;
use Throwable;

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


    /**
     * Constructor
     *
     * @param App ...$subApps
     */
    public function __construct(App... $subApps)
    {
        $this->subApps = $subApps;
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
