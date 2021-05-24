<?php

/**
 * Component_Eportfolio_BasicListMapper
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Component\Eportfolio;

use Schoolbox;

class BasicListMapper extends Schoolbox\Mapper
{

    /**
     * Define column/property mapping
     *
     * @return Bool|void
     */
    protected function mapping()
    {
        $this->hasTable('eportfolio_component_basiclist_item');
        $this->hasModel(BasicList::class);

        $this->hasMap(array(
            'column'     => 'id',
            'property'   => 'id',
            'type'       => self::TYPE_INTEGER,
            'identifier' => true
        ));

        $this->hasMap(array(
            'column'     => 'component_instance_id',
            'property'   => 'componentInstanceId',
            'type'       => self::TYPE_INTEGER,
        ));

        $this->hasMap(array(
            'column'     => 'content',
            'property'   => 'content',
            'type'       => self::TYPE_STRING,
        ));

        $this->hasMap(array(
            'column'     => 'sequence',
            'property'   => 'sequence',
            'type'       => self::TYPE_INTEGER,
        ));
    }
}
