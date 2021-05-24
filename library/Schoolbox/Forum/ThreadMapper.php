<?php

/**
 * Mapper for forum threads.  Forum is structured with a thread attached to a folder,
 * with posts below the thread
 *
 * @author    James Leckie <jleckie@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Forum;

use Alaress\Async\Notification\Instance\Instance;
use PDO;
use Schoolbox\Collection;
use Schoolbox\Feed\FeedableMapperInterface;
use Schoolbox\Mapper;
use Schoolbox\User;

class ThreadMapper extends Mapper implements FeedableMapperInterface
{

    protected function mapping()
    {
        $this->hasTable('forumThread');
        $this->hasModel(Thread::class);
        $this->hasMap(array(
                'column'     => 'threadID',
                'property'   => 'id',
                'type'       => self::TYPE_INTEGER,
                'identifier' => true
            ));

        $this->hasMap(array(
                'column'     => 'categoryID',
                'property'   => 'category',
                'type'       => self::TYPE_MODEL,
                'join'       => 'category'
            ));

        $this->hasMap(array(
                'column'     => 'userID',
                'property'   => 'user',
                'type'       => self::TYPE_MODEL,
                'join'       => 'owner'
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
        $this->hasSoftDeleteField('deleted', 'true', 'false');

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
                'type'       => self::TYPE_MODEL,
                'join'       => 'post'
            ));

        $this->hasMap(array(
                'column'     => 'last_post_date',
                'property'   => 'lastPostDate',
                'type'       => self::TYPE_DATETIME
            ));

        $this->hasMap(array(
                'column'     => 'last_post_user_id',
                'property'   => 'lastPostUser',
                'type'       => self::TYPE_MODEL,
                'join'       => 'lastPoster'
            ));

        $this->hasJoin('owner', array(
            'mapper'    => '\Schoolbox\UserMapper',
            'this_key'  => 'userID',
            'other_key' => 'id'
        ));

        $this->hasJoin('lastPoster', array(
            'mapper'    => '\Schoolbox\UserMapper',
            'this_key'  => 'last_post_user_id',
            'other_key' => 'id'
        ));

        $this->hasJoin('category', array(
            'mapper'    => '\Schoolbox\Resource\Folder\Mapper',
            'this_key'  => 'categoryID',
            'other_key' => 'id'
        ));

        $this->hasJoin('post', array(
            'mapper'    => '\Schoolbox\Forum\PostMapper',
            'this_key'  => 'last_post_id',
            'other_key' => 'postID'
        ));

        $this->registerCallback(self::CALLBACK_POSTSOFTDELETE, array($this, 'postDelete'));
    }

    /**
     * On delete of this item, delete all associated posts
     *
     */
    public function postDelete(Thread $thread)
    {
        PostMapper::getInstance()->deleteByColumn("threadID", $thread->getId());
    }



    /**
     * @param int $id
     * @return Thread
     * @throws \Schoolbox\Mapper\Exception
     */
    public function findById($id)
    {

        $post_mapper = $this->getJoinMapper('post');

        $query =
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
            $thread = $this->hydrate($row, 'thread');
            $this->throwIfDeleted($thread);

            $thread->setFirstPost($post_mapper->hydrate($row, 'firstPost'));
            $thread->setPosts($post_mapper->findByColumn('threadID', $thread->getId()));

            return $thread;
        } else {
            throw new Mapper\Exception\NotFound();
        }
    }


    /**
     * Return the collection of Users who are involved in this thread
     *
     * @param Thread $thread
     * @return Collection|User[]
     */
    public function findInvolvedUsers(Thread $thread)
    {
        $stmt = $this->database->prepare("
            SELECT DISTINCT p.userID
            FROM forumThread t
            LEFT JOIN forumPost p ON t.threadID = p.threadID
            WHERE t.threadID = ?
        ");

        $stmt->execute(array($thread->getId()));

        $ids = array();

        while ($id = $stmt->fetchColumn()) {
            $ids[] = $id;
        }

        return $this->getJoinMapper('owner')->findByColumn('id', $ids);
    }


    /**
     * @param int $context
     * @param int $limit
     * @param int $offset
     * @param int $time
     * @return Collection|Thread[]
     */
    public function findForFeed($context, $limit, $offset = 0, $time = 0)
    {
        $time = date('Y-m-d H:i:s', $time ? : time());

        $limit = (int)$limit;
        $offset = (int)$offset;

        $stmt = $this->database->prepare("
            SELECT
                {$this->getColumnString('t')},
                {$this->getJoinColumnString('owner', 'o')}
            FROM `{$this->getTable()}` t
            INNER JOIN {$this->getJoinSql('owner', 't', 'o', true)}
            WHERE t.deleted = 'false'
            AND t.categoryID = :cat
            AND t.date < :time
            LIMIT $offset, $limit
        ");

        $stmt->execute(
            array(
                'cat'  => $context,
                'time' => $time
            )
        );

        $c = $this->createCollection();

        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
            /** @var Thread $thread */
            $thread = $this->hydrate($r, 't');
            $thread->setUser($this->hydrateJoin($r, 'owner', 'o'));

            $c->add($thread);
        }

        return $c;
    }
}
