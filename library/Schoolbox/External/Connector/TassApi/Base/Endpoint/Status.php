<?php
/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\External\Connector\TassApi\Base\Endpoint;

use Schoolbox\External\Importer\Endpoint\Specification\ISpecification;
use Schoolbox\External\Importer\Endpoint\Status\IStatus;
use Zend\Uri\Exception\InvalidArgumentException;
use Zend\Uri\Exception\InvalidUriPartException;

/**
 * Class Status
 *
 * @package Schoolbox\External\Connector\Isams\Base\Endpoint
 */
class Status implements IStatus
{

    /**
     * @var Api
     */
    private $api;


    /**
     * Status constructor.
     *
     * @param string $host
     * @param string $key
     */
    public function __construct(string $host, string $key)
    {
        $this->api = new Api($host, $key);
    }


    /**
     * @inheritDoc
     *
     * @return bool
     * @throws InvalidArgumentException
     * @throws InvalidUriPartException
     */
    public function isReachable(): bool
    {
        return false;
    }


    /**
     * @inheritDoc
     *
     * @return array
     * @throws InvalidArgumentException
     * @throws InvalidUriPartException
     */
    public function getReport(): array
    {
        return [[
            'message' => 'Cannot access api endpoint: not implemented',
            'status' => ISpecification::STATUS_ERROR
        ]];
    }
}
