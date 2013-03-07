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
	public $file;
	public function setUp() {
		unset($this->file);
		$this->file = new File();
		$this->file->setFileLocationMethod(function($id) {return '/tmp/'.$id.'.dat';});
	}

	public function testSetHashFromFileLocation() {
		$this->file->setTempLocation(__FILE__);
		$this->assertEquals($this->file->getHash(), '');
		$this->file->loadHashFromTempLocation();
		$this->assertEquals($this->file->getHash(), sha1_file(__FILE__));
	}

	public function testGetFileLocation() {
		$this->assertEquals($this->file->getFileLocation(), false);
		$this->file->setId(5);
		$this->assertEquals($this->file->getFileLocation(), '/tmp/5.dat');
	}

}
