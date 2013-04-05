<?php
/**
 * Unit tests for Schoolbox\Resource\CategoryMapper.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Resource\Test;
use Schoolbox\Resource\Category;
use Schoolbox\Resource\CategoryMapper;
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
		$this->object = new CategoryMapper();
	}

	public function testHydrate() {
		$this->markTestIncomplete();
	}
}
