<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\External\Connector\Isams\Base\Endpoint;

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
        /* BatchXML is configured to dump you the entire xml feed; you can't just get a portion
         * which means you're downloading boatloads of data just to see if you could
         *
         * Use an ultra cheep way at the cost of some neatness
         */
        $result = @get_headers((string)$this->api->getUrl());

        if (is_array($result)) {
            return strpos($result[0], '200') > -1;
        }

        return false;
    }


    /**
     * @inheritDoc
     *
     * @return string
     * @throws InvalidArgumentException
     * @throws InvalidUriPartException
     */
    public function getReport(): string
    {
        $specification = new Specification(null, new Batchxml($this->api));

        try {
            return implode("\n", $specification->check());
        } catch (\RuntimeException $e) {
            return "Cannot access api endpoint: {$e->getMessage()}";
        }
    }
}
