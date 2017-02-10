<?php
/**
 * Test mapper.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
 
namespace Schoolbox\Test;

/**
 * Class SoftDeleteTestMapper
 *
 * @package Schoolbox\Test
 * @method MockDatabase getDatabase()
 */
class SoftDeleteTestMapper extends AbstractTestMapper {
	protected function mapping() {
		$this->hasModel(SoftDeleteTestModel::class);
		$this->hasMap(array(
			'column'     => 'id',
			'property'   => 'id',
			'type'       => self::TYPE_INTEGER,
			'identifier' => true,
		));
		$this->hasMap(array(
			'column'     => 'name',
			'property'   => 'name',
			'type'       => self::TYPE_STRING,
		));
		$this->hasMap(array(
			'column'     => 'deleted',
			'property'   => 'deleted',
			'type'       => self::TYPE_BOOLEAN,
		));
        $this->hasSoftDeleteBooleanField('deleted');
	}
}
