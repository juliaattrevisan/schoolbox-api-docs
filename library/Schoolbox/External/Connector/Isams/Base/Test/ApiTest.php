<?php
/**
 * ApiTest
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\External\Connector\Isams\Base\Test;

use PHPUnit\Framework\TestCase;
use Schoolbox\External\Connector\Isams\Base\MockApi;


class ApiTest extends TestCase
{

    /**
     * MockApi returned at least something
     */
    public function testRetrieveDocument(): void
    {
        $this->assertNotEmpty((new MockApi('', ''))->retrieveDocument());
    }
}


