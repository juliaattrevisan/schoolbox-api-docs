<?php

/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Clipboard;

use Psr\Log\LoggerInterface;

/**
 * Class ApiStatistics
 *
 * Report a summary of the endpoints accessed by our import
 *
 * @package Schoolbox\Clipboard
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
     * ApiCertification constructor.
     *
     * @param LoggerInterface $log
     * @param IApi            $api
     */
    public function __construct(LoggerInterface $log, IApi $api)
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
