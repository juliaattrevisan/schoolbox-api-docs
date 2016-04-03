<?php
/**
 * Unit tests for Schoolbox\Resource\Folder\Mapper
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Resource\Test;
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Test\TestCase;

class CategoryMapperTest extends TestCase {

	/**
	 * @var FileMapper
	 */
	protected $object;

	protected $config_vars = array(
		array('file_dir',    __DIR__),
		array('storage_dir', '/tmp/storage'),
	);

	public function setUp() {
		//
		// Move to setupObject instead
		parent::setUp();
		$this->object = new FolderMapper();
	}

	public function testHydrate() {
		$this->markTestIncomplete();
	}
}
