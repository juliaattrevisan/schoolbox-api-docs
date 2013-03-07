<?php
/**
 * Class description here
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Resource\Test;
use Schoolbox\Resource\File;
use PHPUnit_Framework_TestCase;

class FileTest extends PHPUnit_Framework_TestCase {

	/**
	 * The file that's being tested.
	 *
	 * @var File
	 */
	protected $file;

	public function setUp() {
		unset($this->file);
		$this->file = new File();

		$config = array(
			array('storage_dir', '/tmp')
		);

		//
		// Mock that configuration!
		$mock_config = $this->getMock(get_class($this->file->getConfigLoader()));
		$mock_config
			->expects($this->any())
			->method('get')
			->will($this->returnValueMap($config));
		$this->file->setConfigLoader($mock_config);

		//
		// Mock the mimetype loader too.
		$mock_mimetype = $this->getMock(get_class($this->file->getMimetypeLoader()));
		$mock_mimetype
			->expects($this->any())
			->method('getMimetypeForFilename')
			->will($this->returnCallback(array($this, 'getMimetypeForFilename')));
		$mock_mimetype
			->expects($this->any())
			->method('getIconForFilename')
			->will($this->returnCallback(array($this, 'getIconForFilename')));
		$this->file->setMimetypeLoader($mock_mimetype);
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

	public function testSetHashFromFileLocation() {
		$this->file->setTempLocation(__FILE__);
		$this->assertEquals($this->file->getHash(), '');
		$this->file->loadHashFromTempLocation();
		$this->assertEquals($this->file->getHash(), sha1_file(__FILE__));
	}

	public function testGetFileLocation() {
		//
		// Consider abstracting the save method into a separate class.
		// $file->setSaveStrat(new ...);
		$this->assertEquals($this->file->getFileLocation(), false);
		$this->file->setId(5);
		$this->assertEquals($this->file->getFileLocation(), '/tmp/5.dat');
	}

	public function getLoadMimetypeFromFilenameData() {
		return array(
			array(null, false),
			array('blah.png', 'image/png'),
			array('blah.pdf', 'application/pdf'),
			array('somedumbname.stuff.htm', 'text/html'),
			array('dumfile.blah', false),
		);
	}

	/**
	 * @dataProvider getLoadMimetypeFromFilenameData
	 */
	public function testLoadMimetypeFromFilename($filename, $mimetype) {
		$this->file->setFilename($filename);
		$this->file->loadMimetypeFromFilename();
		$this->assertEquals($this->file->getMime(), $mimetype);
	}

	public function getIconFromFilenameData() {
		return array(
			array(null, false),
			array('blah.png', 'image_png.png'),
			array('blah.pdf', 'application_pdf.png'),
			array('somedumbname.stuff.htm', 'text_html.png'),
			array('dumfile.blah', false),
		);
	}

	/**
	 * @dataProvider getIconFromFilenameData
	 */
	public function testGetIconFromFilename($filename, $icon) {
		$this->file->setFilename($filename);
		$this->assertEquals($this->file->getIconFromFilename(), $icon);
	}
}
