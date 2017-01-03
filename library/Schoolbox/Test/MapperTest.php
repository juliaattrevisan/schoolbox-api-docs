<?php
/**
 * Test cases for mappers.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Test;

class MapperTest extends TestCase {

	/**
	 * The mapper that's being tested.
	 *
	 * @var TestMapper
	 */
	protected $object;

	/** @var MockDatabase */
	protected $db;

	public function setUp() {
		parent::setUp();
		$this->object = new TestMapper($this);
		$this->db = new MockDatabase($this);
        $this->object->setDatabase($this->db);
	}

	/**
	 * Data provider for testHydrate().
	 *
	 * @return array
	 */
	public function getHydrateData() {
		return array(
			//
			// Basic hydrate
			array(
				array(
					'id'        => 5,
					'filename'  => 'porn.txt',
					'is_viewed' => true,
					'file'      => 'Awesome porn inside',
				),
				array(
					'storage_id' => 5,
					'filename'   => 'porn.txt',
					'is_viewed'  => true,
					'file'       => 'Awesome porn inside',
				),
			),
			//
			// Hydrate with alias
			array(
				array(
					'id'        => 5,
					'filename'  => 'porn.txt',
					'is_viewed' => false,
					'file'      => null,
				),
				array(
					'i.storage_id' => 5,
					'i.filename'   => 'porn.txt',
					'i.is_viewed'  => false,
					'file'         => 'Awesome porn inside',
				),
				'i'
			),
			//
			// Hydrate with callback (closure)
			array(
				array(
					'id'        => 5,
					'filename'  => 'PORN.TXT',
					'is_viewed' => true,
					'file'      => 'AWESOME',
				),
				array(
					'storage_id' => 5,
					'filename'   => 'porn.txt',
					'is_viewed'  => true,
					'file'       => 'Awesome',
				),
				null,
				function($mapper, $model, $row) {
					$model
						->setFilename(strtoupper($model->getFilename()))
						->setFile(strtoupper($model->getFile()))
					;
					return $model;
				}
			),
			//
			// Hydrate with callback (function reference)
			array(
				array(
					'id'        => 999,
					'filename'  => 'porn.txt',
					'is_viewed' => true,
					'file'      => 'Awesome',
				),
				array(
					'storage_id' => 2,
					'filename'   => 'porn.txt',
					'is_viewed'  => true,
					'file'       => 'Awesome',
				),
				null,
				array($this, 'callbackForTest'),
			),
			//
			// Hydrate with invalid callback - nothing changes
			array(
				array(
					'id'        => 2,
					'filename'  => 'porn.txt',
					'is_viewed' => true,
					'file'      => 'Awesome',
				),
				array(
					'storage_id' => 2,
					'filename'   => 'porn.txt',
					'is_viewed'  => true,
					'file'       => 'Awesome',
				),
				null,
				'99999999999',
			),
			//
			// Hydrate with missing fields - they get set to null, but should
			// probably be left as default.
			array(
				array(
					'id'        => 2,
					'filename'  => 'porn.txt',
					'is_viewed' => null,
					'file'      => null,
				),
				array(
					'storage_id' => 2,
					'filename'   => 'porn.txt',
				),
			),
		);
	}

	/**
	 * Test callback used by testHydrate().
	 *
	 * @param $mapper
	 * @param $model
	 * @param $row
	 *
	 * @return mixed
	 */
	public function callbackForTest($mapper, $model, $row) {
		$model->setId(999);
		return $model;
	}

	/**
	 * Tests for data hydration.
	 *
	 * @dataProvider getHydrateData
	 */
	public function testHydrate($expected, $data, $alias = null, $callback = null) {
		$model = $this->object->hydrate($data, $alias, $callback);
		$this->assertInstanceOf('Schoolbox\\Test\\TestModel', $model);
		$this->assertEquals($expected['id'],        $model->getId());
		$this->assertEquals($expected['filename'],  $model->getFilename());
		$this->assertEquals($expected['is_viewed'], $model->getIsViewed());
		$this->assertEquals($expected['file'],      $model->getFile());
	}

	/**
	 * Test for whether getting a join (that has been defined as a class name
	 * instead of as a class instance) works.
	 */
	public function testGetJoin() {
		$this->assertInstanceOf('Schoolbox\\Test\\TestMapper', $this->object->getJoinMapper('parent'));
	}


    /**
     * If an object is retrieved more than once from DB, its result should be cached.
     * The glaring exception to this is when you're saving and retrieving the previous version of the object.
     *
     * @dataProvider getHydrateData
     * @param array    $expected
     * @param array    $data
     * @param string   $alias
     * @param callable $callback
     */
	public function testObjectIdempotence($expected, $data, $alias = null, $callback = null)
    {
        if (!empty($alias)) {
            $this->markTestSkipped("Cannot test idempotence with an alias expected");
        }

        $id = isset($data['storage_id']) ? $data['storage_id'] : ( isset($data['i.storage_id']) ? $data['i.storage_id'] : null );

        $models = [];

        for ($i = 0; $i < 2; ++$i) {
            $this->db->setNextFetch($data);
            $models[] = $this->object->findById($id);
        }

        // assert that the two objects point to the same memory space
        $this->assertSame($models[0], $models[1]);

        // reset the statement, because the save() method will do a fetch to retrieve "previous"
        $this->db->setNextFetch($data);

        // "persist" our first model
        $ids = $this->object->save($models[0]);

        // ensure (strictly) that we did not receive FALSE back from the save operation
        $this->assertNotSame(false, $ids);
    }


    /**
     * Called from the Mapper::postSave() callback
     *
     * @param TestModel $model
     * @param TestModel $previous
     */
    public function internalTestNotSame(TestModel $model, TestModel $previous = null)
    {
        // we don't care about the actual content of these two objects, but if
        // these two variables point to the same memory address, this test must fail
        $this->assertNotSame($model, $previous);
    }

}
