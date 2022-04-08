<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Wonde\Base\Endpoint;

use Schoolbox\External\Importer\Endpoint\Specification\ISpecification;
use Schoolbox\External\Importer\Endpoint\Status\IStatus;

/**
 * Class Status
 *
 * @package Schoolbox\External\Connector\Wonde\Base\Endpoint
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
        $nextUrl = sprintf(
            'students?page=%s&per_page=%s',
            1,
            1
        );

        (new Api($this->host, $this->key))->retrieve('GET', $nextUrl, []);

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
