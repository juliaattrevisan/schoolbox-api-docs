<?php
/**
 * PostMapper
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace SocialStream;

use Alaress\Async\Notification\Instance\Instance;
use PDO;
use Schoolbox;
use Schoolbox\Collection;
use Schoolbox\Feed\FeedableMapperInterface;
use SocialStream\Post\Factory;
use SocialStream_Post_Abstract;

class PostMapper extends Schoolbox\Mapper implements FeedableMapperInterface, Schoolbox\IHomepageCleanable
{

    /**
     * Define column/property mapping
     *
     * @return Bool|void
     * @throws Schoolbox\Mapper\DefinitionException
     */
    protected function mapping()
    {
        $this->hasTable('socialstream_post');
        $this->hasModel(SocialStream_Post_Abstract::class);
        $this->hasModuleNotifier('\\SocialStream\\PostNotifier');

        $this->hasMap(array(
            'column'     => 'post_id',
            'property'   => 'postId',
            'type'       => self::TYPE_INTEGER,
            'identifier' => true
        ));

        $this->hasMap(array(
            'column'   => 'root_id',
            'property' => 'rootId',
            'type'     => self::TYPE_INTEGER,
        ));

        $this->hasMap(array(
            'column'   => 'owner_id',
            'property' => 'owner',
            'type'     => self::TYPE_MODEL,
            'join'     => 'user',
        ));

        $this->hasMap(array(
            'column'   => 'homepage_id',
            'property' => 'homepageId',
            'type'     => self::TYPE_INTEGER,
        ));

        $this->hasMap(array(
            'column'   => 'parent_id',
            'property' => 'parentId',
            'type'     => self::TYPE_INTEGER,
        ));

        $this->hasMap(array(
            'column'   => 'thumbnail',
            'property' => 'thumbnail',
            'type'     => self::TYPE_STRING,
        ));

        $this->hasMap(array(
            'column'   => 'cache_hash',
            'property' => 'cacheHash',
            'type'     => self::TYPE_STRING,
        ));

        $this->hasMap(array(
            'column'   => 'type',
            'property' => 'type',
            'type'     => self::TYPE_STRING,
        ));

        $this->hasMap(array(
            'column'   => 'mimetype',
            'property' => 'mimetype',
            'type'     => self::TYPE_STRING,
        ));

        $this->hasMap(array(
            'column'   => 'url',
            'property' => 'url',
            'type'     => self::TYPE_STRING,
        ));

        $this->hasMap(array(
            'column'   => 'body',
            'property' => 'body',
            'type'     => self::TYPE_STRING,
        ));

        $this->hasMap(array(
            'column'   => 'external_title',
            'property' => 'externalTitle',
            'type'     => self::TYPE_STRING,
        ));

        $this->hasMap(array(
            'column'   => 'external_body',
            'property' => 'externalBody',
            'type'     => self::TYPE_STRING,
        ));

        $this->hasMap(array(
            'column'   => 'created_at',
            'property' => 'createdAt',
            'type'     => self::TYPE_DATETIME,
        ));

        $this->hasMap(array(
            'column'   => 'updated_at',
            'property' => 'updatedAt',
            'type'     => self::TYPE_DATETIME,
        ));

        $this->hasMap(array(
            'column'   => 'deleted_at',
            'property' => 'deletedAt',
            'type'     => self::TYPE_DATETIME,
        ));

        $this->hasJoin('user', array(
            'multiple'  => false,
            'mapper'    => '\\Schoolbox\\UserMapper',
            'this_key'  => 'owner_id',
            'other_key' => 'id',
        ));

        $this->hasSoftDeleteDateField('deleted_at');
    }


    /**
     * Custom findById
     *
     * @param $identifier
     * @return SocialStream_Post_Abstract
     * @throws Schoolbox\Mapper\DefinitionException
     * @throws Schoolbox\Mapper\Exception
     * @throws Schoolbox\Mapper\Exception\Deleted
     * @throws \UnexpectedValueException
     */
    public function findById($identifier): \SocialStream_Post_Abstract
    {
        /** @var Schoolbox\UserMapper $user_mapper */
        $user_mapper = $this->getJoinMapper('user');

        $query = "
            SELECT
                {$this->getColumnString('p')},
                {$user_mapper->getColumnString('u')}
            FROM `{$this->table}` p
            LEFT JOIN {$this->getJoinSql('user', 'p', 'u', true)}
            WHERE p.post_id = :postId
        ";

        $statement = $this->database->prepare($query);
        $statement->bindParam(":postId", $identifier);
        $statement->execute();

        $row = $statement->fetch();

        if ($row !== false) {
            $postItem = $this->hydrate($row, 'p');

            $userItem = $user_mapper->hydrate($row, 'u');
            $postItem->setOwner($userItem);

            $this->throwIfDeleted($postItem);

            return $postItem;
        }

        throw new Schoolbox\Mapper\Exception\NotFound();
    }


    /**
     * Find the maximum number of posts at a given point in time
     * for a particular homepage
     *
     * @param int $homepageId
     * @return mixed
     */
    public function findCount(int $homepageId)
    {
        $query = "
            SELECT COUNT(*) AS num
            FROM `{$this->table}`
            WHERE homepage_id = :homepageId
            AND parent_id = 0
            AND deleted_at IS NULL
            ORDER BY created_at DESC
        ";

        $statement = $this->database->prepare($query);
        $statement->bindParam(":homepageId", $homepageId);
        $statement->execute();

        $result = $statement->fetch();

        return $result['num'];
    }


    /**
     * Find all the social stream posts that should appear in a homepage's
     * SocialStream component
     *
     * @param int $homepageId
     * @param int $start
     * @param int $offset
     * @return Collection
     * @throws Schoolbox\Mapper\DefinitionException
     * @throws Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    public function findLatestForHomepage(int $homepageId, int $start, int $offset): Collection
    {
        /** @var Schoolbox\UserMapper $user_mapper */
        $user_mapper = $this->getJoinMapper('user');

        $query = "
            SELECT
                {$this->getColumnString('p')},
                {$user_mapper->getColumnString('u')}
            FROM `{$this->table}` p
            LEFT JOIN {$this->getJoinSql('user', 'p', 'u', true)}
            WHERE p.homepage_id = :homepageId
            AND p.parent_id = 0
            AND p.deleted_at IS NULL
            ORDER BY p.created_at DESC
        ";

        /* Optional limit
         */
        if ($offset) {
            $query .= '
                LIMIT :start,:offset
            ';

            $statement = $this->database->prepare($query);
            $statement->bindParam(':homepageId', $homepageId);
            $statement->bindParam(':start', $start, \PDO::PARAM_INT);
            $statement->bindParam(':offset', $offset, \PDO::PARAM_INT);
        } else {
            $statement = $this->database->prepare($query);
            $statement->bindParam(':homepageId', $homepageId);
        }

        $statement->execute();

        /* Manually construct hierarchy of posts and their users
         */
        $collection = new Collection();

        foreach ($statement as $row) {
            $postItem = $this->hydrate($row, 'p');
            $userItem = $user_mapper->hydrate($row, 'u');

            $postItem->setOwner($userItem);

            $collection->add($postItem);
        }

        return $collection;
    }


    /**
     * Find all the social stream posts that should appear in a homepage's
     * SocialStream component
     *
     * @param int $homepageId
     * @param int $end
     * @param int $offset
     * @return Collection
     * @throws Schoolbox\Mapper\DefinitionException
     * @throws Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    public function findLatestForHomepageSlow(int $homepageId, int $end, int $offset): Collection
    {
        /** @var Schoolbox\UserMapper $user_mapper */
        $user_mapper = $this->getJoinMapper('user');

        $query = "
            SELECT
                {$this->getColumnString('p')},
                {$user_mapper->getColumnString('u')}
            FROM `{$this->table}` p
            LEFT JOIN {$this->getJoinSql('user', 'p', 'u', true)}
            WHERE p.homepage_id = :homepageId
            AND p.parent_id = 0
            AND p.deleted_at IS NULL
            ORDER BY p.created_at DESC
            LIMIT :end
        ";

        $statement = $this->database->prepare($query);
        $statement->bindParam(':homepageId', $homepageId);
        $statement->bindParam(':end', $end, \PDO::PARAM_INT);

        $statement->execute();

        /* Manually construct hierarchy of posts and their users
         */
        $collection = new Collection();

        foreach ($statement as $row) {
            $postItem = $this->hydrate($row, 'p');
            $userItem = $user_mapper->hydrate($row, 'u');

            $postItem->setOwner($userItem);

            $collection->add($postItem);
        }

        return $collection;
    }


    /**
     * Find all the social stream hierarchies for a set of post ids
     *
     * This uses the root_id value, which is the post_id of the post
     * to which comments and replies belong.
     *
     * @param array $ids
     * @return Collection
     * @throws Schoolbox\Mapper\DefinitionException
     * @throws Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    public function findHierarchy(array $ids)
    {
        /** @var Schoolbox\UserMapper $user_mapper */
        $user_mapper = $this->getJoinMapper('user');

        $query = "
            SELECT
                {$this->getColumnString('p')},
                {$user_mapper->getColumnString('u')}
            FROM `{$this->table}` p
            LEFT JOIN {$this->getJoinSql('user', 'p', 'u', true)}
            WHERE p.root_id IN {$this->getArrayPlaceholders($ids, 'val')}
            AND p.deleted_at IS NULL
            ORDER BY p.parent_id DESC, p.created_at
        ";

        $statement = $this->database->prepare($query);
        $statement->execute($this->getArrayParameters($ids, 'val'));

        /* Manually construct hierarchy of posts and their users
         */
        $collection = new Collection();

        foreach ($statement as $row) {
            $postItem = $this->hydrate($row, 'p');
            $userItem = $user_mapper->hydrate($row, 'u');

            $postItem->setOwner($userItem);

            $collection->add($postItem);
        }

        return $collection;
    }


    /**
     * Spawn a subclass of SocialStream_Post_Abstract
     *
     * @param array $row
     * @return SocialStream_Post_Abstract
     * @throws \UnexpectedValueException
     */
    public function createModel($row = null): \SocialStream_Post_Abstract
    {
        // default
        $type = \SocialStream_Post_String::TYPE;

        foreach (array('p.type', 'type') as $t) {
            if (isset($row[$t])) {
                $type = $row[$t];
                break;
            }
        }

        $factory = new Factory\Display($type);

        return $factory->produce();
    }


    /**
     * @param array    $row
     * @param string   $alias
     * @param callable $callback
     * @return SocialStream_Post_Abstract
     * @throws \UnexpectedValueException
     */
    public function hydrate(array $row, $alias = '', $callback = null): \SocialStream_Post_Abstract
    {
        $model = $this->createModel($row);

        foreach ($this->map as $map) {
            $propertyName = $map['property'];
            $columnName   = $map['column'];

            if (!empty($alias)) {
                $columnName = "$alias.$columnName";
            }

            $columnValue = $row[$columnName] ?? null;
            $this->setModelProperty($model, $propertyName, $columnValue);
        }

        if (!empty($callback) && \is_callable($callback)) {
            $model = $callback($this, $model, $row);
        }

        return $model;
    }


    /**
     * Returns a collection of posts that are immediate children of the given post
     *
     * @param SocialStream_Post_Abstract $post
     * @return Collection|SocialStream_Post_Abstract[]
     * @throws \UnexpectedValueException
     */
    public function findChildren($post)
    {

        $stmt = $this->database->prepare("
            SELECT
                {$this->getColumnString('p')}
            FROM
                `{$this->getTable()}` p
            WHERE p.parent_id = :pid
            AND p.deleted_at IS NULL
        ");

        $stmt->execute(array(
            'pid' => $post->getPostId()
        ));

        $collection = new Collection();

        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $collection->add($this->hydrate($r, 'p'));
        }

        return $collection;
    }


    /**
     * @param int $context
     * @param int $limit
     * @param int $offset
     * @param int $time
     * @return \Schoolbox\Collection|Instance[]
     * @throws Schoolbox\Mapper\DefinitionException
     * @throws Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    public function findForFeed($context, $limit, $offset = 0, $time = 0)
    {
        $time = date('Y-m-d H:i:s', $time ?: time());

        $limit = (int)$limit;
        $offset = (int)$offset;

        /** @var Schoolbox\UserMapper $ownerMapper */
        $ownerMapper = $this->getJoinMapper('user');

        $stmt = $this->database->prepare("
            SELECT
                {$this->getColumnString('p')},
                {$ownerMapper->getColumnString('u')}
            FROM `{$this->getTable()}` p
            INNER JOIN {$this->getJoinSql('user', 'p', 'u', true)}
            WHERE
                p.deleted_at IS NULL
                AND p.created_at < :time
                AND p.homepage_id = :cat_id
            ORDER BY p.created_at DESC
            LIMIT $offset, $limit
        ");

        $stmt->execute(array(
            'time'   => $time,
            'cat_id' => $context
        ));

        $c = $this->createCollection();

        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
            /** @var SocialStream_Post_Abstract $post */
            $post = $this->hydrate($r, 'p');
            $post->setOwner($ownerMapper->hydrate($r, 'u'));
            $c->add($post);
        }
        return $c;
    }

    /**
     * Soft-deletes content for specified homepage
     *
     * @param int $cid
     * @return void
     */
    public function homepageClean($cid): void
    {
        $this->deleteByColumn('homepage_id', $cid);
    }
}
