<?php
/**
 * MockApi
 *
 * A version of Api that can be used in unit tests
 * Loads an isams xml file from somewhere instead of their actual endpoint
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Alaress Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\External\Connector\Isams\Base;

use RuntimeException;


class MockApi extends Api
{

    /**
     * Retrieves a XML document representing the result from the API call.
     *
     * @param string $filter An XML document representing a filter
     * @return string
     * @throws RuntimeException if the retrieval of the XML doc failed
     */
    public function retrieveDocument(string $filter = ''): ?string
    {
        return file_get_contents(__DIR__ .'/../../../../../../www/test.xml');
    }
}
