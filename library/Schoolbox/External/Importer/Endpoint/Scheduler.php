<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\External\Importer\Endpoint;

use Psr\Log\LoggerInterface;

class Scheduler
{

    /**
     * The options you can choose
     */
    private const HOURLY = 'hourly';
    private const TWICE_DAILY = 'twiceDaily';
    private const DAILY = 'daily';


    /**
     * One of [hourly, twiceDaily, daily]
     *
     * @var string
     */
    private $schedule;


    /**
     * API Endpoint url
     *
     * @var string
     */
    private $host;


    /**
     * API Endpoint access token
     *
     * @var string
     */
    private $key;


    /**
     * @var bool
     */
    private $perform;


    /**
     * @var string
     */
    private $type;


    /**
     * @var LoggerInterface
     */
    private $logger;


    /**
     * Scheduler constructor.
     * @param string $schedule
     */
    public function __construct(string $schedule, string $host, string $key, string $type, LoggerInterface $logger)
    {
        $this->schedule = $schedule;

        /* Factory requirements
         * Always perform in crontab
         */
        $this->host = $host;
        $this->key = $key;
        $this->perform = true;
        $this->logger = $logger;
    }


    /**
     * Decide whether to execute the importer
     */
    public function run()
    {
        /* If the frequency is hourly, just do it
         * We'll make the assumption that Support won't configure it
         * to run more frequently than hourly anyway
         */

        /* If the frequency is twiceDaily,
         * Check that the current hour is 4am or 4pm
         * If it is, just do it
         * Otherwise, noop
         */

        /* If the frequency is daily,
         * Checkt hat the current hour is 1am
         * If it is, just do it
         * Otherwise, noop
         */
    }
}
