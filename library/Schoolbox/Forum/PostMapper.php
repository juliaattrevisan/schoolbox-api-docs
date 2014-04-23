<?php
/**
 * Mapper for forum posts.  Forum is structured with a thread attached to a folder,
 * with posts below the thread.  The users post into a thread using this mapper.
 *
 * @author    James Leckie <jleckie@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Forum;
use Schoolbox\Mapper;

class PostMapper extends Mapper {

    protected function mapping() {
        $this->hasTable('forumPost');
        $this->hasModel('\Schoolbox\Forum\Post');
        $this->hasMap(array(
                'column'     => 'postID',
                'property'   => 'postID',
                'type'       => self::TYPE_INTEGER,
                'identifier' => true
            ));

        $this->hasMap(array(
                'column'     => 'threadID',
                'property'   => 'threadID',
                'type'       => self::TYPE_INTEGER
            ));

        $this->hasMap(array(
                'column'     => 'userID',
                'property'   => 'userID',
                'type'       => self::TYPE_INTEGER
            ));

        $this->hasMap(array(
                'column'     => 'date',
                'property'   => 'date',
                'type'       => self::TYPE_DATETIME,
                'created_timestamp' => true
            ));

        $this->hasMap(array(
                'column'     => 'body',
                'property'   => 'body',
                'type'       => self::TYPE_STRING
            ));

        $this->hasMap(array(
                'column'     => 'deleted',
                'property'   => 'deleted',
                'type'       => self::TYPE_BOOLEAN
            ));
        $this->hasSoftDeleteBooleanField('deleted');


        $this->hasMap(array(
                'column'     => 'modified',
                'property'   => 'modified',
                'type'       => self::TYPE_DATETIME,
                'updated_timestamp'=> true
            ));

        $this->hasMap(array(
                'column'     => 'censored',
                'property'   => 'censored',
                'type'       => self::TYPE_BOOLEAN
            ));

    }
}
