<?php
/**
 * Unit tests for Schoolbox\Assessment\Component\SubmissionBox class.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Assessment\Component\Test;
use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\Test\TestCase;

class SubmissionBoxTest extends TestCase {

	/**
	 * @var SubmissionBox
	 */
	protected $object;

	public function setUp() {
		parent::setUp();
		$this->object = new SubmissionBox();
	}

	//
	// Unit test what?
	// Setting of related objects, perhaps.
	// Don't bother testing any getters or setters, unless their value can be
	// affected by another method.

	public function testFileLinks() {
		$this->assertInstanceOf(
			'\\Schoolbox\\Assessment\\Component\\SubmissionBox\\FileCollection',
			$this->object->getFileLinks()
		);
		$this->assertCount(0, $this->object->getFileLinks());

		//
		// Test adding a file link - they now exist in multiples instead of only
		// a single one.
		$file = new SubmissionBox\File();
		$file->setFileId(11);
		$this->object->addFileLink($file);
		$this->assertCount(1, $this->object->getFileLinks());

		//
		// and getting it out again.
		foreach ($this->object->getFileLinks() as $file_link) {
			$this->assertInstanceOf(
				'\\Schoolbox\\Assessment\\Component\\SubmissionBox\\File',
				$file_link
			);
			$this->assertEquals(11, $file_link->getFileId());
			break;
		}
	}

	public function testRubric() {
		$this->markTestIncomplete('Rubric model hasn\'t been created yet');
	}

	public function testCategory() {
		$this->markTestIncomplete('Category/Homepage models haven\'t been created yet');
	}

	public function testWorkType() {
		$this->markTestIncomplete('Work type model hasn\'t been added yet');
	}

	public function testMarkType() {
		$this->markTestIncomplete('Mark type model hasn\'t been added yet');
	}

}
