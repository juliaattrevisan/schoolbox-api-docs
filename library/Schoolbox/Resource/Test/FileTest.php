<?php
/**
 * Unit tests for Schoolbox\Resource\File class.
 *
 * Designed to not have any external dependencies on external databases.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Resource\Test;
use Schoolbox\Resource\File;
use Schoolbox\Test\TestCase;

class FileTest extends TestCase {

	/**
	 * The file that's being tested.
	 *
	 * @var File
	 */
	protected $object;

	protected $config_vars = array(
		array('file_dir', '/tmp')
	);

	public function setUp() {
		parent::setUp();
		$this->object = new File();
		$this->object->setConfigLoader($this->mock_config);
	}

	public function testGetFileLocation() {
		$this->object->setId(671);
		$this->assertEquals('/tmp/671.dat', $this->object->getFileLocation());
	}
}
