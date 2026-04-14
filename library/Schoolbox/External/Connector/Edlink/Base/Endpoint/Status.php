<?php

/**
 * @copyright   Copyright 2026, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Edlink\Base\Endpoint;

use Schoolbox\External\Importer\Endpoint\Specification\ISpecification;
use Schoolbox\External\Importer\Endpoint\Status\IStatus;
use SensitiveParameter;

/**
 * Class Status
 *
 * @package Schoolbox\External\Connector\Edlink\Base\Endpoint
 */
class Status implements IStatus
{
    /**
     * Status constructor.
     */
    public function __construct(
        private string $host,
        #[SensitiveParameter] private string $key
    ) {
    }

    /**
     * @inheritDoc
     */
    public function isReachable(): bool
    {
        try {
            (new Api(host: $this->host, apiKey: $this->key))->retrieve('GET', 'people?$first=1');
        } catch (\DomainException | \InvalidArgumentException | \RuntimeException $e) {
            return false;
        }

        return true;
    }

    /**
     * @inheritDoc
     */
    public function getReport(): ?array
    {
        try {
            $api = new Api(host: $this->host, apiKey: $this->key);
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
