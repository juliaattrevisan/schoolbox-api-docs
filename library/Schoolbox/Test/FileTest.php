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

namespace Schoolbox\Test;
use Schoolbox\File;
use Schoolbox\Test\TestCase;

class FileTest extends TestCase {

	/**
	 * The file that's being tested.
	 *
	 * @var File
	 */
	protected $object;

	protected $config_vars = array(
		array('storage_dir', '/tmp')
	);

	public function setUp() {
		parent::setUp();
		$this->object = new File();
		$this->object->setConfigLoader($this->mock_config);
	}

	public function getIconForFilename($filename) {
		$mimetype = $this->getMimetypeForFilename($filename);
		if (!$mimetype) {
			return false;
		}
		return str_replace('/', '_', $mimetype).'.png';
	}

	public function getMimetypeForFilename($filename) {
		$extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
		$mimetype  = false;
		switch ($extension) {
			case 'gif':
				$mimetype = 'image/gif';
				break;

			case 'jpg':
			case 'jpeg':
			case 'jpe':
				$mimetype = 'image/jpeg';
				break;

			case 'png':
				$mimetype = 'image/png';
				break;

			case 'pdf':
				$mimetype = 'application/pdf';
				break;

			case 'txt':
				$mimetype = 'text/plain';
				break;

			case 'htm':
			case 'html':
				$mimetype = 'text/html';
				break;

			default:
				break;
		}
		return $mimetype;
	}

	public function testLoadHashFromTempLocation() {
		$this->object->setTempLocation(__FILE__);
		$this->assertEquals($this->object->getHash(), '');
		$this->object->loadHashFromTempLocation();
		$this->assertEquals($this->object->getHash(), sha1_file(__FILE__));
	}

	public function testLoadFilesizeFromTempLocation() {
		$this->object->setTempLocation(__FILE__);
		$this->assertEquals($this->object->getFilesize(), false);
		$this->object->loadFilesizeFromTempLocation();
		$this->assertEquals($this->object->getFilesize(), filesize(__FILE__));
	}

	public function testGetFileLocation() {
		//
		// Consider abstracting the save method into a separate class.
		// $file->setSaveStrat(new ...);
		$this->assertEquals($this->object->getFileLocation(), false);
		$this->object->setId(5);
		$this->assertEquals($this->object->getFileLocation(), '/tmp/5.dat');
	}

}
