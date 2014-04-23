<?php
/**
 * Mapper for forum threads.  Forum is structured with a thread attached to a folder,
 * with posts below the thread
 *
 * @author    James Leckie <jleckie@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Forum;
use Schoolbox\Mapper;

class ThreadMapper extends Mapper {

    protected function mapping() {
        $this->hasTable('forumThread');
        $this->hasModel('\Schoolbox\Forum\Thread');
        $this->hasMap(array(
                'column'     => 'threadID',
                'property'   => 'threadID',
                'type'       => self::TYPE_INTEGER,
                'identifier' => true
            ));

        $this->hasMap(array(
                'column'     => 'categoryID',
                'property'   => 'categoryID',
                'type'       => self::TYPE_INTEGER
            ));

        $this->hasMap(array(
                'column'     => 'userID',
                'property'   => 'userID',
                'type'       => self::TYPE_INTEGER
            ));

        $this->hasMap(array(
                'column'     => 'title',
                'property'   => 'title',
                'type'       => self::TYPE_STRING
            ));

        $this->hasMap(array(
                'column'     => 'date',
                'property'   => 'date',
                'type'       => self::TYPE_DATETIME,
                'created_timestamp' => true,
            ));


        $this->hasMap(array(
                'column'     => 'deleted',
                'property'   => 'deleted',
                'type'       => self::TYPE_STRING
            ));
        $this->hasSoftDeleteField('deleted', 'true','false');

        $this->hasMap(array(
                'column'     => 'locked',
                'property'   => 'locked',
                'type'       => self::TYPE_STRING
            ));

        $this->hasMap(array(
                'column'     => 'views',
                'property'   => 'views',
                'type'       => self::TYPE_INTEGER
            ));

        $this->hasMap(array(
                'column'     => 'last_post_id',
                'property'   => 'lastPost',
                'type'       => self::TYPE_INTEGER
            ));

        $this->hasMap(array(
                'column'     => 'last_post_date',
                'property'   => 'lastPostDate',
                'type'       => self::TYPE_DATETIME
            ));

        $this->hasMap(array(
                'column'     => 'last_post_user_id',
                'property'   => 'lastPostUser',
                'type'       => self::TYPE_INTEGER
            ));

    }

    public function findById($id) {

        $post_mapper = PostMapper::getInstance();

        $query=
            "SELECT
                {$this->getColumnString('thread')},
                {$post_mapper->getColumnString('firstPost')}
            FROM {$this->getTable()} AS thread
            JOIN forumPost firstPost ON thread.threadID = firstPost.threadID
			WHERE thread.threadID = ?
			ORDER BY firstPost.date ASC LIMIT 1 ";

        $statement = $this->database->prepare($query);
        $statement->execute(array($id));

        if (($row = $statement->fetch()) !== false) {
            $post = $this->hydrate($row, 'thread');
            $post->setFirstPost($post_mapper->hydrate($row,'firstPost'));
            $this->throwIfDeleted($post);
            return $post;
        }
        else {
            throw new Mapper\Exception('Instance of object not found');
        }

    }
}
