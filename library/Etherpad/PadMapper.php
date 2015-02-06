<?php
/**
 * PadMapper
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Etherpad;


class PadMapper extends \Schoolbox\Mapper {

	/**
	 * Define column/property mapping
	 *
	 * @return Bool|void
	 */
	protected function mapping() {
		$this->hasModel('\\Etherpad\\Pad');
		$this->hasTable('etherpad_pad');

		$this->hasMap(array(
			'column'     => 'id',
			'property'   => 'id',
			'type'       => self::TYPE_INTEGER,
			'identifier' => true
		));

		$this->hasMap(array(
			'column'     => 'pad_id',
			'property'   => 'padId',
			'type'       => self::TYPE_STRING,
		));

		$this->hasMap(array(
			'column'     => 'homepage_id',
			'property'   => 'homepageId',
			'type'       => self::TYPE_INTEGER,
		));

		$this->hasMap(array(
			'column'     => 'environment',
			'property'   => 'environment',
			'type'       => self::TYPE_STRING,
		));

		$this->hasMap(array(
			'column'     => 'name',
			'property'   => 'name',
			'type'       => self::TYPE_STRING,
		));

		$this->hasMap(array(
			'column'     => 'body',
			'property'   => 'body',
			'type'       => self::TYPE_STRING,
		));

		$this->hasMap(array(
			'column'     => 'lock',
			'property'   => 'lock',
			'type'       => self::TYPE_INTEGER,
		));

		$this->hasMap(array(
			'column'     => 'created_at',
			'property'   => 'createdAt',
			'type'       => self::TYPE_DATETIME,
		));

		$this->hasMap(array(
			'column'     => 'updated_at',
			'property'   => 'updatedAt',
			'type'       => self::TYPE_DATETIME,
		));

		$this->hasMap(array(
			'column'     => 'deleted_at',
			'property'   => 'deletedAt',
			'type'       => self::TYPE_DATETIME,
		));

		$this->hasSoftDeleteDateField('deleted_at');
	}


    /**
     * @param object $object
     * @param string $verb
     * @param null   $previous
     * @return bool
     */
    protected function raiseNotification($object, $verb, $previous = null)
    {
        // vicious lies
        return true;
    }

}