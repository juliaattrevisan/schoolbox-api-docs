<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class DueWork_ErrorMapper extends Schoolbox\Mapper {

	protected function mapping () {
		$this->hasTable('submission_errors');
		$this->hasModel('DueWork_Error');
		$this->hasMap(array(
			'column'     => 'id',
			'property'   => 'id',
			'type'       => self::TYPE_INTEGER,
			'identifier' => true
		));

		$this->hasMap(array(
			'column'     => 'code',
			'property'   => 'code',
			'type'       => self::TYPE_STRING,
		));

		$this->hasMap(array(
			'column'     => 'display_as',
			'property'   => 'displayAs',
			'type'       => self::TYPE_STRING,
		));

		$this->hasMap(array(
			'column'     => 'normalise_as',
			'property'   => 'normaliseAs',
			'type'       => self::TYPE_DECIMAL,
		));
	}

	/**
	 * Convenience method for finding by code
	 * @param $code
	 * @return \Schoolbox\Collection
	 */
	public function findByCode($code) {
		return parent::findByColumn('code', $code);
	}

	public function listDisplayValues() {
		$errs = $this->findAll();
		$disp = array();
		foreach($errs as $e) {
			$disp[] = $e->displayAs;
		}
		return $disp;
	}

}
