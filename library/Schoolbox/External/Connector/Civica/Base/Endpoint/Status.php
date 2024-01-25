<?php

/**
 * @copyright   Copyright 2023, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Civica\Base\Endpoint;

use GuzzleHttp\Exception\InvalidArgumentException;
use Schoolbox\External\Importer\Endpoint\Status\IStatus;
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
     * @throws InvalidArgumentException
     * @throws \DomainException
     * @throws \JsonException
     * @throws \RuntimeException
     */
    public function isReachable(): bool
    {
        (new Api($this->host, $this->key))->retrieveForJurisdiction('GET', 'Students/Jurisdiction', ['pageSize' => 1]);

        return true;
    }


    /**
     * @inheritDoc
     *
     * @return array
     * @throws InvalidArgumentException
     * @throws \DomainException
     * @throws \JsonException
     * @throws \RuntimeException
     */
    public function getReport(): array
    {
        return (new Specification(new Api($this->host, $this->key)))->check();
    }
}
