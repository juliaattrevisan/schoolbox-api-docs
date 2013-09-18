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
use Schoolbox\Resource\Exception;
use Schoolbox\Resource\File;
use Schoolbox\Resource\FileMapper;
use Schoolbox\Test\TestCase;
use Schoolbox\File as Storage;

class FileMapperTest extends TestCase {

	/**
	 * @var FileMapper
	 */
	protected $object;

	protected $config_vars = array(
		array('file_dir',    __DIR__),
		array('storage_dir', '/tmp/storage'),
	);

	public function setUp() {
		parent::setUp();
		$this->object = new FileMapper();
	}

	public function testHydrate() {
		$data = array(
			'id'       => 3,
			'realname' => 'stuff.png',
		);
		$file = $this->object->hydrate($data);
		$this->assertInstanceOf('Schoolbox\\Resource\\File', $file);
		$this->assertEquals(3, $file->getId());
		$this->assertEquals('stuff.png', $file->getFilename());
	}

	public function testCopyToStorage() {
		//
		// Storage items - mock their mimetype and config loaders.
		$new_storage = new Storage();
		$new_storage
			->setConfigLoader($this->mock_config)
		;
		$saved_storage = new Storage();
		$saved_storage
			->setConfigLoader($this->mock_config)
			->setFilename('dumb.txt')
			->setId(2)
		;

		//
		// Storage item mapper:
		// createModel(): returns a new (mocked) storage item.
		// findByColumn(): First call throws an exception, others return the
		// saved storage item.
		$storage_mapper = $this->getMock('\\Schoolbox\\FileMapper', array('createModel', 'findOneByColumn'));
		$storage_mapper
			->expects($this->any())
			->method('createModel')
			->will($this->returnCallback(function() use ($new_storage) {
				return clone $new_storage;
			}));
		$matcher = $this->any();
		$storage_mapper
			->expects($matcher)
			->method('findOneByColumn')
			->will($this->returnCallback(function() use($matcher, $saved_storage) {
				if ($matcher->getInvocationCount() === 1) {
					throw new Exception('Instance of object not found');
				}
				return $saved_storage;
			}));
		$this->object->setStorageMapper($storage_mapper);

		$file_to_copy = new File();
		$file_to_copy
			->setConfigLoader($this->mock_config)
			->setFilename('stupid.txt')
			->setId(1)
		;

		//
		// Test for file that doesn't exist.
		$new_file = $this->object->copyToStorage($file_to_copy);
		$this->assertEquals('stupid.txt', $new_file->getFilename());
		$this->assertEmpty($new_file->getId());

		//
		// Test for copying a file that does exist - it won't create a new one,
		// but will create existing instead.
		$new_file = $this->object->copyToStorage($file_to_copy);
		$this->assertEquals('dumb.txt', $new_file->getFilename());
		$this->assertEquals(2, $new_file->getId());
	}
}
