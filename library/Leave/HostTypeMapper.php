<?php
/**
 * HostTypeMapper
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
namespace Leave;

class HostTypeMapper extends \Schoolbox\Mapper {

	/**
	 * Mapping
	 * 
	 * @return Bool|void
	 */
	protected function mapping() {
		$this->hasTable('lu_leave_request_host_type');
        $this->hasModel(HostType::class);

		$this->hasMap(array(
			'column' => 'id',
			'property' => 'id',
			'type' => self::TYPE_INTEGER,
			'identifier' => true
		));

		$this->hasMap(array(
			'column' => 'name',
			'property' => 'name',
			'type' => self::TYPE_STRING,
		));
	}
}