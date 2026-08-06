<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Isams\Base\Endpoint\Api\Batch;

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
     * Batch API key.
     */
    private string $batchKey;


    /**
     * Batch HTTP client.
     */
    private Client $client;


    /**
     * Constructor
     *
     * @param string $host The host of the iSAMS server.
     * @param string $dsn Encoded iSAMS DSN.
     * @param string $module The iSAMS module being accessed.
     * @param Client|null $client Batch HTTP client.
     * @throws \DomainException When the DSN cannot be decoded.
     * @todo Migrate the Batch OAuth secret out of the legacy encoded DSN during configuration cleanup.
     */
    public function __construct(
        private string $host,
        #[SensitiveParameter] string $dsn,
        private string $module,
        ?Client $client = null
    ) {
        $credentials = (new DSN())->decode($dsn);
        $moduleCredentials = $credentials['modules'][$this->module];

        $this->options = $credentials['options'] ?? [];
        $this->batchKey = $moduleCredentials['key'];
        $this->client = $client ?? new Client(
            $this->host,
            $this->batchKey,
            $moduleCredentials['clientsecret'] ?? ''
        );
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
        return $this->client->get(DSN::PATHS[$this->module]);
    }

    /**
     * Check the configured Batch endpoint without downloading its XML body.
     *
     * @return void
     * @throws RuntimeException if the endpoint cannot be reached
     * @throws \DomainException
     * @throws \InvalidArgumentException
     */
    public function checkConnection(): void
    {
        $this->client->check(DSN::PATHS[$this->module]);
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
                ->appendQueryParameters(['apikey' => $this->batchKey]);
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
