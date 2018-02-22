<?php
/**
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\News;

use PDO;
use Schoolbox\Campus;
use Schoolbox\CampusMapper;
use Schoolbox\Collection;
use Schoolbox\Column\Model;
use Schoolbox\Mapper;
use Schoolbox\Mapper\Exception;
use Schoolbox\Mapper\Searchable;
use Schoolbox\Resource\Folder\Model as FolderModel;
use Schoolbox\Resource\Category\NewsMapper;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Resource\Storage;
use Schoolbox\User;

class ArticleMapper extends Mapper implements Searchable
{

    protected function mapping()
    {
        $this->hasTable('news_items');
        $this->hasModel(Article::class);

        $this->hasMap(
            array(
                'column'     => 'item_id',
                'property'   => 'id',
                'type'       => Mapper::TYPE_INTEGER,
                'identifier' => true
            )
        );

        $this->hasMap(
            array(
                'column'   => 'item_topic_id',
                'property' => 'topic',
                'type'     => Mapper::TYPE_MODEL,
                'join'     => 'topic'
            )
        );

        $this->hasMap(
            array(
                'column'   => 'item_heading',
                'property' => 'heading',
                'type'     => Mapper::TYPE_STRING,
            )
        );

        $this->hasMap(
            array(
                'column'   => 'item_datetime_posted',
                'property' => 'postDate',
                'type'     => Mapper::TYPE_DATETIME,
            )
        );

        $this->hasMap(
            array(
                'column'   => 'item_datetime_published',
                'property' => 'publishDate',
                'type'     => Mapper::TYPE_DATETIME,
            )
        );

        $this->hasMap(
            array(
                'column'   => 'item_datetime_expires',
                'property' => 'expiryDate',
                'type'     => Mapper::TYPE_DATETIME,
            )
        );

        $this->hasMap(
            array(
                'column'   => 'item_datetime_modified',
                'property' => 'modifiedDate',
                'type'     => Mapper::TYPE_DATETIME,
            )
        );

        $this->hasMap(
            array(
                'column'   => 'item_body',
                'property' => 'body',
                'type'     => Mapper::TYPE_STRING,
            )
        );

        $this->hasMap(
            array(
                'column'   => 'item_blurb',
                'property' => 'blurb',
                'type'     => Mapper::TYPE_STRING,
            )
        );

        $this->hasMap(
            array(
                'column'   => 'item_deleted',
                'property' => 'deleted',
                'type'     => Mapper::TYPE_BOOLEAN,
            )
        );

        $this->hasMap(
            array(
                'column'   => 'item_owner',
                'property' => 'owner',
                'type'     => Mapper::TYPE_MODEL,
                'join'     => 'owner'
            )
        );

        // for storage, we use hash, not ID
        $hydrator = new Model();
        $hydrator->setIdentifierProperty('hash');
        $this->hasMap(
            array(
                'column'   => 'item_image',
                'property' => 'image',
                'hydrator' => $hydrator,
                'join'     => 'image'
            )
        );

        $this->hasMap(
            array(
                // linked to a 'multi' join, does not have a 'column' declared
                'property' => 'roles',
                'type'     => Mapper::TYPE_COLLECTION,
                'join'     => 'roles'
            )
        );

        $this->hasMap(
            array(
                // 'column' => null
                'property' => 'campuses',
                'type'     => Mapper::TYPE_COLLECTION,
                'join'     => 'campuses'
            )
        );

        $this->hasMap(
            array(
                // 'column' => null
                'property' => 'users',
                'type'     => Mapper::TYPE_COLLECTION,
                'join'     => 'users'
            )
        );

        $this->hasSoftDeleteBooleanField('item_deleted');


        $this->hasJoin(
            'topic', array(
                'mapper'    => '\\Schoolbox\\News\\TopicMapper',
                'this_key'  => 'item_topic_id',
                'other_key' => 'newsTopicId',
            )
        );

        $this->hasJoin(
            'owner', array(
                'mapper'    => '\\Schoolbox\\UserMapper',
                'this_key'  => 'item_owner',
                'other_key' => 'id',
            )
        );

        $this->hasJoin(
            'image', array(
                'mapper'    => Storage\Mapper::class,
                'this_key'  => 'item_image',
                'other_key' => 'hash',
            )
        );

        $this->hasJoin(
            'roles', array(
                'multi'     => array(
                    'via_table' => 'newsGroup',
                    'this_key'  => 'id',
                    'other_key' => 'gid'
                ),
                'mapper'    => '\\Schoolbox\\RoleMapper',
                'this_key'  => 'item_id',
                'other_key' => 'id'
            )
        );

        $this->hasJoin(
            'campuses', array(
                'multi'     => array(
                    'via_table' => 'newsCampus',
                    'this_key'  => 'id',
                    'other_key' => 'campus'
                ),
                'mapper'    => '\\Schoolbox\\CampusMapper',
                'this_key'  => 'item_id',
                'other_key' => 'id'
            )
        );

        $this->hasJoin(
            'users', array(
                'multi'     => array(
                    'via_table' => 'newsUser',
                    'this_key'  => 'newsID',
                    'other_key' => 'userID'
                ),
                'mapper'    => '\\Schoolbox\\UserMapper',
                'this_key'  => 'item_id',
                'other_key' => 'id'
            )
        );

    }


    /**
     * Retrieves a model by id
     *
     * @throws Exception if no instance of model is found
     * @throws Exception\Deleted if the model was found but has been deleted
     *
     * @param  int $identifierValue
     *
     * @return object
     */
    public function findById($identifierValue)
    {
        $t = 0;

        // the main model we'll ultimately be populating
        $mainModel = null;

        /** @var Mapper[] $joinMappers */
        $joinMappers   = array();
        $columnStrings = array($this->getColumnString("t$t"));
        $joinSqls      = array();
        $propertyMap   = array();
        $joinNames     = array();

        $identifier       = $this->getIdentifier();
        $identifierColumn = $identifier['column'];

        foreach ($this->map as $map) {
            if (empty($map['join'])) {
                continue;
            }
            // increment our little `t` counter
            ++$t;
            $name = $map['join'];

            $alias = "t$t";

            $propertyMap[$alias] = $map['property'];
            $joinNames[$alias]   = $name;
            $joinMappers[$alias] = $this->getJoinMapper($name);
            if (!$joinMappers[$alias] instanceof Mapper) {
                continue;
            }
            $columnStrings[$alias] = $joinMappers[$alias]->getColumnString($alias);
            $joinSqls[$alias]      = $this->getJoinSql($name, 't0', $alias);
        }

        $columns = implode(', ', $columnStrings);
        $joins   = implode(' LEFT JOIN ', $joinSqls);
        if (strlen($joins)) {
            $joins = "LEFT JOIN $joins";
        }

        $stmt = $this->database->prepare(
            "
			SELECT $columns FROM `{$this->getTable()}` t0 {$joins} WHERE `t0`.`{$identifierColumn}` = :id_value
		"
        );

        $stmt->execute(array('id_value' => $identifierValue));


        // there will be more than one record returned by this query
        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (empty($mainModel)) {
                /** @var Article $mainModel */
                $mainModel = $this->hydrate($r, 't0');
                // set blank collections
                $mainModel->setRoles(new Collection());
                $mainModel->setCampuses(new Collection());
                $mainModel->setUsers(new Collection());
            }

            foreach ($joinMappers as $alias => $mapper) {
                $subIdentifier = $mapper->getIdentifier();
                $subColumn     = $subIdentifier['column'];

                $field = "$alias.$subColumn";

                if (is_null($r[$field])) {
                    continue;
                }

                $entity = $mapper->hydrate($r, $alias);

                $property = $propertyMap[$alias];
                $joinName = $joinNames[$alias];

                // singular value
                if (empty($this->joins[$joinName]['multi'])) {
                    $setEntity = $this->getSetMethodName($property);

                    $mainModel->$setEntity($entity);
                } // multi-value...this is a bit circuitous...
                else {
                    $getEntity = $this->getGetMethodName($property);

                    if (!$mainModel->$getEntity() instanceof Collection) {
                        $setEntity = $this->getSetMethodName($property);
                        $mainModel->$setEntity(new Collection());
                    }
                    $mainModel->$getEntity()->add($entity);
                }
            }
        }

        if ($mainModel instanceof Article) {
            return $mainModel;
        }

        throw new \Schoolbox\Mapper\Exception("Instance of object not found");
    }


    /**
     * Performs a search with the given parameters, and returns a Collection of the resultant set.
     *
     * @param User   $user
     * @param string $keyword
     * @param int    $offset
     * @param int    $limit
     * @param string $sortProperty
     * @param string $sortDirection
     *
     * @return Collection
     */
    public function search(
        $user,
        $keyword,
        $offset = 0,
        $limit = self::DEFAULT_LIMIT,
        $sortProperty = '',
        $sortDirection = 'DESC'
    ) {

        $campusMapper = $this->getJoinMapper('campuses');
        $userMapper   = $this->getJoinMapper('users');
        $roleMapper   = $this->getJoinMapper('roles');
        $topicMapper  = $this->getJoinMapper('topic');

        $stmt = $this->database->prepare(
            "
			SELECT
				{$this->getColumnString('a')},
				{$topicMapper->getColumnString('t')},
				{$userMapper->getColumnString('u')},
				{$campusMapper->getColumnString('c')},
				{$roleMapper->getColumnString('r')},
				MATCH(a.item_heading,a.item_body) AGAINST (:keyword) AS `score`
			FROM
				`{$this->getTable()}`
				LEFT JOIN {$this->getJoinSql('topic', 'a', 't')}
				LEFT JOIN {$this->getJoinSql('users', 'a', 'u')}
				LEFT JOIN {$this->getJoinSql('campuses', 'a', 'c')}
				LEFT JOIN {$this->getJoinSql('groups', 'a', 'r')}
			WHERE
				a.item_owner != :user_id
				AND a.item_deleted = 0
				AND MATCH(a.item_heading,a.item_body) AGAINST (:keyword)
				AND
				(
					a.item_datetime_published <= :publish_date
					AND ((c.id = :campus_id AND r.id = :role_id) OR u.id = :user_id)
					OR a.owner = :user_id
				)
			GROUP BY a.item_id
			ORDER BY newsTopic.newsTopicID ASC, news_items.item_datetime_posted DESC
		"
        );

        $records = array();
        // if you're a superuser, set the publish date filter to a few hundred years in the future
        $time = $user->isSuperuser() ? time() + 3 * 1e10 : time();

        // iterate by campus
        foreach ($user->getCampusCollection() as $campus) {
            /** @var Campus $campus */
            $stmt->execute(
                array(
                    'keyword'      => $keyword,
                    'user_id'      => $user->getId(),
                    'role_id'      => $user->getRole()->getId(),
                    'campus_id'    => $campus->getId(),
                    'publish_date' => date('Y-m-d', $time)
                )
            );

            $records = array_merge($records, $stmt->fetchAll(PDO::FETCH_ASSOC));
        }

        $collection = $this->hydrateList(
            $records, 'a', function ($mapper, $model, $row) {
                $map = array(
                    't' => 'topic',
                    'u' => 'users',
                    'r' => 'roles',
                    'c' => 'campuses',
                );

                foreach ($map as $alias => $name) {
                    /** @var Mapper $mapper */
                    $mapper->setModelProperty($model, $name, $mapper->hydrateJoin($row, $name, $alias));
                }
            }
        );

        $collection = $collection->slice($offset, $limit)->sortBy('publishDate', 'DESC');

        return $collection;
    }


    /**
     * @param bool   $archive
     * @param string $topic
     * @param int    $year
     * @param int    $month
     * @param int    $offset
     * @param int    $limit
     * @param bool   $groupByTopic
     *
     * @return Collection|Collection[]
     */
    public function getListing(
        $archive = true,
        $topic = '',
        $year = 0,
        $month = 0,
        $offset = 0,
        $limit = 0,
        $groupByTopic = false
    ) {

        $columns = array();

        if ($topic) {
            $topicObject              = TopicMapper::getInstance()->findByColumn('name', $topic)->first();
            $columns['item_topic_id'] = $topicObject->getId();
        }

        // filter by archive status, year, and month (as specified)
        $c = $this->findByColumnList($columns)->filterByCallback(
            function ($item) use ($archive, $year, $month) {
                /** @var Article $item */
                $publishDate = $item->getPublishDate();
                $expireStamp = $item->getExpiryDate()->getUnixTimeStamp();

                $yearMatch  = $year ? $publishDate->getYear() == $year : true;
                $monthMatch = $month ? $publishDate->getMonth() == $month : true;

                return ($archive ? $expireStamp < time() : $expireStamp > time()) && $monthMatch && $yearMatch;
            }
        )->sortBy('publishDate', 'ASC');

        if ($limit) {
            $c = $c->slice($offset, $limit);
        }

        if ($groupByTopic) {
            $c = $c->groupByCallback(
                function ($item) {
                    /** @var $item Article */
                    return $item->getTopic()->getId();
                }
            );
        }

        return $c;
    }

    /**
     * Find all the news relevant to a particular user
     * by finding news they are directly related to, that their group/role is,
     * and optionally for a particular category
     *
     * @param User $user
     * @param      $category
     *
     * @return \Schoolbox\Collection
     */
    public function findAllForUserAndCategory(User $user, $category)
    {
        $subjectNewsMapper = new NewsMapper();

        /* Find all the news relevant to a specific category
         */
        if ($category instanceof FolderModel) {
            $allArticles = $subjectNewsMapper->findForFeed($category->getId(), 9999);
        } /* A combination of news related to you, specifically;
         * and roles within campuses; and your associated categories
         */
        else {
            $categoryMapper = FolderMapper::getInstance();
            $allArticles    = new Collection();

            /* User campuses
             */
            $campusMapper = CampusMapper::getInstance();
            $userCampuses = $campusMapper->getAvailableCampusesForUser($user);
            $user->setCampusCollection($userCampuses);

            /* Find all the news relevant to all categories
             */
            $allCategories = $categoryMapper->findForUser($user);

            foreach ($allCategories as $currentCategory) {
                $allArticles = $allArticles->merge($subjectNewsMapper->findForFeed($currentCategory->getId(), 9999));
            }

            /* Find all the news relevant to roles and campuses
             */
            $allRecords = array();

            $userMapper   = $this->getJoinMapper('users');
            $imageMapper  = $this->getJoinMapper('image');
            $campusMapper = $this->getJoinMapper('campuses');
            $roleMapper   = $this->getJoinMapper('roles');

            /* Find all the news relevant to you specifically by id,
             * or by role type in your campus
             */
            $query = "
                SELECT
                    {$this->getColumnString('a')},
                    {$imageMapper->getColumnString('i')},
                    {$userMapper->getColumnString('u')},
                    {$campusMapper->getColumnString('c')},
                    {$roleMapper->getColumnString('r')}
                FROM `{$this->getTable()}` a
                LEFT JOIN {$this->getJoinSql('image', 'a', 'i')}
                LEFT JOIN {$this->getJoinSql('users', 'a', 'u')}
                LEFT JOIN {$this->getJoinSql('campuses', 'a', 'c')}
                LEFT JOIN {$this->getJoinSql('roles', 'a', 'r')}
                WHERE a.item_deleted = 0
                    AND a.item_datetime_published <= NOW()
                    AND a.item_datetime_expires > NOW()
                    AND (
                        u.id = :userId
                        OR a.item_owner = :userId
                        OR (c.id = :campusId AND r.id = :roleId)
                    )
                ORDER BY a.item_datetime_posted DESC
            ";

            $statement = $this->getDatabase()->prepare($query);

            // iterate by campus
            foreach ($user->getCampusCollection() as $campus) {
                $statement->execute(
                    array(
                        'userId'      => $user->getId(),
                        'roleId'      => $user->getRole()->getId(),
                        'campusId'    => $campus->getId(),
                    )
                );

                $allRecords = array_merge($statement->fetchAll(PDO::FETCH_ASSOC));
            }

            /* Hydrate
             */
            foreach ($allRecords as $currentRecord) {
                $article = $this->hydrate($currentRecord, 'a');

                /* Only ram in a hydrated image if there's an actual image there to use
                 * Otherwise set to null (Article sets an empty IStorage object by default)
                 */
                if (strlen(trim($currentRecord['i.hash']))) {
                    /** @var Storage\IStorage $image */
                    $image = $imageMapper->hydrate($currentRecord, 'i');
                    $article->setImage($image);
                } else {
                    $article->setImage(null);
                }

                $allArticles->add($article);
            }
        }

        $allArticles->sortBy('publishDate', 'DESC');

        return $allArticles;
    }

    /**
     * Find all the news relevant to a particular user
     * by finding news they are directly related to, that their group/role is,
     * and optionally for a particular news topic
     *
     * @param User $user
     * @param      $topic
     *
     * @return \Schoolbox\Collection
     */
    public function findAllForUserAndTopic(User $user, Topic $topic)
    {
        $allArticles = new Collection();

        /* Find all the news relevant to roles and campuses
         */
        $imageMapper  = $this->getJoinMapper('image');
        $topicMapper  = $this->getJoinMapper('topic');

        /* Find all the news relevant to you specifically by id,
         * or by role type in your campus
         */
        $campusList = $user->getCampusCollection()->getColumnValues('id');
        if (empty($campusList)) {
            // empty collection
            return $allArticles;
        }
        $campusPlaceholders = $this->getArrayPlaceholders($campusList, 'campus');
        $params             = $this->getArrayParameters($campusList, 'campus');
        $params['userId']   = $user->getId();
        $params['roleId']   = $user->getRole()->getId();

        $topicQuery = '';
        if ($topic->getId()) {
            $topicQuery = 'AND t.newsTopicId = :topicId';
            $params['topicId'] = $topic->getId();
        }

        $query = "
            SELECT DISTINCT
                {$this->getColumnString('a')},
                {$imageMapper->getColumnString('i')},
                {$topicMapper->getColumnString('t')}
            FROM `{$this->getTable()}` a
            LEFT JOIN {$this->getJoinSql('image', 'a', 'i')}
            LEFT JOIN {$this->getJoinSql('users', 'a', 'u')}
            LEFT JOIN {$this->getJoinSql('topic', 'a', 't')}
            LEFT JOIN `newsCampus` AS `v_c` ON `a`.`item_id` = `v_c`.`id`
            LEFT JOIN `newsGroup` AS `v_r` ON `a`.`item_id` = `v_r`.`id`
            WHERE a.item_deleted = 0
                $topicQuery
                AND a.item_datetime_published <= NOW()
                AND a.item_datetime_expires > NOW()
                AND (
                    u.id = :userId
                    OR a.item_owner = :userId
                    OR (v_c.campus IN $campusPlaceholders AND v_r.gid = :roleId)
                )
            ORDER BY a.item_datetime_posted DESC
        ";

        $statement = $this->getDatabase()->prepare($query);

        $statement->execute($params);

        $allRecords = $statement->fetchAll(PDO::FETCH_ASSOC);

        /* Hydrate
         */
        foreach ($allRecords as $currentRecord) {

            /** @var Article $article */
            $article = $this->hydrate($currentRecord, 'a');
            $article->setTopic($topicMapper->hydrate($currentRecord,'t'));

            /* Only ram in a hydrated image if there's an actual image there to use
             * Otherwise set to null (Article sets an empty IStorage object by default)
             */
            if (strlen(trim($currentRecord['i.hash']))) {
                /** @var Storage\IStorage $image */
                $image = $imageMapper->hydrate($currentRecord, 'i');
                $article->setImage($image);
            } else {
                $article->setImage(null);
            }

            $allArticles->add($article);
        }

        $allArticles->sortBy('publishDate', 'DESC');

        return $allArticles;
    }

    /**
     * Find all the news items relevant to $topic
     *
     * @param     Topic $topic
     * @param     $limit
     * @param int $offset
     * @param int $time
     *
     * @return Collection|Article[]
     */
    public function findForTopic(Topic $topic, $limit, $offset = 0, $time = 0)
    {
        $time = date('Y-m-d H:i:s', $time ? : time());
        $now  = \Alaress_Date_Format::now();

        $limit  = (int)$limit;
        $offset = (int)$offset;

        $imageMapper  = $this->getJoinMapper('image');

        $query = "
			SELECT
				{$this->getColumnString('n')},
				{$this->getJoinColumnString('owner', 'u')},
				{$this->getJoinColumnString('topic', 't')},
				{$imageMapper->getColumnString('i')}
			FROM
				`{$this->getTable()}` `n`
				LEFT JOIN {$this->getJoinSql('owner', 'n', 'u')}
				LEFT JOIN {$this->getJoinSql('topic', 'n', 't')}
				LEFT JOIN {$this->getJoinSql('image', 'n', 'i')}
			WHERE
				`n`.`item_deleted` = FALSE
				AND `n`.`item_datetime_published` <= NOW()
				AND `n`.`item_topic_id` = :topicId
				AND `n`.`item_datetime_expires` > NOW()
			ORDER BY `n`.`item_datetime_posted` DESC
			LIMIT $offset, $limit
		";

        $stmt = $this->database->prepare($query);

        $stmt->execute(
            array(
                'time'    => $time,
                'topicId' => $topic->getId(),
                'now'     => $now->getDatabaseDate(),
            )
        );

        $c = $this->createCollection();

        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
            /** @var Article $news */
            $news = $this->hydrate($r, 'n');
            $news->setOwner($this->hydrateJoin($r, 'owner', 'u'));
            $news->setTopic($this->hydrateJoin($r, 'topic', 't'));

            /* Only ram in a hydrated image if there's an actual image there to use
             * Otherwise set to null (Article sets an empty IStorage object by default)
             */
            if (strlen(trim($r['i.hash']))) {
                /** @var Storage\IStorage $image */
                $image = $imageMapper->hydrate($r, 'i');
                $news->setImage($image);
            } else {
                $news->setImage(null);
            }

            $c->add($news);
        }
        return $c;
    }
}
