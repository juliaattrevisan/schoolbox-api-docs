<?php
/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\External\Connector\Sentral\Base\Endpoint;

use DomainException;
use InvalidArgumentException;
use Schoolbox\External\Importer\Endpoint\Status\IStatus;

/**
 * Class Status
 *
 * @package Schoolbox\External\Connector\Sentral\Base\Endpoint
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
     * @throws DomainException
     * @throws InvalidArgumentException
     */
    public function __construct(string $host, string $key)
    {
        $this->api = new Api($host, $key);
    }


    /**
     * @inheritDoc
     *
     * @return bool
     */
    public function isReachable(): bool
    {
        return false;
    }


    /**
     * @inheritDoc
     *
     * @return string
     */
    public function getReport(): string
    {
        $specification = new Specification(null, $this->api);

        try {
            return implode("\n", $specification->check());
        } catch (\RuntimeException $e) {
            return "Cannot access api endpoint: {$e->getMessage()}";
        }
    }
}
