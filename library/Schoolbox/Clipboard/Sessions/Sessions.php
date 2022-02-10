<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Clipboard\Sessions;

use Psr\Log\LoggerInterface;
use Schoolbox\Clipboard\Hierarchy\Registry;
use Schoolbox\Clipboard\IApi;
use Schoolbox\Clipboard\IStrategy;

/**
 * Generate a list of Clipboard Sessions to load as schoolbox Matchups
 */
class Sessions implements IStrategy
{
    /**
     * @var LoggerInterface
     */
    private LoggerInterface $log;


    /**
     * @var bool
     */
    private bool $perform;


    /**
     * @var IApi
     */
    private IApi $api;


    /**
     * @var Registry
     */
    private Registry $registry;


    /**
     * Constructor.
     *
     * @param LoggerInterface $log
     * @param bool            $perform
     * @param IApi            $api
     * @param Registry        $registry
     */
    public function __construct(
        LoggerInterface $log,
        bool $perform,
        IApi $api,
        Registry $registry
    ) {
        $this->log = $log;
        $this->perform = $perform;
        $this->api = $api;
        $this->registry = $registry;
    }


    /**
     * @return void
     */
    public function import(): void
    {

        /* Consume data from Clipboard endpoint - each Session comes with info about
         * its own hierarchy so map them to schoolbox concepts as best we can
         */
        $endpoint = 'sessions';

        $this->log->info(sprintf(
            'Downloading data from endpoint: %s (this might take a while)',
            $endpoint
        ));

        $sessions = $this->api->retrieve('GET', $endpoint, [
            'includeTeams' => 'true',
            'includeStaff' => 'true',
            'includeRoundName' => 'true',
            'includeStatuses' => ['confirmed'],
            /* Apparently this should give you "only not cancelled" sessions
             */
            'cancelled' => 'false'
        ]);

        print_r($sessions);
    }
}