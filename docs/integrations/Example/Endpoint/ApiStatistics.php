<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Veracross\Base\Endpoint;

use Psr\Log\LoggerInterface;
use Schoolbox\External\Importer\Endpoint\IStrategy;

/**
 * Class ApiStatistics
 *
 * Report a summary of the endpoints accessed by our import
 *
 * @package Schoolbox\External\Connector\Example\Base\Endpoint
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
        $this->log->info('');

        $apiUsage = $this->api->getUsage()->retrieve();

        $headings = [
            'endpoint' => 'Endpoint',
            'accesses' => 'Accesses',
            'duration' => 'Time (sec)'
        ];

        /* Find the longest value for this column of data
         */
        $maxEndpointLength = strlen($headings['endpoint']);
        $maxAccessesLength = strlen($headings['accesses']);
        $maxDurationLength = strlen($headings['duration']);

        foreach ($apiUsage as $endpoint => $data) {
            $maxEndpointLength = max($maxEndpointLength, strlen($endpoint));
            $maxAccessesLength = max($maxAccessesLength, strlen((string)$data['count']));
            $maxDurationLength = max($maxDurationLength, strlen(number_format($data['duration'], 2)));
        }

        /* Extra padding for aesthetic reasons only
         */
        $maxEndpointLength += 5;
        $maxDurationLength += 5;

        $this->log->info(sprintf(
            '    %s%s%s',
            str_pad($headings['endpoint'], $maxEndpointLength, ' ', STR_PAD_RIGHT),
            str_pad($headings['accesses'], $maxAccessesLength, ' ', STR_PAD_LEFT),
            str_pad($headings['duration'], $maxDurationLength, ' ', STR_PAD_LEFT),
        ));

        $this->log->info('    ' . str_pad('', $maxEndpointLength + $maxAccessesLength + $maxDurationLength, '-'));

        foreach ($apiUsage as $endpoint => $data) {
            $this->log->info(sprintf(
                '    %s%s%s',
                str_pad($endpoint, $maxEndpointLength, ' ', STR_PAD_RIGHT),
                str_pad((string)$data['count'], $maxAccessesLength, ' ', STR_PAD_LEFT),
                str_pad(number_format($data['duration'], 2), $maxDurationLength, ' ', STR_PAD_LEFT),
            ));
        }

        $this->log->info('');
    }
}
