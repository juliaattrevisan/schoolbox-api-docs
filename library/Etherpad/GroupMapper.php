<?php
/**
 * GroupMapper
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Etherpad;


class GroupMapper extends \Schoolbox\Mapper {

	/**
	 * Define column/property mapping
	 *
	 * @return Bool|void
	 */
	protected function mapping() {
		$this->hasModel('\\Etherpad\\Group');
		$this->hasTable('etherpad_groups');

		$this->hasMap(array(
			'column'     => 'id',
			'property'   => 'id',
			'type'       => self::TYPE_INTEGER,
			'identifier' => true
		));

		$this->hasMap(array(
			'column'     => 'group_id',
			'property'   => 'groupId',
			'type'       => self::TYPE_STRING,
		));

		$this->hasMap(array(
			'column'     => 'user_id',
			'property'   => 'userId',
			'type'       => self::TYPE_INTEGER,
		));

		$this->hasMap(array(
			'column'     => 'homepage_id',
			'property'   => 'homepageId',
			'type'       => self::TYPE_INTEGER,
		));
	}
}