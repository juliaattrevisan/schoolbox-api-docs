<?php
/**
 * Used for mappings group types to categories
 *
 * @author    James Leckie <james.leckie@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */


namespace Schoolbox\Resource;
use Schoolbox\Mapper;


class GroupMapper extends Mapper {

    protected function mapping() {
        $this->hasModel('\\Schoolbox\\Resource\\Group');
        $this->hasTable('userGroups');
        $this->hasMap(array(
                'column'     => 'cid',
                'property'   => 'category',
                'type'       => self::TYPE_INTEGER,
            ));
        $this->hasMap(array(
                'column'     => 'type',
                'property'   => 'type',
                'type'       => self::TYPE_INTEGER,
            ));
    }
}