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
     * @throws \JsonException
     */
    public function isReachable(): bool
    {
        /* Try accessing an intentionally broken place
         */
        $endpoint = 'getStudents';

        try {
            (new Api($this->host, $this->key, Codec\DSN::MODULE_LMS_INTEGRATION))->retrieveDocument($endpoint, [
                'test' => true
            ]);
        } catch (RuntimeException $e) {
            /* We only need to know that the server is alive; even if we
             * get an error message in response, it tells us the server is here
             * and waiting to accept actual calls with valid data
             *
             * Endpoint name appears in the error message.
             */
            if (strpos($e->getMessage(), $endpoint) !== false) {
                return true;
            }
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
