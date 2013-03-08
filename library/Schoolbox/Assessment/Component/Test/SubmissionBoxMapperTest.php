<?php
/**
 * Unit tests for Schoolbox\Assessment\Component\SubmissionBoxMapper class.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Assessment\Component\Test;
use Schoolbox\Assessment\Component\SubmissionBoxMapper;
use Schoolbox\Test\TestCase;

class SubmissionBoxMapperTest extends TestCase {

	/**
	 * @var SubmissionBoxMapper
	 */
	protected $object;

	public function setUp() {
		parent::setUp();
		$this->object = new SubmissionBoxMapper();
	}

	//
	// Unit test what?
	// Hydration
	// Don't test save, but potentially test pre-save callbacks.
	public function testHydrate() {
		$this->markTestIncomplete();
	}
}
