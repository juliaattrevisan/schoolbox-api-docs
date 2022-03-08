<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\TassApi\Base\Endpoint\Calendar;

use Psr\Log\LoggerInterface;
use Schoolbox\External\Importer\Endpoint\IStrategy;

/**
 * Show a list of calendar endpoints with the quantity of events
 * that came out of tass, alongside the quantity of events created
 * in schoolbox itself
 */
class Summary implements IStrategy
{

    /**
     * @var LoggerInterface
     */
    protected LoggerInterface $log;


    /**
     * @var Registry
     */
    private Registry $registry;


    /**
     * @param LoggerInterface $log
     * @param Registry        $registry
     */
    public function __construct(LoggerInterface $log, Registry $registry)
    {
        $this->log = $log;
        $this->registry = $registry;
    }


    /**
     * @return void
     */
    public function import(): void
    {
        $this->log->info('');
        $this->log->info('');
        $this->log->info('Calendar Creation Summary');
        $this->log->info('');

        foreach ($this->registry->recallAll() as $endpoint => $result) {
            $this->log->info(sprintf(
                "    %s",
                $endpoint
            ));

            $this->log->info(sprintf(
                "        %s.",
                $result[0],
            ));

            $this->log->info(sprintf(
                "        %s.",
                $result[1]
            ));

            /* If any event types are missing, tell someone
             */
            if (\count($result[2])) {
                $this->log->info("        There are missing event types in schoolbox which you should create before next calendar import.");

                foreach ($result[2] as $brokenEventType) {
                    $this->log->info(sprintf(
                        "            %s",
                        $brokenEventType
                    ));
                }
            }

            $this->log->info('');
        }
    }
}