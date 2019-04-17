<?php
/**
 * @copyright Copyright 2019, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Application\CliTool;

use Aura\Di;
use Psr\Log\LoggerInterface;

/**
 * A composite app: contains one or more apps to be run in sequence.
 */
class Composite extends App
{
    /**
     * A set of apps to run.
     *
     * @var array|App[]
     */
    private $apps = [];


    /**
     * Constructor
     *
     * @param App ...$apps
     */
    public function __construct(App... $apps)
    {
        $this->apps = $apps;
    }


    /**
     * Sets up this app, then all of its sub-apps.
     *
     * @inheritDoc
     */
    public function setup(array $appOptions, Di\Container $di, $outStream, LoggerInterface $logger, bool $perform): App
    {
        parent::setup($appOptions, $di, $outStream, $logger, $perform);
        foreach ($this->apps as $app) {
            $app->setup($appOptions, $di, $outStream, $logger, $perform);
        }
        return $this;
    }


    /**
     * Runs all sub-apps.
     */
    public function run(): void
    {
        foreach ($this->apps as $app) {
            $app->run();
        }
    }
}
