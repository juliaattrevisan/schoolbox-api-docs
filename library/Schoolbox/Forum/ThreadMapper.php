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

use PDO;
use PDOStatement;
use Schoolbox\Collection;
use Schoolbox\Feed\FeedableMapperInterface;
use Schoolbox\GenericCollection;
use Schoolbox\IHomepageCleanable;
use Schoolbox\Mapper;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\User;
use Schoolbox\UserMapper;

class ThreadMapper extends Mapper implements FeedableMapperInterface, IHomepageCleanable
{
    const FORUM_THREADS_PER_PAGE = 30;

    protected function mapping()
    {
        $this->hasTable('forumThread');
        $this->hasModel(Thread::class);
        $this->hasMap([
            'column'     => 'threadID',
            'property'   => 'id',
            'type'       => self::TYPE_INTEGER,
            'identifier' => true,
        ]);

        $this->hasMap([
            'column'   => 'categoryID',
            'property' => 'category',
            'type'     => self::TYPE_MODEL,
            'join'     => 'category',
        ]);

        $this->hasMap([
            'column'   => 'userID',
            'property' => 'user',
            'type'     => self::TYPE_MODEL,
            'join'     => 'owner',
        ]);

        $this->hasMap([
            'column'   => 'title',
            'property' => 'title',
            'type'     => self::TYPE_STRING,
        ]);

        $this->hasMap([
            'column'            => 'date',
            'property'          => 'date',
            'type'              => self::TYPE_DATETIME,
            'created_timestamp' => true,
        ]);


//        $this->hasMap([
//            'column'   => 'deleted',
//            'property' => 'deleted',
//            'type'     => self::TYPE_STRING,
//        ]);
//        $this->hasSoftDeleteField('deleted', 'true', 'false');
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);

        $this->hasMap([
            'column'   => 'locked',
            'property' => 'locked',
            'type'     => self::TYPE_BOOL_ENUM,
        ]);

        $this->hasMap([
            'column'   => 'views',
            'property' => 'views',
            'type'     => self::TYPE_INTEGER,
        ]);

        $this->hasMap([
            'column'   => 'last_post_id',
            'property' => 'lastPost',
            'type'     => self::TYPE_MODEL,
            'join'     => 'post',
        ]);

        $this->hasMap([
            'column'   => 'last_post_date',
            'property' => 'lastPostDate',
            'type'     => self::TYPE_DATETIME,
        ]);

        $this->hasMap([
            'column'   => 'last_post_user_id',
            'property' => 'lastPostUser',
            'type'     => self::TYPE_MODEL,
            'join'     => 'lastPoster',
        ]);

        $this->hasJoin('owner', [
            'mapper'    => UserMapper::class,
            'this_key'  => 'userID',
            'other_key' => 'id',
        ]);

        $this->hasJoin('lastPoster', [
            'mapper'    => UserMapper::class,
            'this_key'  => 'last_post_user_id',
            'other_key' => 'id',
        ]);

        $this->hasJoin('category', [
            'mapper'    => FolderMapper::class,
            'this_key'  => 'categoryID',
            'other_key' => 'id',
        ]);

        $this->hasJoin('post', [
            'mapper'    => PostMapper::class,
            'this_key'  => 'last_post_id',
            'other_key' => 'postID',
        ]);

        $this->registerCallback(self::CALLBACK_POSTSOFTDELETE, [$this, 'postDelete']);
        $this->registerPersistSearchIndexCallbacks();
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
        $statement->execute([$id]);

        if (($row = $statement->fetch()) !== false) {
            $thread = $this->hydrate($row, 'thread');
            $this->throwIfDeleted($thread);

            $thread->setFirstPost($post_mapper->hydrate($row, 'firstPost'));

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

        $stmt->execute([$thread->getId()]);

        $ids = [];

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
        $time = date('Y-m-d H:i:s', $time ?: time());

        $limit  = (int)$limit;
        $offset = (int)$offset;

        $stmt = $this->database->prepare("
            SELECT
                {$this->getColumnString('t')},
                {$this->getJoinColumnString('owner', 'o')},
                {$this->getJoinColumnString('owner.pronoun', 'op')}
            FROM `{$this->getTable()}` t
            INNER JOIN {$this->getJoinSql('owner', 't', 'o', true)}
            LEFT JOIN {$this->getJoinSql('owner.pronoun', 'o', 'op')}
            WHERE t.deleted_at IS NULL
            AND t.categoryID = :cat
            AND t.date < :time
            LIMIT $offset, $limit
        ");

        $stmt->execute([
            'cat'  => $context,
            'time' => $time,
        ]);

        $c = $this->createCollection();

        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
            /** @var Thread $thread */
            $thread = $this->hydrate($r, 't');
            /** @var User $owner */
            $owner = $this->hydrateJoin($r, 'owner', 'o');
            $owner->setPronoun($this->hydrateJoin($r, 'owner.pronoun', 'op'));
            $thread->setUser($owner);

            $c->add($thread);
        }

        return $c;
    }

    /**
     * @param int $folderID
     * @return bool
     */
    public function isComponent($folderID)
    {
        /** replacement for forumHandler->hasForum the code:
         *
         * ```$result = $this->db->query($query)->fetchColumn();
         * if (isset($result)) {
         * return true;
         * }
         *
         * will always return true, as the $result will always be set, even it its set to false.
         * where this checks if there actually are results
         */

        /**
         * break up the forum query into 2 separate checks, one to check if it is on a page,
         * the other to see if it is alt sourced.
         */
        $homepageQuery = $this->database->prepare("
            SELECT ci.id FROM component_instance ci
            JOIN component_definition cd ON ci.`component_definition_id`=cd.`id`
            WHERE class = 'Component_Homepage_ForumListController' AND ci.module = 'homepage'
            AND identifier = :id
        ");

        $homepageQuery->execute(['id' => $folderID]);

        /**
         * if we found a forum on the homepage, return true, else check for alt sourcing forums
         */
        if (($row = $homepageQuery->fetch()) !== false) {
            return true;
        }

        $altSourceQuery = $this->database->prepare("
            SELECT
                `component_instance`.`id`, `component_instance`.`identifier`, `cci`.`value`
            FROM        `component_instance`
            INNER JOIN  `component_definition` `cd` ON `component_instance`.`component_definition_id` = `cd`.`id`  AND class = 'Component_Homepage_ForumListController'
            JOIN   `component_config_definition` `ccd` ON `cd`.`id` = `ccd`.`component_definition_id` AND `ccd`.`label` = 'Source'
            JOIN   `component_config_instance` `cci` ON
            `ccd`.`id` = `cci`.`component_config_definition_id` AND
            `cci`.`component_instance_id` = `component_instance`.`id` AND `cci`.`value` = :id1

            WHERE
                `component_instance`.module = 'homepage'
            AND
                `component_instance`.`identifier` != :id2
        ");

        $altSourceQuery->execute([
            'id1' => $folderID,
            'id2' => $folderID,
        ]);

        if (($row = $altSourceQuery->fetch()) !== false) {
            return true;
        }

        /**
         * no results from either homepage or alt sourcing, no forum enabled
         */
        return false;
    }

    /**
     * Soft-deletes Forum for specified homepage
     *
     * @param int $cid
     * @return void
     */
    public function homepageClean($cid)
    {
        $this->deleteByColumn('categoryID', $cid);
    }

    /**
     * @param int   $categoryID
     * @param array $config
     * @return array|bool
     */
    public function getRecentThreadList(int $categoryID, array $config)
    {
        /* Set default sorting to threads, but leave options for other sort fields
         */
        $params = [
            'categoryID' => $categoryID,
        ];

        $query = "SELECT t.*, u.title userTitle, u.firstname userFirstname, u.preferred_name userPreferredName, u.lastname userLastname, u.synergy_id,
                  if (u.pronoun, concat('(', pronoun.subjective, '/', pronoun.objective, ')'), '') as 'pronoun'
                  FROM forumThread t
                  LEFT JOIN user u ON u.id = t.userID
                  LEFT JOIN `pronoun` ON u.pronoun = pronoun.id
                  WHERE t.categoryID = :categoryID
                  AND t.deleted_at IS NULL
                  ORDER BY ";

        if ($config['sort'] == 'Posts') {
            $query .= ' t.last_post_date DESC ';
        } elseif ($config['sort'] == 'Threads') {
            $query .= 't.date DESC ';
        } else {
            $query .= 't.date DESC ';
        }

        if ($config['size'] != 0 && $config['size'] != '') {
            $query          .= " LIMIT 0, :size";
            $params['size'] = (int)$config['size'];
        }
        $statement = $this->database->prepare($query);
        foreach ($params as $param => $value) {
            $statement->bindValue($param, $value, PDO::PARAM_INT);
        }
        $statement->execute();
        return $statement->fetchAll();
    }

    /**
     * @inheritdoc
     * @throws \Schoolbox\Mapper\DefinitionException
     */
    protected function getSelectString(): string
    {
        return "
            SELECT {$this->getColumnString()},
                   {$this->getJoinColumnString('owner', 'owner')},
                   {$this->getJoinColumnString('owner.pronoun', 'op')},
                   {$this->getJoinColumnString('lastPoster', 'lastPoster')},
                   {$this->getJoinColumnString('lastPoster.pronoun', 'lpp')},
                   {$this->getJoinColumnString('post', 'post')}
        ";
    }


    /**
     * @inheritdoc
     */
    protected function getFromString(): string
    {
        return "
            FROM       {$this->getTable()}
            INNER JOIN {$this->getJoinSql('owner', '', 'owner', true)}
            INNER JOIN {$this->getJoinSql('lastPoster', '', 'lastPoster', true)}
            LEFT JOIN {$this->getJoinSql('owner.pronoun', 'owner', 'op')}
            LEFT JOIN {$this->getJoinSql('lastPoster.pronoun', 'lastPoster', 'lpp')}
            LEFT JOIN {$this->getJoinSql('post', '', 'post', false)}
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
            /** @var User $lastPoster */
            $lastPoster = $this->hydrateJoin($row, 'lastPoster', 'lastPoster');
            $lastPoster->setPronoun($this->hydrateJoin($row, 'lastPoster.pronoun', 'lpp'));
            $thread                       = $this->hydrate($row)
                                                 ->setUser($owner)
                                                 ->setLastPost($this->hydrateJoin($row, 'post', 'post'))
                                                 ->setLastPostUser($lastPoster);
            $collection[$thread->getId()] = $thread;
        }

        return $collection;
    }

    /**
     * find post count for a list of forum threads
     */
    public function findPostCount(Collection $threads): array
    {
        $statement = $this->database->prepare("
            SELECT threadID, count(*) -1 as count
            FROM forumPost
            WHERE threadID in (" . implode(',', $threads->getPropertyValues('id')) . ")
            AND deleted_at IS NULL
            GROUP BY threadID
        ");
        $statement->execute();
        $postCounts = [];
        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $postCounts[$row['threadID']] = $row['count'];
        }
        return $postCounts;
    }
}
