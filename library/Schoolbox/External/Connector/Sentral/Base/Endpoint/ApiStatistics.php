<?php

/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Sentral\Base\Endpoint;

use Psr\Log\LoggerInterface;
use Schoolbox\External\Importer\Endpoint\IStrategy;

/**
 * Class ApiStatistics
 *
 * Report a summary of the endpoints accessed by our import
 *
 * @package Schoolbox\External\Connector\Sentral\Base\Endpoint
 */
class ApiStatistics implements IStrategy
{

    /**
     * @var LoggerInterface
     */
    private $log;


    /**
     * @var IApi
     */
    private $api;


    /**
     * ApiCertification constructor.
     *
     * @param LoggerInterface|null $log
     * @param IApi                 $api
     */
    public function __construct(?LoggerInterface $log, IApi $api)
    {
        $this->log = $log;
        $this->api = $api;
    }


    /**
     * @inheritDococ
     */
    public function import(): void
    {
        $this->log->info('');
        $this->log->info('API Endpoint Usage');
        $this->log->info('(Note that each endpoint can configure page size...)');
        $this->log->info('');

        $apiUsage = $this->api->getUsage()->retrieve();

        foreach ($apiUsage as $endpoint => $num) {
            $this->log->info(sprintf(
                '    %s %s',
                $endpoint,
                $num
            ));
        }

        $this->log->info('');

        $this->log->info(sprintf(
            '    Total %s',
            array_sum(array_values($apiUsage))
        ));

        $this->log->info('');
    }
}
