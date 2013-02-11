<?php
/**
 * Description of the file and what its purpose is
 *
 * @author      James Leckie <james.leckie@alaress.com.au>
 * @copyright   2013 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 */

namespace Schoolbox;
use Schoolbox;

class CampusMapper extends Mapper {

    protected function mapping () {

        $this->hasTable('luCampus');
        $this->hasModel('\Schoolbox\Campus');

        $this->hasMap(array(
                'column'     => 'id',
                'property'   => 'id',
                'type'       => Mapper::TYPE_INTEGER,
                'identifier' => true
            ));

        $this->hasMap(array(
                'column'     => 'name',
                'property'   => 'name',
                'type'       => Mapper::TYPE_STRING,
            ));
    }

}