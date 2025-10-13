<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Veracross\Base\Endpoint;

use Schoolbox\External\Importer\Endpoint\Specification\ISpecification;
use Schoolbox\External\Importer\Endpoint\Status\IStatus;
use SensitiveParameter;

/**
 * Class Status
 *
 * @package Schoolbox\External\Connector\Veracross\Base\Endpoint
 */
class Status implements IStatus
{
    /**
     * Status constructor.
     *
     * @param string $host
     * @param string $key
     */
    public function __construct(
        private string $host,
        #[SensitiveParameter] private string $key
    ) {
    }


    /**
     * @inheritDoc
     *
     * @return bool
     * @throws \JsonException
     */
    public function isReachable(): bool
    {
        /* Try to fetch one row from this generic endpoint I choze
         * Api instance will go and fetch an access token to do it,
         * so it might take a few seconds to complete.
         */
        $scope = 'students:list';

        try {
            (new Api(host: $this->host, apiKey: $this->key))->retrieveOne('GET', 'students', $scope, 1, 1, []);
        } catch (\DomainException | \InvalidArgumentException | \RuntimeException $e) {
            return false;
        }

        return true;
    }


    /**
     * @inheritDoc
     *
     * @return array
     */
    public function getReport(): array
    {
        try {
            $api = new Api(host: $this->host, apikey: $this->key);
        } catch (\DomainException | \InvalidArgumentException $e) {
            return [[
                'message' => 'Cannot access api endpoint',
                'reason' => $e->getMessage(),
                'status' => ISpecification::STATUS_ERROR
            ]];
        }

        return (new Specification($api))->check();
    }
}
