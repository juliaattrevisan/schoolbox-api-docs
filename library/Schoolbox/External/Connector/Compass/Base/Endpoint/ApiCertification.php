<?php

/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Compass\Base\Endpoint;

use Psr\Log\LoggerInterface;
use Schoolbox\External\Importer\Endpoint\IStrategy;

/**
 * Class ApiCertification
 *
 * Report a summary of the endpoints accessed by our import
 *
 * @package Schoolbox\External\Connector\Compass\Base\Endpoint
 */
class ApiCertification implements IStrategy
{

    /**
     * @var LoggerInterface|null
     */
    private ?LoggerInterface $log;


    /**
     * @var IApi
     */
    private IApi $api;


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
