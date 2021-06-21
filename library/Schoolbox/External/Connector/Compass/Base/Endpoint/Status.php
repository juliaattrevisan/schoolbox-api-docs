<?php

/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Compass\Base\Endpoint;

use Schoolbox\External\Importer\Endpoint\Specification\ISpecification;
use Schoolbox\External\Importer\Endpoint\Status\IStatus;

/**
 * Class Status
 *
 * @package Schoolbox\External\Connector\Compass\Base\Endpoint
 */
class Status implements IStatus
{

    /**
     * @var Api
     */
    private Api $api;


    /**
     * Status constructor.
     *
     * @param string $host
     * @param string $key
     * @throws \GuzzleHttp\Exception\InvalidArgumentException
     */
    public function __construct(string $host, string $key)
    {
        $this->api = new Api($host, $key);
    }


    /**
     * @inheritDoc
     *
     * @return bool
     * @throws \DomainException
     * @throws \JsonException
     * @throws \RuntimeException
     */
    public function isReachable(): bool
    {
        $result = $this->api->retrieveDocument('person/v1/GetStudents', [
            'lBound' => \Alaress_Date_Format::now()->getDatabaseDate() . 'T00:00:00Z',
            'uBound' => \Alaress_Date_Format::now()->addDays(1)->getDatabaseDate() . 'T00:00:00Z'
        ]);

        return (bool)$result;
    }


    /**
     * @inheritDoc
     *
     * @return array
     */
    public function getReport(): array
    {
        try {
            return [];
        } catch (\RuntimeException $e) {
            return [[
                 'message' => 'Cannot access api endpoint',
                 'reason' => $e->getMessage(),
                 'status' => ISpecification::STATUS_ERROR
            ]];
        }
    }
}
