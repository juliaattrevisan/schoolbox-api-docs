<?php
/**
 * A singleton wrapper around a Predis instance.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Alaress;

use Predis;

class Redis {

    /**
     * @var static
     */
    protected static $instance;

    /**
     * @var Predis\ClientInterface
     */
    protected $client;

    /**
     * Gets singleton instance of this wrapper.
     *
     * @return static
     */
    public static function getInstance() {
        if (!(static::$instance instanceof static)) {
            static::$instance = new static();
        }
        return static::$instance;
    }

    /**
     * Connects this wrapper to Redis.
     *
     * @see Predis\Client::__construct() for parameter documentation.
     *
     * @param  mixed $parameters
     * @param  mixed $options
     * @return $this
     */
    public function connect($parameters = null, $options = null) {
        $this->setClient(new Predis\Client($parameters, $options));
        return $this;
    }

    /**
     * @return Predis\ClientInterface
     */
    public function getClient() {
        return $this->client;
    }

    /**
     * @param  Predis\ClientInterface $client
     * @return $this
     */
    protected function setClient($client) {
        $this->client = $client;
        return $this;
    }

}