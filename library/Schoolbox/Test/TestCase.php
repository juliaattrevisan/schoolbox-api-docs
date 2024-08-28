<?php

/**
 * Base test case for Schoolbox classes.
 *
 * .
 *
 * @author      Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Test;

use PHPUnit\Framework\TestCase as BaseTestCase;
use Schoolbox\Config\Loader;
use PHPUnit\Framework\MockObject\MockObject;

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
     * @var Loader|MockObject
     */
    protected $mock_config;

    public function setUp(): void
    {
        unset($this->object);

        //
        // Set up a mock configuration.
        $mock_config = $this->createMock(Loader::class);
        $mock_config
            ->expects($this->any())
            ->method('get')
            ->willReturnMap($this->config_vars);
        $this->mock_config = $mock_config;
    }
}
