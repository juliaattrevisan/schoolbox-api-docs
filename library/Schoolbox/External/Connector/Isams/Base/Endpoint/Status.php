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
        /* BatchXML is configured to dump you the entire xml feed; you can't just get a portion
         * which means you're downloading boatloads of data just to see if you could
         *
         * Use an ultra cheep way at the cost of some neatness
         */
        $api = new Api\Batch\Api($this->host, $this->dsn, Codec\DSN::MODULE_BATCHXML);

        $result = @get_headers((string)$api->getUrl());

        if (is_array($result)) {
            return strpos($result[0], '200') > -1;
        }

        return false;
    }


    /**
     * @inheritDoc
     *
     * @return array
     */
    public function getReport(): array
    {
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
}
