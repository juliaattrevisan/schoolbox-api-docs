<?php
/**
 * Test cases for mappers.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Test;
use Schoolbox\Test\TestCase;
use Schoolbox\Test\SoftDeleteTestMapper;
use Schoolbox\Mapper\Exception;

class SoftDeleteTest extends TestCase {

    /**
     * The mapper that's being tested.
     *
     * @var SoftDeleteTestMapper
     */
    protected $object;

    public function setUp() {
        parent::setUp();
        $this->object = new SoftDeleteTestMapper();
        $this->object->setDatabase(new MockDatabase($this));
    }

    public function testSoftDeleteNotDeleted() {
        $this->object->getDatabase()->setNextFetchAll(array(array(
            'id'      => 1,
            'name'    => 'something',
            'deleted' => false,
        )));
        $this->assertFalse($this->object->findById(1)->isDeleted());
    }

    public function testSoftDeleteDeletedResult() {
        $this->object->getDatabase()->setNextFetchAll(array(array(
            'id'      => 1,
            'name'    => 'something',
            'deleted' => true,
        )));
        try {
            $this->object->findById(1);
            $this->fail('Exception\Deleted expected, but not thrown');
        }
        catch (Exception\Deleted $e) {
            $this->assertTrue($e->getDeletedObject()->isDeleted());
            $this->assertEquals('something', $e->getDeletedObject()->getName());
        }
    }
}