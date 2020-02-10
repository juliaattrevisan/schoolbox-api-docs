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
use UnexpectedValueException;

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
     * @throws UnexpectedValueException
     */
    public function isReachable(): bool
    {

        /* There is a sentral/ping route, but it deliberately returns no data
         * but the api connectivity doesn't recognise this as valid; instead
         * of making it understand this scenario, pick a different one instead
         */
        return (bool)$this->api->retrieveDocument('sentral/status');
    }


    /**
     * @inheritDoc
     *
     * @return array
     */
    public function getReport(): array
    {
        $specification = new Specification($this->api);

        try {
            return $specification->check();
        } catch (\RuntimeException $e) {
            return ["Cannot access api endpoint: {$e->getMessage()}"];
        }
    }
}
