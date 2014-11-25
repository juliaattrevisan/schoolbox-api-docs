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
        $this->hasModel('\\Schoolbox\\Forum\\Post');
        $this->hasMap(array(
                'column'     => 'postID',
                'property'   => 'id',
                'type'       => self::TYPE_INTEGER,
                'identifier' => true
            ));

        $this->hasMap(array(
                'column'     => 'threadID',
                'property'   => 'thread',
                'type'       => self::TYPE_MODEL,
                'join'       => 'thread'
            ));

        $this->hasMap(array(
                'column'     => 'userID',
                'property'   => 'owner',
                'type'       => self::TYPE_MODEL,
                'join'       => 'owner',
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
                'type'       => self::TYPE_STRING
            ));
        $this->hasSoftDeleteField('deleted', 'true','false');

        $this->hasMap(array(
                'column'     => 'modified',
                'property'   => 'modified',
                'type'       => self::TYPE_DATETIME,
                'updated_timestamp'=> true
            ));

        $this->hasMap(array(
                'column'     => 'censored',
                'property'   => 'censored',
                'type'       => self::TYPE_STRING
            ));

        $this->hasJoin('owner', array(
            'mapper'    => '\\Schoolbox\\UserMapper',
            'this_key'  => 'userID',
            'other_key' => 'id',
        ));

        $this->hasJoin('thread', array(
            'mapper'    => '\\Schoolbox\\Forum\\ThreadMapper',
            'this_key'  => 'threadID',
            'other_key' => 'threadID',
        ));

    }


    /**
     * @param int $id
     * @return Post
     * @throws \Schoolbox\Mapper\Exception
     */
    public function findById($id) {
        $query=
            "SELECT
                {$this->getColumnString('post')},
                {$this->getJoinColumnString('owner', 'owner')}
            FROM {$this->getTable()} AS post
            LEFT JOIN {$this->getJoinSql('owner', 'post', 'owner')}
			WHERE post.postID=? ";

        $statement = $this->database->prepare($query);
        $statement->execute(array($id));

        if (($row = $statement->fetch()) !== false) {
            $post = $this->hydrate($row, 'post');
            $post->setOwner($this->hydrateJoin($row, 'owner', 'owner'));
            $this->throwIfDeleted($post);
            return $post;
        }
        else {
            throw new Mapper\Exception('Instance of object not found');
        }

    }

}
