<?php
/**
 * Eportfolio_PageMapper
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace SocialStream;

use Alaress\Async\Notification\Instance\Instance;
use PDO;
use Schoolbox;
use Schoolbox\Collection;
use SocialStream\Post\Factory;
use SocialStream_Post_Abstract;

class PostMapper extends Schoolbox\Mapper implements \Schoolbox\Feed\FeedableMapperInterface
{

    /**
     * Define column/property mapping
     *
     * @return Bool|void
     */
    protected function mapping()
    {
        $this->hasTable('socialstream_post');
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
            'multiple'  => true,
            'mapper'    => '\\Schoolbox\\UserMapper',
            'this_key'  => 'owner_id',
            'other_key' => 'id',
        ));

        $this->hasSoftDeleteDateField('deleted_at');
    }


    /**
     * Custom findById
     *
     * @param mixed $identifier
     * @return SocialStream_Post_Abstract
     * @throws Schoolbox\Mapper\Exception
     */
    public function findById($identifier)
    {
        $user_mapper = $this->getJoinMapper('user');

        $query = "
            SELECT
                {$this->getColumnString('p')},
                {$user_mapper->getColumnString('u')}
            FROM `{$this->table}` p
            LEFT JOIN {$this->getJoinSql('user', 'p', 'u')}
            WHERE p.post_id = :postId
        ";

        $statement = $this->database->prepare($query);
        $statement->bindParam(":postId", $identifier);
        $statement->execute();

        $row = $statement->fetch();

        if ($row != false) {
            $postItem = $this->hydrate($row, 'p');

            $userItem = $user_mapper->hydrate($row, 'u');
            $postItem->setOwner($userItem);

            $this->throwIfDeleted($postItem);

            return $postItem;
        }
        throw new Schoolbox\Mapper\Exception('Instance of object not found');
    }


    /**
     * Find the maximum number of posts at a given point in time
     * for a particular homepage
     *
     * @param $homepageId
     * @return mixed
     */
    public function findCount($homepageId)
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
     * @param $homepageId
     * @param $start
     * @param $offset
     * @return Collection
     */
    public function findLatestForHomepage($homepageId, $start, $offset)
    {
        $user_mapper = $this->getJoinMapper('user');

        $query = "
            SELECT
                {$this->getColumnString('p')},
                {$user_mapper->getColumnString('u')}
            FROM `{$this->table}` p
            LEFT JOIN {$this->getJoinSql('user', 'p', 'u')}
            WHERE homepage_id = :homepageId
            AND parent_id = 0
            AND deleted_at IS NULL
            ORDER BY created_at DESC
        ";

        /* Optional limit
         */
        if ($offset) {
            $query .= "
                LIMIT :start,:offset
            ";

            $statement = $this->database->prepare($query);
            $statement->bindParam(":homepageId", $homepageId);
            $statement->bindParam(':start', $start, \PDO::PARAM_INT);
            $statement->bindParam(':offset', $offset, \PDO::PARAM_INT);
        } else {
            $statement = $this->database->prepare($query);
            $statement->bindParam(":homepageId", $homepageId);
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
     * @param $homepageId
     * @param $end
     * @param $offset
     * @return Collection
     */
    public function findLatestForHomepageSlow($homepageId, $end, $offset)
    {
        $user_mapper = $this->getJoinMapper('user');

        $query = "
            SELECT
                {$this->getColumnString('p')},
                {$user_mapper->getColumnString('u')}
            FROM `{$this->table}` p
            LEFT JOIN {$this->getJoinSql('user', 'p', 'u')}
            WHERE homepage_id = :homepageId
            AND parent_id = 0
            AND deleted_at IS NULL
            ORDER BY created_at DESC
            LIMIT :end
        ";

        $statement = $this->database->prepare($query);
        $statement->bindParam(":homepageId", $homepageId);
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
     * @param $ids
     * @return \Collection
     */
    public function findHierarchy($ids)
    {
        $user_mapper = $this->getJoinMapper('user');

        $query = "
            SELECT
                {$this->getColumnString('p')},
                {$user_mapper->getColumnString('u')}
            FROM `{$this->table}` p
            LEFT JOIN {$this->getJoinSql('user', 'p', 'u')}
            WHERE `root_id` IN {$this->getArrayPlaceholders($ids, 'val')}
            AND deleted_at IS NULL
            ORDER BY parent_id DESC, created_at
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
     */
    public function createModel($row)
    {
        // default
        $type = 'string';

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
     * @param Array    $row
     * @param String   $alias
     * @param callable $callback
     * @return SocialStream_Post_Abstract
     */
    public function hydrate($row, $alias = '', $callback = null)
    {
        $model = $this->createModel($row);

        foreach ($this->map as $map) {
            $propertyName = $map['property'];
            $columnName   = $map['column'];

            if (!empty($alias)) {
                $columnName = "$alias.$columnName";
            }

            $columnValue = array_key_exists($columnName, $row) ? $row[$columnName] : null;
            $this->setModelProperty($model, $propertyName, $columnValue, $map);
        }

        if (!empty($callback) && is_callable($callback)) {
            $model = call_user_func($callback, $this, $model, $row);
        }

        return $model;
    }


    /**
     * Returns a collection of posts that are immediate children of the given post
     *
     * @param SocialStream_Post_Abstract $post
     * @return Collection|SocialStream_Post_Abstract[]
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
     */
    public function findForFeed($context, $limit, $offset = 0, $time = 0)
    {
        $time = date('Y-m-d H:i:s', $time ?: time());

        $limit = (int)$limit;
        $offset = (int)$offset;

        $stmt = $this->database->prepare("
			SELECT
				{$this->getColumnString()}
			FROM
				`{$this->getTable()}`
			WHERE
				`deleted_at` IS NULL
				AND `created_at` < :time
				AND `homepage_id` = :cat_id
			ORDER BY `created_at` DESC
			LIMIT $offset, $limit
		");

        $stmt->execute(array(
            'time'   => $time,
            'cat_id' => $context
        ));

        $c = $this->createCollection();

        while($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
            /** @var SocialStream_Post_Abstract $post */
            $post = $this->hydrate($r);
            $c->add($post);
        }
        return $c;
    }

}