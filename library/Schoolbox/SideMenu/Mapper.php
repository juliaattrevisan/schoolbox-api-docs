<?php
/**
 * Mapper for side menu items in Schoolbox.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\SideMenu;

use Schoolbox\Mapper as BaseMapper;

class Mapper extends BaseMapper
{
    public function mapping()
    {
        $this->hasTable('sideMenu');
        $this->hasModel('\\Schoolbox\\SideMenu\\Model');
        $this->hasMap(
            array(
                'column'     => 'id',
                'property'   => 'id',
                'type'       => self::TYPE_INTEGER,
                'identifier' => true
            )
        );
        $this->hasMap(
            array(
                'column'   => 'parent',
                'property' => 'parent',
                'type'     => self::TYPE_MODEL,
                'join'     => 'parent',
                'join_def' => array(
                    'other_key' => 'id',
                    'mapper'    => '\\Schoolbox\\SideMenu\\Mapper',
                ),
            )
        );
        $this->hasMap(
            array(
                'column'   => 'txt',
                'property' => 'text',
                'type'     => self::TYPE_STRING,
            )
        );
        $this->hasMap(
            array(
                'column'   => 'url',
                'property' => 'url',
                'type'     => self::TYPE_STRING
            )
        );
        $this->hasMap(
            array(
                'column'   => 'target',
                'property' => 'target',
                'type'     => self::TYPE_STRING
            )
        );
        $this->hasMap(
            array(
                'column'   => 'sequence',
                'property' => 'order',
                'type'     => self::TYPE_INTEGER
            )
        );
    }
}
