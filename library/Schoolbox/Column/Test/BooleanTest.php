<?php
/**
 * Test for
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */


namespace Schoolbox\Column\Test;
use Schoolbox\Column\Boolean;

class BooleanTest extends TestCase {

	/**
	 * @var Boolean
	 */
	protected $object;

    /**
     * @inheritdoc
     */
    public function setupObject() {
		$this->object = new Boolean();
	}

    /**
     * @inheritdoc
     */
    public function getTestHydrate() {
		return array(
			array(true, true),
			array(false, false),
			array(0, false),
			array(1, true),
			array('0', false),
			array('00', false),
			array('0.0', false),
			array('0.01', true),
			array('-0.01', true),
			array(null, false),
			array('', false),
			array(' ', false),
		);
	}

    /**
     * @inheritdoc
     */
    public function getTestDehydrate() {
		return array(
			array(true, true),
			array(false, false),
			array(0, false),
			array(1, true),
			array('0', false),
			array('00', false),
			array('0.0', false),
			array('0.01', true),
			array('-0.01', true),
			array(null, false),
			array('', false),
			array(' ', false),
		);
	}
}
