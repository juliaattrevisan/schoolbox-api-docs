<?php
/**
 * MockApi
 *
 * A version of Api that can be used in unit tests
 * Loads an isams xml file from somewhere instead of their actual endpoint
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\External\Connector\Isams\Base\Importer;

class MockApi implements IApi
{

    /**
     * Path to the local xml file from which to read
     *
     * @var string
     */
    private $file;


    /**
     * MockApi constructor.
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
     * @param string $filter An XML document representing a filter
     * @return string|null
     */
    public function retrieveDocument(string $filter = ''): ?string
    {
        return file_get_contents($this->file);
    }
}
