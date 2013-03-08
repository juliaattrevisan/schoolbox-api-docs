<?php
/**
 * Used to in the processing of the video queue
 *
 * @author      James Leckie <james.leckie@alaress.com.au>
 * @copyright   2013 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 */

namespace Schoolbox;
use Schoolbox;

class QueueMapper extends Mapper {

    protected function mapping () {

        $this->hasTable('groups');
        $this->hasModel('\Schoolbox\Role');

        $this->hasMap(array(
                'column'     => 'id',
                'property'   => 'id',
                'type'       => Mapper::TYPE_INTEGER,
                'identifier' => true
        ));

        $this->hasMap(array(
                'column'     => 'created_on',
                'property'   => 'createdOn',
                'type'       => Mapper::TYPE_STRING,
        ));

        $this->hasMap(array(
                'column'     => 'processed_on',
                'property'   => 'processedOn',
                'type'       => Mapper::TYPE_STRING,
        ));

        $this->hasMap(array(
                'column'     => 'file_id',
                'property'   => 'fileId',
                'type'       => Mapper::TYPE_INTEGER,
        ));

        $this->hasMap(array(
                'column'     => 'status',
                'property'   => 'status',
                'type'       => Mapper::TYPE_STRING,
        ));


    }



}