<?php
/**
 * Unit tests for Schoolbox\Assessment\Component\SubmissionBoxMapper class.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Assessment\Component\Test;
use Schoolbox\Assessment\Component\SubmissionBox;
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

	public function testHydrate() {
		$data = array(
			'id'                => 3,
			'category'          => 11,
			'name'              => 'Some test',
			'description'       => 'no description',
			'owner'             => 15,
			'accept_submission' => true,
			'multi_submission'  => false,
			'max_mark'          => 100,
			'rubric_result_set' => false,
		);
		$box = $this->object->hydrate($data);
		$this->assertInstanceOf('Schoolbox\\Assessment\\Component\\SubmissionBox', $box);
		$this->assertEquals(3,                $box->getId());
		$this->assertEquals(11,               $box->getCategory());
		$this->assertEquals('Some test',      $box->getName());
		$this->assertEquals('no description', $box->getDescription());
		$this->assertEquals(15,               $box->getOwner());
		$this->assertEquals(true,             $box->getAcceptOnline());
		$this->assertEquals(false,            $box->getAcceptMultiple());
		$this->assertEquals(100,              $box->getMaxMark());
		$this->assertEquals(false,            $box->getRubricResultSet());
	}

	public function testFindById() {
		$this->markTestIncomplete();
	}

	public function testSave() {
		$this->markTestIncomplete();
	}

	public function testDelete() {
		$this->markTestIncomplete();
	}

}
