<?php

/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\TassApi\Base\Endpoint;

use DomainException;
use InvalidArgumentException;
use RuntimeException;
use Schoolbox\External\Importer\Endpoint\Specification\ISpecification;
use Schoolbox\External\Importer\Endpoint\Status\IStatus;

/**
 * Class Status
 *
 * @package Schoolbox\External\Connector\TassApi\Base\Endpoint
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
     * @throws DomainException
     * @throws InvalidArgumentException
     * @throws \External\Tass\Api\Exception\InvalidApiKey
     */
    public function isReachable(): bool
    {
        try {
            (new Api($this->host, $this->key, Codec\DSN::MODULE_LMS_INTEGRATION))->retrieveDocument('getStudents', [
                'currentstatus' => 'current'
            ]);

            return true;
        } catch (RuntimeException $e) {
            // noop - something died
        }

        return false;
    }


    /**
     * @inheritDoc
     *
     * @return array
     * @throws InvalidArgumentException
     * @throws \External\Tass\Api\Exception\InvalidApiKey
     */
    public function getReport(): array
    {
        $specification = new Specification($this->host, $this->key);

        try {
            return $specification->check();
        } catch (DomainException | RuntimeException $e) {
            return [[
                'message' => 'Cannot access api endpoint',
                'reason' => $e->getMessage(),
                'status' => ISpecification::STATUS_ERROR
            ]];
        }
    }
}
