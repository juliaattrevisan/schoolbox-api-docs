<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Isams\Base\Endpoint\Api\Batch;

use Schoolbox\External\Connector\Isams\Base\Endpoint\Codec\DSN;

/**
 * Class MockFileApi
 *
 * A version of Api that can be used in unit tests
 * Loads an isams xml file from somewhere instead of their actual endpoint
 *
 * @package Schoolbox\External\Connector\Isams\Base\Endpoint\Api\Batch
 */
class MockFileApi implements IApi
{
    /**
     * @var array
     */
    private array $options = [];


    /**
     * MockFileApi constructor.
     *
     * @param string $file  Path to the local xml file from which to read
     * @param string $apiKey
     */
    public function __construct(private string $file, private string $apiKey)
    {
    }


    /**
     * Retrieves a XML document representing the result from the API call.
     *
     * @return string|null
     * @throws \DomainException
     */
    public function retrieveDocument(): ?string
    {
        $credentials = (new DSN())->decode($this->apiKey);

        /* Remember the optional pieces we offer
         */
        $this->options = $credentials['options'] ?? [];

        return file_get_contents($this->file);
    }


    /**
     * Used to apply instance-specific configuration options
     *
     * @return array
     */
    public function getOptions(): array
    {
        return $this->options;
    }
}
