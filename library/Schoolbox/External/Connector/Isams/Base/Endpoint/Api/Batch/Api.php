<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Isams\Base\Endpoint\Api\Batch;

use Alaress_Curl_Url;
use RuntimeException;
use Schoolbox\Url\Uri;
use Schoolbox\External\Connector\Isams\Base\Endpoint\Codec\DSN;
use SensitiveParameter;

/**
 * Class Api
 *
 * @package Schoolbox\External\Connector\Isams\Base\Endpoint\Api\Batch
 */
class Api implements IApi
{
    /**
     * @var Uri|null
     */
    private ?Uri $url = null;


    /**
     * @var array
     */
    private array $options = [];


    /**
     * Constructor
     *
     * @param string $host    The host of the iSAMS server
     * @param string $apiKey  The API key for this call, provided by the school
     * @param string $module  The iSAMS module that you're accessing; corresponds to one of the DSN consts
     */
    public function __construct(
        private string $host,
        #[SensitiveParameter] private string $apiKey,
        private string $module
    ) {
    }


    /**
     * Retrieves a XML document representing the result from the API call.
     *
     * @return string|null
     * @throws RuntimeException if the retrieval of the XML doc failed
     * @throws \DomainException
     * @throws \InvalidArgumentException
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
     * Set up curl instance
     *
     * @return Alaress_Curl_Url
     * @throws \DomainException
     * @throws \InvalidArgumentException
     */
    private function initCurl(): \Alaress_Curl_Url
    {
        return new Alaress_Curl_Url((string)$this->getUrl());
    }


    /**
     * Cache a url definition then return its value
     *
     * @return Uri
     * @throws \DomainException
     * @throws \InvalidArgumentException
     */
    public function getUrl(): Uri
    {
        $credentials = (new DSN())->decode($this->apiKey);

        /* Remember the optional pieces we offer
         */
        $this->options = $credentials['options'] ?? [];

        if (!$this->url) {
            /* Find the path corresponding to the module we found
             * The module we found is in $matches[1]
             */
            $path = DSN::PATHS[$this->module];

            /* Only the url changes; the api key remains the same
             * for everything you do in isams
             */
            $this->url = (new Uri())
                ->withScheme('https')
                ->withHost(rtrim($this->host, "/ \n\r\t\v\x00"))
                ->withPath("/{$path}")
                ->appendQueryParameters(['apikey' => $credentials['modules'][$this->module]['key']]);
        }

        return $this->url;
    }


    /**
     * @return array
     */
    public function getOptions(): array
    {
        return $this->options;
    }


    /**
     * Save the xml to $filename in the root folder of the instance
     *
     * @param string $filename
     * @throws RuntimeException
     * @throws \DomainException
     * @throws \InvalidArgumentException
     */
    public function saveToFile(string $filename): void
    {
        $data = $this->retrieveDocument();

        $filepointer = fopen("../{$filename}", 'w+');
        fputs($filepointer, $data);
        fclose($filepointer);
    }
}
