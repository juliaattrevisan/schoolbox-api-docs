<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\External\Connector\Example\Base\Endpoint;

use Alaress_Curl_Url;
use RuntimeException;
use Zend\Uri\Exception;
use Zend\Uri\Uri;

/**
 * Class Api
 *
 * @package Schoolbox\External\Connector\Example\Base\Endpoint
 */
class Api implements IApi
{

    /**
     * The host server
     *
     * @var string
     */
    private $host;


    /**
     * The API key for this call, provided by the school
     *
     * @var string
     */
    private $apiKey;


    /**
     * @var Uri
     */
    private $url;


    /**
     * Constructor
     *
     * @param string $host
     * @param string $apiKey
     */
    public function __construct(string $host, string $apiKey)
    {
        $this->host   = $host;
        $this->apiKey = $apiKey;
    }


    /**
     * Retrieves endpoint data representing the result from the API call.
     *
     * @return string|null
     * @throws RuntimeException if the retrieval of the endpoint failed
     * @throws Exception\InvalidArgumentException
     * @throws Exception\InvalidUriPartException
     */
    public function retrieveDocument(): ?string
    {
        $curl = $this->initCurl();
        $curl->exec();

        if (empty($curl->getErrors())) {
            // Too big to parse
            return $curl->getResult();
        }

        throw new RuntimeException(
            sprintf(
                'Endpoint retrieval failed: response code %d, message: %s',
                $curl->getResponseCode(),
                implode(', ', $curl->getErrors())
            ),
            $curl->getResponseCode()
        );
    }


    /**
     * Cache a url definition then return its value
     *
     * @return Uri
     * @throws Exception\InvalidArgumentException
     * @throws Exception\InvalidUriPartException
     */
    public function getUrl(): Uri
    {
        /* TODO Set Path
         */
        if (!$this->url) {
            $this->url = (new Uri())
                ->setScheme('https')
                ->setHost($this->host)
                ->setPath('')
                ->setQuery(['apikey' => $this->apiKey]);
        }

        return $this->url;
    }


    /**
     * Set up curl instance
     *
     * @return Alaress_Curl_Url
     * @throws Exception\InvalidArgumentException
     * @throws Exception\InvalidUriPartException
     */
    private function initCurl(): \Alaress_Curl_Url
    {
        return new Alaress_Curl_Url($this->getUrl());
    }
}
