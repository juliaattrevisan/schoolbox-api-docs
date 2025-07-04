<?php

/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Compass\Base\Endpoint;

use Schoolbox\External\Importer\Endpoint\Specification\ISpecification;
use Schoolbox\External\Importer\Endpoint\Status\IStatus;
use SensitiveParameter;

/**
 * Class Status
 *
 * @package Schoolbox\External\Connector\Compass\Base\Endpoint
 */
class Status implements IStatus
{
    /**
     * @var IApi
     */
    private IApi $api;


    /**
     * Status constructor.
     *
     * @param string $host
     * @param string $key
     * @param string $customUserAgent
     */
    public function __construct(string $host, #[SensitiveParameter] string $key, string $customUserAgent)
    {
        $this->api = new Api($host, $key, $customUserAgent);
    }


    /**
     * @inheritDoc
     *
     * @return bool
     */
    public function isReachable(): bool
    {
        /* ISO8601 format:
         * https://stackoverflow.com/questions/903203/how-to-display-a-date-as-iso-8601-format-with-php
         */
        try {
            $result = $this->api->retrieveDocument('person/v1/GetStudents', [
                'lBound' => \Alaress_Date_Format::now()->getUsingFormat('c'),
                'uBound' => \Alaress_Date_Format::now()->addDays(1)->getUsingFormat('c')
            ]);
        } catch (\RuntimeException | \DomainException | \JsonException $e) {
            return false;
        }

        return (bool)$result;
    }


    /**
     * @inheritDoc
     *
     * @return array
     */
    public function getReport(): array
    {
        $specification = new Specification($this->api);

        try {
            return $specification->check();
        } catch (\RuntimeException $e) {
            return [[
                 'message' => 'Cannot access api endpoint',
                 'reason' => $e->getMessage(),
                 'status' => ISpecification::STATUS_ERROR
            ]];
        }
    }
}
