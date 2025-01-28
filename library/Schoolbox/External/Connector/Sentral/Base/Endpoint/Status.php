<?php

/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Sentral\Base\Endpoint;

use Schoolbox\External\Importer\Endpoint\Specification\ISpecification;
use Schoolbox\External\Importer\Endpoint\Status\IStatus;
use SensitiveParameter;

/**
 * Class Status
 *
 * @package Schoolbox\External\Connector\Sentral\Base\Endpoint
 */
class Status implements IStatus
{
    /**
     * Status constructor.
     *
     * @param string $host
     * @param string $key
     * @throws \DomainException
     * @throws \InvalidArgumentException
     */
    public function __construct(private string $host, #[SensitiveParameter] private string $key)
    {
    }


    /**
     * @inheritDoc
     *
     * @return bool
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \InvalidArgumentException
     * @throws \DomainException
     */
    public function isReachable(): bool
    {
        $today = date('Y-m-d');
        $nextUrl = "enrolments/academic-period?date={$today}&limit=1";

        (new Api($this->host, $this->key))->retrieveDocument($nextUrl);

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
        try {
            $api = new Api($this->host, $this->key);
        } catch (\DomainException $e) {
            return [[
                'message' => 'Cannot access api endpoint',
                'reason' => $e->getMessage(),
                'status' => ISpecification::STATUS_ERROR
            ]];
        }

        $specification = new Specification($api);

        return $specification->check();
    }
}
