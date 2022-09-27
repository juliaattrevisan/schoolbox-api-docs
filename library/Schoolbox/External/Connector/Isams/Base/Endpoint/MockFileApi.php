<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Isams\Base\Endpoint;

/**
 * Class MockFileApi
 *
 * A version of Api that can be used in unit tests
 * Loads an isams xml file from somewhere instead of their actual endpoint
 *
 * @package Schoolbox\External\Connector\Isams\Base\Endpoint
 */
class MockFileApi implements IApi
{

    /**
     * Path to the local xml file from which to read
     *
     * @var string
     */
    private string $file;


    /**
     * MockFileApi constructor.
     *
     * @param string $file
     */
    public function __construct(string $file)
    {
        $this->file = $file;
    }


    /**
     * Retrieves a XML document representing the result from the API call.
     *
     * @return string|null
     */
    public function retrieveDocument(): ?string
    {
        return file_get_contents($this->file);
    }
}
