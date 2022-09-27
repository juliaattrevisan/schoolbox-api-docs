<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Isams\Base\Endpoint;

/**
 * Class MockFragmentApi
 *
 * A version of Api that can be used in unit tests
 * Loads an isams xml from somewhere instead of their actual endpoint
 *
 * @package Schoolbox\External\Connector\Isams\Base\Endpoint
 */
class MockFragmentApi implements IApi
{

    /**
     * A chunk of xml from which to read
     *
     * @var string
     */
    private string $fragment;


    /**
     * MockFragmentApi constructor.
     *
     * @param string $fragment
     */
    public function __construct(string $fragment)
    {
        $this->fragment = $fragment;
    }


    /**
     * Retrieves a XML document representing the result from the API call.
     *
     * @return string|null
     */
    public function retrieveDocument(): ?string
    {
        return $this->fragment;
    }
}
