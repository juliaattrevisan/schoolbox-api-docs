<?php
/**
 * Unit tests for Schoolbox\Resource\FileMapper.
 *
 * I'm not going to test any loading methods, at least not without a database
 * connection or some strategy for mocking one.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Test;
use Schoolbox\StorageMapper;
use Schoolbox\Test\TestCase;

class FileMapperTest extends TestCase {

	/**
	 * @var StorageMapper
	 */
	protected $object;

	public function setUp() {
		parent::setUp();
		$this->object = new StorageMapper();
	}

	public function testHydrate() {
		$data = array(
			//
			// storage_id, and not id, is the name of the column.
			'id'            => 3,
			'storage_id'    => 4,
			'filename'      => 'stuff.png',
			'temp_location' => '/tmp/pngsomewhere'
		);
		$file = $this->object->hydrate($data);
		$this->assertInstanceOf('Schoolbox\\Storage', $file);
		$this->assertEquals(4, $file->getId());
		$this->assertEquals('stuff.png', $file->getFilename());
		$this->assertEquals(false, $file->getTempLocation());
	}
}
