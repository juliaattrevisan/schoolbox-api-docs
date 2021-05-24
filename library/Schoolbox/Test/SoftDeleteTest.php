<?php

/**
 * Test cases for mappers.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Test;

use Schoolbox\Mapper\Exception;

class SoftDeleteTest extends TestCase
{

    /**
     * The mapper that's being tested.
     *
     * @var SoftDeleteTestMapper
     */
    protected $object;

    public function setUp(): void
    {
        parent::setUp();
        $this->object = new SoftDeleteTestMapper();
        $this->object->setDatabase(new MockDatabase($this));
    }

    public function testSoftDeleteNotDeleted()
    {
        $this->object->getDatabase()->setNextFetch([
            'id'      => 1,
            'name'    => 'something',
            'deleted' => false,
        ]);
        $this->assertFalse($this->object->findById(1)->isDeleted());
    }

    public function testSoftDeleteDeletedResult()
    {
        $this->object->getDatabase()->setNextFetch([
            'id'      => 1,
            'name'    => 'something',
            'deleted' => true,
        ]);
        try {
            $this->object->findById(1);
            $this->fail('Exception\Deleted expected, but not thrown');
        } catch (Exception\Deleted $e) {
            $this->assertTrue($e->getDeletedObject()->isDeleted());
            $this->assertEquals('something', $e->getDeletedObject()->getName());
        }
    }
}
