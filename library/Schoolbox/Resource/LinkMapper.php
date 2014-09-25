<?php
/**
 * Mapper for links in the resource browser and homepages.
 *
 * @author    James Leckie <jleckie@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Resource;
use Schoolbox\Mapper;

class LinkMapper extends Mapper {

	protected function mapping() {
		$this->hasTable('link');
		$this->hasModel('\\Schoolbox\\Resource\\Link');
		$this->hasMap(array(
			'column'     => 'id',
			'property'   => 'id',
			'type'       => self::TYPE_INTEGER,
			'identifier' => true,
		));
        $this->hasMap(array(
            'column'     => 'category',
            'property'   => 'folder',
            'type'       => self::TYPE_INTEGER,
        ));
        $this->hasMap(array(
            'column'     => 'owner',
            'property'   => 'owner',
            'type'       => self::TYPE_MODEL,
            'join'       => 'user',
        ));
        $this->hasMap(array(
            'column'     => 'url',
            'property'   => 'url',
            'type'       => self::TYPE_STRING,
        ));
		$this->hasMap(array(
			'column'     => 'comment',
			'property'   => 'comment',
			'type'       => self::TYPE_STRING,
		));
		$this->hasMap(array(
			'column'     => 'description',
			'property'   => 'description',
			'type'       => self::TYPE_STRING,
		));
		$this->hasMap(array(
			'column'     => 'hidden',
			'property'   => 'hidden',
			'type'       => self::TYPE_STRING,
		));
		$this->hasMap(array(
			'column'            => 'submitted',
			'property'          => 'submitted',
			'type'              => self::TYPE_DATETIME,
			'created_timestamp' => true,
		));
		$this->hasMap(array(
			'column'            => 'modified',
			'property'          => 'modified',
			'type'              => self::TYPE_DATETIME,
			'updated_timestamp' => true,
		));
        $this->hasMap(array(
            'column'     => 'deleted_at',
            'property'   => 'deletedAt',
            'type'       => self::TYPE_DATETIME,
        ));
		$this->hasJoin('user', array(
             'mapper'    => '\\Schoolbox\\UserMapper',
             'this_key'  => 'owner',
             'other_key' => 'id',
		));

        $this->hasSoftDeleteDateField('deleted_at');
	}
}