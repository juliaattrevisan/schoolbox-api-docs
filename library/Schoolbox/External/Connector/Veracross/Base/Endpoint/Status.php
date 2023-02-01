<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Veracross\Base\Endpoint;

//use Schoolbox\External\Connector\Veracross\Base\Endpoint\Specification;
//use Schoolbox\External\Importer\Endpoint\Specification\ISpecification;
use Schoolbox\External\Importer\Endpoint\Status\IStatus;

/**
 * Class Status
 *
 * @package Schoolbox\External\Connector\Veracross\Base\Endpoint
 */
class Status implements IStatus
{
    /**
     * @var string
     */
    private string $host;


    /**
     * @var string
     */
    private string $key;


    /**
     * Status constructor.
     *
     * @param string $host
     * @param string $key
     */
    public function __construct(string $host, string $key)
    {
        $this->host = $host;
        $this->key = $key;
    }


    /**
     * @inheritDoc
     *
     * @return bool
     * @throws \DomainException
     * @throws \GuzzleHttp\Exception\InvalidArgumentException
     * @throws \JsonException
     * @throws \RuntimeException
     */
    public function isReachable(): bool
    {
        /* Try to fetch one row from this generic endpoint I choze
         * Api instance will go and fetch an access token to do it,
         * so it might take a few seconds to complete.
         */
        $scope = 'students:list';

        (new Api($this->host, $this->key, $scope))->retrieveOne('GET', 'students', 1, 1, []);

        return true;
    }


    /**
     * @inheritDoc
     *
     * @return array
     * @throws \InvalidArgumentException
     */
    public function getReport(): array
    {
        return [];
//        try {
//            $api = new Api($this->host, $this->key);
//        } catch (\DomainException $e) {
//            return [[
//                'message' => 'Cannot access api endpoint',
//                'reason' => $e->getMessage(),
//                'status' => ISpecification::STATUS_ERROR
//            ]];
//        }
//
//        $specification = new Specification($api);
//
//        return $specification->check();
    }
}
