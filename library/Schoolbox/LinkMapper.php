<?php
/**
 * LinkMapper
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox;
use Schoolbox\Mapper;

class LinkMapper extends Mapper {

    /**
     * Mapping
     *
     * @return Bool|void
     */
    protected function mapping () {
        $this->hasTable('link');
        $this->hasModel('\\Schoolbox\\Link');

        $this->hasMap(array(
            'column'     => 'id',
            'property'   => 'id',
            'type'       => self::TYPE_INTEGER,
            'identifier' => true,
        ));

        $this->hasMap(array(
            'column'     => 'category',
            'property'   => 'categoryId',
            'type'       => self::TYPE_MODEL,
        ));

        $this->hasMap(array(
            'column'     => 'owner',
            'property'   => 'ownerId',
            'type'       => self::TYPE_MODEL,
        ));

        $this->hasMap(array(
            'column'     => 'url',
            'property'   => 'url',
            'type'       => self::TYPE_STRING,
        ));

        $this->hasMap(array(
            'column'     => 'submitted',
            'property'   => 'submittedAt',
            'type'       => self::TYPE_DATETIME,
        ));

        $this->hasMap(array(
            'column'     => 'modified',
            'property'   => 'modifiedAt',
            'type'       => self::TYPE_DATETIME,
        ));

        $this->hasMap(array(
            'column'     => 'description',
            'property'   => 'description',
            'type'       => self::TYPE_STRING,
        ));

        $this->hasMap(array(
            'column'     => 'comment',
            'property'   => 'comment',
            'type'       => self::TYPE_STRING,
        ));

        $this->hasMap(array(
            'column'     => 'hidden',
            'property'   => 'hidden',
            'type'       => self::TYPE_INTEGER,
        ));

        $this->hasMap(array(
            'column'     => 'deleted_at',
            'property'   => 'deletedAt',
            'type'       => self::TYPE_DATETIME,
        ));

        $this->hasSoftDeleteDateField('deleted_at');
    }
}