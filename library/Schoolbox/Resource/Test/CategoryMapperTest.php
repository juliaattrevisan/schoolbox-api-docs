<?php

/**
 * Unit tests for Schoolbox\Resource\Folder\Mapper
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Resource\Test;

use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Test\TestCase;

class CategoryMapperTest extends TestCase
{

    /**
     * @var FileMapper
     */
    protected $object;

    protected $config_vars = [
        ['storage_dir', '/tmp/storage'],
    ];

    public function setUp(): void
    {
        //
        // Move to setupObject instead
        parent::setUp();
        $this->object = new FolderMapper();
    }

    public function testHydrate()
    {
        $this->markTestIncomplete();
    }
}
