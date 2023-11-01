<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Civica\Base\Endpoint;

use Psr\Log\LoggerInterface;
use Schoolbox\External\Importer\Endpoint\IStrategy;

/**
 * Class ApiStatistics
 *
 * Report a summary of the endpoints accessed by our import
 *
 * @package Schoolbox\External\Connector\Civica\Base\Endpoint;
 */
class ApiStatistics implements IStrategy
{
    /**
     * ApiStatistics constructor.
     *
     * @param LoggerInterface $log
     * @param IApi            $api
     */
    public function __construct(
        private LoggerInterface $log,
        private IApi $api
    ) {
    }


    /**
     * @inheritDococ
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
