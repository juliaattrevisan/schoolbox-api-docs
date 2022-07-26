<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Importer\Endpoint\Status;

use DomainException;
use InvalidArgumentException;
use Schoolbox\External\Connector;
use Schoolbox\External\Importer\Endpoint;

/**
 * Class Factory
 */
class Factory
{

    /**
     * API Endpoint url
     *
     * @var string
     */
    private string $host;


    /**
     * API Endpoint access token
     *
     * @var string
     */
    private string $key;


    /**
     * @var bool
     */
    private bool $includePhoto;


    /**
     * Factory constructor.
     *
     * @param string $host
     * @param string $key
     * @param bool   $includePhoto
     */
    public function __construct(string $host, string $key, bool $includePhoto)
    {
        $this->host = $host;
        $this->key = $key;
        $this->includePhoto = $includePhoto;
    }


    /**
     * @param string $type
     *
     * @return IStatus|null
     * @throws DomainException
     * @throws InvalidArgumentException
     */
    public function produce(string $type): ?IStatus
    {
        switch ($type) {
            case Endpoint\Factory::ISAMS:
                return new Connector\Isams\Base\Endpoint\Status($this->host, $this->key);

            case Endpoint\Factory::KAMAR:
                return new Connector\Kamar\Base\Endpoint\Status($this->host);

            case Endpoint\Factory::SENTRAL:
                return new Connector\Sentral\Base\Endpoint\Status($this->host, $this->key);

            case Endpoint\Factory::TASS_API:
                return new Connector\TassApi\Base\Endpoint\Status($this->host, $this->key, $this->includePhoto);

            case Endpoint\Factory::COMPASS:
                return new Connector\Compass\Base\Endpoint\Status($this->host, $this->key);

            case Endpoint\Factory::WONDE:
                return new Connector\Wonde\Base\Endpoint\Status($this->host, $this->key);

            default:
                // Use manual csv imports
                return null;
        }
    }
}
