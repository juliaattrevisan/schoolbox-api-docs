<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\Home;

use Alaress_Date_Format;
use Aws\Api\DateTimeResult;
use Aws\CloudWatch\CloudWatchClient;

use function end;

/**
 * Fetches CloudWatch metrics.
 */
class CloudWatchMetrics
{
    /** @var CloudWatchClient */
    private $cloudWatchClient;

    /**
     * @param CloudWatchClient $cloudWatchClient
     */
    public function __construct(CloudWatchClient $cloudWatchClient)
    {
        $this->cloudWatchClient = $cloudWatchClient;
    }


    /**
     * Returns a S3 Buckets size
     *
     * @param string $bucketName
     * @param string $storageType
     * @return float
     */
    public function getBucketSize(string $bucketName, string $storageType = 'StandardStorage'): float
    {
        $endTime             = Alaress_Date_Format::now();
        $startTime           = (clone $endTime)->addDays(-7);
        $result              = $this->cloudWatchClient->getMetricStatistics([
            'Namespace'  => 'AWS/S3',
            'MetricName' => 'BucketSizeBytes',
            'Dimensions' => [
                [
                    'Name'  => 'BucketName',
                    'Value' => $bucketName,
                ],
                [
                    'Name'  => 'StorageType',
                    'Value' => $storageType,
                ],
            ],
            'StartTime'  => $startTime->getUsingFormat(Alaress_Date_Format::FORMAT_DATETIME_UTC),
            'EndTime'    => $endTime->getUsingFormat(Alaress_Date_Format::FORMAT_DATETIME_UTC),
            'Period'     => '86400',
            'Statistics' => ['Average'],
            'Unit'       => 'Bytes',
        ]);

        $bucketSize          = 0.0;
        $mostRecentTimestamp = null;
        foreach ($result->get('Datapoints') ?? [] as $dataPoint) {
            /** @var DateTimeResult $timestamp */
            $timestamp = $dataPoint['Timestamp'];
            if ($mostRecentTimestamp === null || $timestamp->getTimestamp() > $mostRecentTimestamp) {
                $mostRecentTimestamp = $timestamp->getTimestamp();
                $bucketSize          = $dataPoint['Average'];
            }
        }
        return $bucketSize;
    }


    /**
     * Returns the number of objects in a bucket
     *
     * @param string $bucketName
     * @param string $storageType
     * @return int
     */
    public function getNumberOfObjects(string $bucketName, string $storageType = 'AllStorageTypes'): int
    {
        $endTime             = Alaress_Date_Format::now();
        $startTime           = (clone $endTime)->addDays(-7);
        $result              = $this->cloudWatchClient->getMetricStatistics([
            'Namespace'  => 'AWS/S3',
            'MetricName' => 'NumberOfObjects',
            'Dimensions' => [
                [
                    'Name'  => 'BucketName',
                    'Value' => $bucketName,
                ],
                [
                    'Name'  => 'StorageType',
                    'Value' => $storageType,
                ],
            ],
            'StartTime'  => $startTime->getUsingFormat(Alaress_Date_Format::FORMAT_DATETIME_UTC),
            'EndTime'    => $endTime->getUsingFormat(Alaress_Date_Format::FORMAT_DATETIME_UTC),
            'Period'     => '86400',
            'Statistics' => ['Average'],
            'Unit'       => 'Count',
        ]);
        $dataPoints          = $result->get('Datapoints');
        $mostRecentDataPoint = end($dataPoints);
        return (int)$mostRecentDataPoint['Average'];
    }
}
