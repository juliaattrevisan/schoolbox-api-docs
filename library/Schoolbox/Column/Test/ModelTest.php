<?php
/**
 * Test for hydration/dehydration of models.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */


namespace Schoolbox\Column\Test;
use Schoolbox\Column\Model;
use Schoolbox\Test\TestMapper;
use Schoolbox\Test\TestModel;

class ModelTest extends TestCase {

	/**
	 * @var Model
	 */
	protected $object;

	/**
	 * @var TestModel
	 */
	protected $test_model;

	/**
	 * @var TestMapper
	 */
	protected $test_mapper;

	public function setupObject() {
		$this->object = new Model();
		$this->test_model = new TestModel();
		$this->test_model
			->setId(1)
			->setFilename('d.txt')
			->setIsViewed(false)
			->setFile('contents');
		$this->test_mapper = $this->getMock('\\Schoolbox\\Test\\TestMapper', array('findById'));
		$this->test_mapper
			->expects($this->any())
			->method('findById')
			->will($this->returnValue($this->test_model));
	}

	public function getTestHydrate() {
		return array();
	}

	public function getTestDehydrate() {
		return array();
	}

	public function testHydrate() {
		$hydrate = $this->object->hydrate(1, $this->test_mapper);
		$this->assertSame(1, $hydrate->getId());
		$this->assertFalse($hydrate->isModelLoaded());
		$this->assertSame('d.txt', $hydrate->getFilename());
		$this->assertTrue($hydrate->isModelLoaded());
	}

	public function testDehydrate() {
		$this->assertSame(1, $this->object->dehydrate($this->test_model, $this->test_mapper));
	}

	/**
	 * Test when dehydrating with an alternate field name.
	 */
	public function testDehydrateWithFieldName() {
		$this->object->setFieldName('filename');
		$this->assertSame('d.txt', $this->object->dehydrate($this->test_model, $this->test_mapper));
	}
}
