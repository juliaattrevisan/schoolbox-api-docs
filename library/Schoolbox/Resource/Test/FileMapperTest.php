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

namespace Schoolbox\Resource\Test;
use Schoolbox\Resource\FileMapper;
use PHPUnit_Framework_TestCase;

class FileMapperTest extends PHPUnit_Framework_TestCase {

	/**
	 * The file mapper that's being tested.
	 *
	 * @var FileMapper
	 */
	protected $file_mapper;

	public function setUp() {
		unset($this->file_mapper);
		$this->file_mapper = new FileMapper();
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
		$file = $this->file_mapper->hydrate($data);
		$this->assertInstanceOf('Schoolbox\\Resource\\File', $file);
		$this->assertEquals(4, $file->getId());
		$this->assertEquals('stuff.png', $file->getFilename());
		$this->assertEquals(false, $file->getTempLocation());
	}
}
