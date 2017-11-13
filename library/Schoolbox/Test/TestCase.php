<?php
/**
 * Base test case for Schoolbox classes.
 *
 * .
 *
 * @author      Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright   2013 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 */

namespace Schoolbox\Test;

use PHPUnit\Framework\TestCase as BaseTestCase;
use Schoolbox\Config\Loader;

abstract class TestCase extends BaseTestCase
{
    /**
     * The object that's being tested.
     *
     * @var object
     */
    protected $object;

    /**
     * Configuration variables to set in the mock config.
     *
     * @var array
     */
    protected $config_vars = [];

    /**
     * Mock object of ConfigLoader
     *
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    protected $mock_config;

    public function setUp()
    {
        unset($this->object);

        //
        // Set up a mock configuration.
        $mock_config = $this->createMock(Loader::class);
        $mock_config
            ->expects($this->any())
            ->method('get')
            ->will($this->returnValueMap($this->config_vars));
        $this->mock_config = $mock_config;
    }

}
