<?php

/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\TassApi\Base\Endpoint;

use Psr\Log\LoggerInterface;
use Schoolbox\External\Importer\Endpoint\IStrategy;

/**
 * Class ApiCertification
 *
 * Report a summary of the endpoints accessed by our import
 *
 * @package Schoolbox\External\Connector\TassApi\Base\Endpoint
 */
class ApiCertification implements IStrategy
{

    /**
     * @var LoggerInterface
     */
    private ?LoggerInterface $log;


    /**
     * @var IApi[]
     */
    private array $api;


    /**
     * ApiCertification constructor.
     *
     * @param LoggerInterface|null $log
     * @param IApi[]               $api
     */
    public function __construct(?LoggerInterface $log, array $api)
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

        foreach ($this->api as $api) {
            $apiUsage = $api->getUsage()->retrieve();

            foreach ($apiUsage as $module => $endpointUsage) {
                $this->log->info('');
                $this->log->info($module);

                foreach ($endpointUsage as $endpoint => $num) {
                    $this->log->info(sprintf(
                        '    %s %s',
                        $endpoint,
                        $num
                    ));
                }
            }
        }

        $this->log->info('');
    }
}
