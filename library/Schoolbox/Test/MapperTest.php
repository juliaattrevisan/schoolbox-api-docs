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
use Schoolbox\Test\TestModel;
use Schoolbox\Test\TestMapper;

class MapperTest extends TestCase {

	/**
	 * The mapper that's being tested.
	 *
	 * @var TestMapper
	 */
	protected $object;

	public function setUp() {
		parent::setUp();
		$this->object = new TestMapper();
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
}