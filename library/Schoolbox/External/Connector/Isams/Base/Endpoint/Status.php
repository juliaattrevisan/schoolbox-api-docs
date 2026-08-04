<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Isams\Base\Endpoint;

use Schoolbox\External\Importer\Endpoint\Specification\ISpecification;
use Schoolbox\External\Importer\Endpoint\Status\IStatus;
use SensitiveParameter;

/**
 * Class Status
 *
 * @package Schoolbox\External\Connector\Isams\Base\Endpoint
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
    private string $dsn;


    /**
     * Status constructor.
     *
     * @param string $host
     * @param string $dsn
     */
    public function __construct(string $host, #[SensitiveParameter] string $dsn)
    {
        $this->host = $host;
        $this->dsn = $dsn;
    }


    /**
     * @inheritDoc
     *
     * @return bool
     * @throws \DomainException
     */
    public function isReachable(): bool
    {
        if (!$this->hasBatchOAuthSecret()) {
            return false;
        }

        try {
            $api = new Api\Batch\Api($this->host, $this->dsn, Codec\DSN::MODULE_BATCHXML);
            $api->checkConnection();

            return true;
        } catch (\RuntimeException) {
            return false;
        }
    }


    /**
     * @inheritDoc
     *
     * @return array
     * @throws \DomainException When the iSAMS DSN cannot be decoded.
     */
    public function getReport(): array
    {
        if (!$this->hasBatchOAuthSecret()) {
            return [[
                'message' => 'Cannot access api endpoint',
                'reason' => 'Batch OAuth client secret is not configured.',
                'status' => ISpecification::STATUS_ERROR
            ]];
        }

        $specification = new Specification($this->host, $this->dsn);

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


    /**
     * Determine whether Batch OAuth credentials are configured.
     *
     * @return bool True when the Batch OAuth client secret is present.
     * @throws \DomainException When the iSAMS DSN cannot be decoded.
     */
    private function hasBatchOAuthSecret(): bool
    {
        $credentials = (new Codec\DSN())->decode($this->dsn);

        return ($credentials['modules'][Codec\DSN::MODULE_BATCHXML]['clientsecret'] ?? '') !== '';
    }
}
