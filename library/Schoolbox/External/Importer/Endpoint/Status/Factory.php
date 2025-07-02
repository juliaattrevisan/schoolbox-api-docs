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
use SensitiveParameter;

/**
 * Class Factory
 */
class Factory
{
    /**
     * Factory constructor.
     *
     * @param string $host
     * @param string $key
     * @param bool $includePhoto
     * @param string $customUserAgent
     */
    public function __construct(
        private string $host,
        #[SensitiveParameter] private string $key,
        private bool $includePhoto,
        private string $customUserAgent
    ) {
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
                return new Connector\Compass\Base\Endpoint\Status($this->host, $this->key, $this->customUserAgent);

            case Endpoint\Factory::WONDE:
                return new Connector\Wonde\Base\Endpoint\Status($this->host, $this->key);

            case Endpoint\Factory::VERACROSS:
                return new Connector\Veracross\Base\Endpoint\Status($this->host, $this->key);

            case Endpoint\Factory::CIVICA:
                return new Connector\Civica\Base\Endpoint\Status($this->host, $this->key);

            default:
                // Use manual csv imports
                return null;
        }
    }
}
