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
