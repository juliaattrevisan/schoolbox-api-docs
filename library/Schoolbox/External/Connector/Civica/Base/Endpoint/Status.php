<?php

/**
 * @copyright   Copyright 2023, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Civica\Base\Endpoint;

use GuzzleHttp\Exception\InvalidArgumentException;
use Laminas\Uri\Exception\InvalidUriPartException;
use Schoolbox\External\Importer\Endpoint\Status\IStatus;
use Schoolbox\External\Importer\Endpoint\Specification\ISpecification;
use SensitiveParameter;

/**
 * Class Status
 *
 * @package Schoolbox\External\Connector\Civica\Base\Endpoint
 */
class Status implements IStatus
{
    /**
     * Status constructor.
     *
     * @param string $host
     * @param string $key
     * @throws InvalidArgumentException
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
     */
    public function isReachable(): bool
    {
        (new Api($this->host, $this->key))->retrieveOne('GET', 'Students/Jurisdiction', ['pageSize' => 1]);
        return true;
    }


    /**
     * @inheritDoc
     *
     * @return array
     * @throws \Laminas\Uri\Exception\InvalidArgumentException
     * @throws InvalidUriPartException
     */
    public function getReport(): array
    {
        $specification = new Specification(new Api($this->host, $this->key));

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
