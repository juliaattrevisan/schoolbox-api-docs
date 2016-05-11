<?php
/**
 * Mapper for default course page permissions
 *
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Assessment\Course\Config;

use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Resource\Storage;
use Schoolbox\Collection;
use Schoolbox\Role;

class Mapper extends BaseMapper
{

    public function mapping()
    {
        $this->hasTable('course_builder_config');
        $this->hasModel(Model::class);

        $this->hasMap(
            array(
                'column'     => 'id',
                'property'   => 'id',
                'type'       => self::TYPE_INTEGER,
                'identifier' => true,
            )
        );
        $this->hasMap(
            array(
                'column'     => 'role_id',
                'property'   => 'roleId',
                'type'       => self::TYPE_INTEGER,
            )
        );
        $this->hasMap(
            array(
                'column'     => 'permission',
                'property'   => 'permission',
                'type'       => self::TYPE_INTEGER,
            )
        );
        $this->hasMap(
            array(
                'column'     => 'page',
                'property'   => 'page',
                'type'       => self::TYPE_STRING,
            )
        );
    }
}
