<?php
/**
 * Mapper for top menu items in Schoolbox.
 *
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\TopMenu;

use Schoolbox\Mapper as BaseMapper;
use Schoolbox\User;

class Mapper extends BaseMapper
{
    public function mapping()
    {
        $this->hasTable('topMenu');
        $this->hasModel('\\Schoolbox\\TopMenu\\Model');
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
                'column'   => 'txt',
                'property' => 'text',
                'type'     => self::TYPE_STRING,
            )
        );
        $this->hasMap(
            array(
                'column'   => 'icon',
                'property' => 'icon',
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
