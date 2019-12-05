<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\External\Importer\Endpoint\Status;

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
    private $host;


    /**
     * API Endpoint access token
     *
     * @var string
     */
    private $key;


    /**
     * Factory constructor.
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
     * @param string $type
     *
     * @return IStatus|null
     */
    public function produce(string $type): ?IStatus
    {
        switch ($type) {
            case Endpoint\Factory::ISAMS:
                return new Connector\Isams\Base\Endpoint\Status($this->host, $this->key);

            case Endpoint\Factory::KAMAR:
            case Endpoint\Factory::SENTRAL:
            default:
                // Use manual csv imports
                return null;
        }
    }
}
