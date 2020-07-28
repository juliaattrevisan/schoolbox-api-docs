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
use Schoolbox\External\Importer\Endpoint\Status\IStatus;

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
        try {
            $this->api->retrieveDocument('getStudents', [
                'currentstatus' => 'current'
            ]);

            return true;
        } catch (RuntimeException $e) {
            // noop - something died
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
        $specification = new Specification($this->api);

        try {
            return $specification->check();
        } catch (RuntimeException $e) {
            return ["Cannot access api endpoint: {$e->getMessage()}"];
        }
    }
}
