<?php

/**
 * @copyright   Copyright 2026, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Edlink\Base\Endpoint;

use Psr\Log\LoggerInterface;
use Schoolbox\External\Importer\Endpoint\IStrategy;

/**
 * Class ApiStatistics
 *
 * Report a summary of the endpoints accessed by our import
 *
 * @package Schoolbox\External\Connector\Edlink\Base\Endpoint
 */
class ApiStatistics implements IStrategy
{
    /**
     * @var LoggerInterface
     */
    private LoggerInterface $log;

    /**
     * @var IApi
     */
    private IApi $api;

    /**
     * @param LoggerInterface $log
     * @param IApi            $api
     */
    public function __construct(LoggerInterface $log, IApi $api)
    {
        $this->log = $log;
        $this->api = $api;
    }

    /**
     * @inheritDoc
     */
    public function import(): void
    {
        $this->log->info('');
        $this->log->info('API Endpoint Usage');

        $apiUsage = $this->api->getUsage()->retrieve();

        foreach ($apiUsage as $endpoint => $num) {
            $this->log->info(sprintf(
                '    %s %s',
                $endpoint,
                $num
            ));
        }

        $this->log->info('');
    }
}
