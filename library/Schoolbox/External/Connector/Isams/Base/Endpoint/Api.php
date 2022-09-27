<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Isams\Base\Endpoint;

use Alaress_Curl_Url;
use Laminas\Uri\Exception\InvalidArgumentException;
use Laminas\Uri\Exception\InvalidUriException;
use Laminas\Uri\Exception\InvalidUriPartException;
use RuntimeException;
use Laminas\Uri\Exception;
use Laminas\Uri\Uri;
use Schoolbox\External\Connector\Isams\Base\Endpoint\Codec\DSN;

/**
 * Class Api
 *
 * @package Schoolbox\External\Connector\Isams\Base\Endpoint
 */
class Api implements IApi
{
    /**
     * The host of the iSAMS server
     *
     * @var string
     */
    private string $host;


    /**
     * The API key for this call, provided by the school
     *
     * @var string
     */
    private string $apiKey;


    /**
     * The iSAMS module that you're accessing;
     * corresponds to one of the DSN consts
     *
     * @var string
     */
    private string $module;


    /**
     * @var Uri|null
     */
    private ?Uri $url = null;


    /**
     * Constructor
     *
     * @param string $host
     * @param string $apiKey
     * @param string $module
     */
    public function __construct(string $host, string $apiKey, string $module)
    {
        $this->host   = $host;
        $this->apiKey = $apiKey;
        $this->module = $module;
    }


    /**
     * Retrieves a XML document representing the result from the API call.
     *
     * @return string|null
     * @throws InvalidArgumentException
     * @throws InvalidUriException
     * @throws InvalidUriPartException
     * @throws RuntimeException if the retrieval of the XML doc failed
     * @throws \DomainException
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
                'XML retrieval failed: response code %d, message: %s',
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
     * @throws Exception\InvalidUriPartException|\DomainException
     */
    public function getUrl(): Uri
    {
        $credentials = (new DSN())->decode($this->apiKey);

        if (!$this->url) {
            /* Find the path corresponding to the module we found
             * The module we found is in $matches[1]
             */
            $path = DSN::PATHS[$this->module];

            $this->url = (new Uri())
                ->setScheme('https')
                ->setHost(rtrim($this->host, "/ \n\r\t\v\x00"))
                ->setPath("/{$path}")
                ->setQuery(['apikey' => $credentials['modules'][$this->module]['key']]);
        }

        return $this->url;
    }


    /**
     * Set up curl instance
     *
     * @return Alaress_Curl_Url
     * @throws InvalidArgumentException
     * @throws InvalidUriException
     * @throws InvalidUriPartException
     * @throws \DomainException
     */
    private function initCurl(): \Alaress_Curl_Url
    {
        return new Alaress_Curl_Url($this->getUrl()->toString());
    }


    /**
     * Save the xml to $filename in the root folder of the instance
     *
     * @param string $filename
     * @throws Exception\InvalidArgumentException
     * @throws Exception\InvalidUriPartException
     * @throws RuntimeException
     */
    public function saveToFile(string $filename): void
    {
        $data = $this->retrieveDocument();

        $filepointer = fopen("../{$filename}", 'w+');
        fputs($filepointer, $data);
        fclose($filepointer);
    }
}
