<?php
/**
 * Mapper for user access.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright 2013 Alaress Pty Ltd
 * @link      http://www.alaress.com.au
 */

namespace Schoolbox;
use Schoolbox;

class UserAccessMapper extends Mapper {

	protected function mapping() {
		$this->hasTable('userAccess');
		$this->hasModel('\\Schoolbox\\UserAccess');
		$this->hasMap(array(
			'column'     => 'uid',
			'property'   => 'user',
			'type'       => Mapper::TYPE_MODEL,
			'join'       => 'user',
		));
		$this->hasMap(array(
			'column'     => 'service',
			'property'   => 'service',
			'type'       => Mapper::TYPE_INTEGER,
		));
		$this->hasMap(array(
			'column'     => 'sid',
			'property'   => 'itemId',
			'type'       => Mapper::TYPE_INTEGER,
		));
		$this->hasMap(array(
			'column'     => 'type',
			'property'   => 'accessLevel',
			'type'       => Mapper::TYPE_INTEGER,
		));
		$this->hasJoin('user', array(
			'mapper'    => '\\Schoolbox\\UserMapper',
			'this_key'  => 'uid',
			'other_key' => 'id',
		));
	}

}
