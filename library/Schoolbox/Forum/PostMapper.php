<?php

/**
 * Mapper for forum posts.  Forum is structured with a thread attached to a folder,
 * with posts below the thread.  The users post into a thread using this mapper.
 *
 * @author    James Leckie <jleckie@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Forum;

use PDO;
use PDOStatement;
use Schoolbox\Collection;
use Schoolbox\Feed\FeedableMapperInterface;
use Schoolbox\GenericCollection;
use Schoolbox\Mapper;
use Schoolbox\User;

class PostMapper extends Mapper implements FeedableMapperInterface
{
    /** @var int */
    public const FORUM_POSTS_PER_PAGE = 15;

    protected function mapping()
    {
        $this->hasTable('forumPost');
        $this->hasModel(Post::class);
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
            'column'     => 'parentID',
            'property'   => 'parent',
            'type'       => self::TYPE_MODEL,
            'join'       => 'parent'
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
        $this->hasSoftDeleteField('deleted', 'true', 'false');

        $this->hasMap(array(
                'column'     => 'modified',
                'property'   => 'modified',
                'type'       => self::TYPE_DATETIME,
                'updated_timestamp' => true
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

        $this->hasJoin('parent', array(
            'mapper'    => '\\Schoolbox\\Forum\\PostMapper',
            'this_key'  => 'parentID',
            'other_key' => 'postID',
        ));

        $this->hasJoin('thread', array(
            'mapper'    => '\\Schoolbox\\Forum\\ThreadMapper',
            'this_key'  => 'threadID',
            'other_key' => 'threadID',
        ));

        $this->registerPersistSearchIndexCallbacks();
    }


    /**
     * @param int $id
     * @return Post
     * @throws \Schoolbox\Mapper\Exception
     */
    public function findById($id)
    {
        $query =
            "SELECT
                {$this->getColumnString('post')},
                {$this->getJoinColumnString('owner', 'owner')}
            FROM {$this->getTable()} AS post
            LEFT JOIN {$this->getJoinSql('owner', 'post', 'owner', true)}
            WHERE post.postID=? ";

        $statement = $this->database->prepare($query);
        $statement->execute(array($id));

        if (($row = $statement->fetch()) !== false) {
            $post = $this->hydrate($row, 'post');
            $post->setOwner($this->hydrateJoin($row, 'owner', 'owner'));
            $this->throwIfDeleted($post);
            return $post;
        } else {
            throw new Mapper\Exception\NotFound();
        }
    }


    /**
     * @param int $context
     * @param int $limit
     * @param int $offset
     * @param int $time
     * @return Collection|Post[]
     * @throws Mapper\DefinitionException
     * @throws Mapper\Exception
     * @throws \PDOException
     * @throws \UnexpectedValueException
     */
    public function findForFeed($context, $limit, $offset = 0, $time = 0)
    {
        $time = \Alaress_Date_Format::newFromTimestamp($time ? : time())->getDatabase();

        $limit = (int)$limit;
        $offset = (int)$offset;

        $stmt = $this->database->prepare("
            SELECT
                {$this->getColumnString('p')},
                {$this->getJoinColumnString('owner', 'o')},
                {$this->getJoinColumnString('owner.pronoun', 'op')},
                {$this->getJoinColumnString('thread', 't')}
            FROM `{$this->getTable()}` p
              INNER JOIN {$this->getJoinSql('owner', 'p', 'o', true)}
              LEFT JOIN {$this->getJoinSql('owner.pronoun', 'o', 'op')}
              INNER JOIN {$this->getJoinSql('thread', 'p', 't', true)}
            WHERE p.deleted = 'false'
            AND t.categoryID = :cat
            AND p.date < :time
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
            /** @var Post $post */
            $post = $this->hydrate($r, 'p');
            /** @var User $owner */
            $owner = $this->hydrateJoin($r, 'owner', 'o');
            $owner->setPronoun($this->hydrateJoin($r, 'owner.pronoun', 'op'));
            $post->setOwner($owner);
            $post->setThread($this->hydrateJoin($r, 'thread', 't'));

            $c->add($post);
        }

        return $c;
    }

    /**
     * @param int $threadID
     * @return object|null
     * @throws Mapper\DefinitionException
     * @throws Mapper\Exception
     * @throws Mapper\Exception\Deleted
     * @throws Mapper\Exception\NotFound
     * @throws \PDOException
     */
    public function findFirstPost($threadID)
    {

        $query =
            "SELECT
                {$this->getColumnString('post')},
                {$this->getJoinColumnString('owner', 'owner')}
            FROM {$this->getTable()} AS post
            LEFT JOIN {$this->getJoinSql('owner', 'post', 'owner', true)}
            WHERE post.parentID IS NULL AND post.threadID=?
            LIMIT 1";

        $statement = $this->database->prepare($query);
        $statement->execute(array($threadID));

        if (($row = $statement->fetch()) !== false) {
            $post = $this->hydrate($row, 'post');
            $post->setOwner($this->hydrateJoin($row, 'owner', 'owner'));
            $this->throwIfDeleted($post);
            return $post;
        } else {
            throw new Mapper\Exception\NotFound();
        }
    }

    /**
     * @inheritdoc
     * @throws \Schoolbox\Mapper\DefinitionException
     */
    protected function getSelectString(): string
    {
        return "
            SELECT {$this->getColumnString()},
                   {$this->getJoinColumnString('thread', 'thread')},
                   {$this->getJoinColumnString('owner', 'owner')},
                   {$this->getJoinColumnString('owner.pronoun', 'op')},
                   {$this->getJoinColumnString('parent', 'parent')}
        ";
    }


    /**
     * @inheritdoc
     */
    protected function getFromString(): string
    {
        return "
            FROM       {$this->getTable()}
            INNER JOIN {$this->getJoinSql('thread', '', 'thread', false)}
            INNER JOIN {$this->getJoinSql('owner', '', 'owner', true)}
            LEFT JOIN {$this->getJoinSql('owner.pronoun', 'owner', 'op')}
            LEFT JOIN {$this->getJoinSql('parent', '', 'parent', false)}
        ";
    }


    /**
     * @param PDOStatement $statement
     * @return GenericCollection
     * @throws \Schoolbox\Mapper\DefinitionException
     * @throws \Schoolbox\Mapper\Exception
     */
    public function hydrateSelect(PDOStatement $statement): GenericCollection
    {
        $collection = $this->createCollection();

        /** @noinspection PhpAssignmentInConditionInspection */
        while ($row = $statement->fetch()) {
            /** @var User $owner */
            $owner = $this->hydrateJoin($row, 'owner', 'owner');
            $owner->setPronoun($this->hydrateJoin($row, 'owner.pronoun', 'op'));
            $post = $this->hydrate($row)
                ->setOwner($owner)
                ->setThread($this->hydrateJoin($row, 'thread', 'thread'));
            /**
             * check if the post has a parent, if so, hydrate
             */
            if (null !== $row['parentID']) {
                $post->setParent($this->hydrateJoin($row, 'parent', 'parent'));
            }
            $collection[$post->getId()] = $post;
        }

        return $collection;
    }

    /**
     * @param Post $post
     * @return false|float
     */
    public function getPageNumber(Post $post)
    {
        $stmt = $this->database->prepare("SELECT count(*) as postCount FROM forumPost
        WHERE deleted = 'false'
            AND threadID = :threadId
            AND postID <= :postId");
        $stmt->execute([
                'threadId' => $post->getThread()->getId(),
                'postId' => $post->getId(),
                ]);

        return ceil($stmt->fetch()['postCount'] / self::FORUM_POSTS_PER_PAGE);
    }
}
