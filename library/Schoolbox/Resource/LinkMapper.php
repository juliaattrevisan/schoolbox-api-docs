<?php
/**
 * Mapper for links in the resource browser and homepages.
 *
 * @author    James Leckie <jleckie@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Resource;

use PDO;
use Schoolbox\Collection;
use Schoolbox\Mapper;
use Schoolbox\Mapper\Searchable;
use Schoolbox\Notif\Notif;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\User;
use Schoolbox\Resource\Search\Sanitiser;

class LinkMapper extends Mapper implements Searchable
{
    /**
     * Mapping
     *
     * @return void
     */
    protected function mapping()
    {
        $this->hasTable('link');
        $this->hasModel('\\Schoolbox\\Resource\\Link');
        $this->hasMap(
            array(
                'column'     => 'id',
                'property'   => 'id',
                'type'       => self::TYPE_INTEGER,
                'identifier' => true,
            )
        );
        $this->hasMap(
            array(
                'column'   => 'category',
                'property' => 'folder',
                'type'     => self::TYPE_MODEL,
                'join'     => 'category'
            )
        );
        $this->hasMap(
            array(
                'column'   => 'owner',
                'property' => 'owner',
                'type'     => self::TYPE_MODEL,
                'join'     => 'user',
            )
        );
        $this->hasMap(
            array(
                'column'   => 'url',
                'property' => 'url',
                'type'     => self::TYPE_STRING,
            )
        );
        $this->hasMap(
            array(
                'column'   => 'comment',
                'property' => 'comment',
                'type'     => self::TYPE_STRING,
            )
        );
        $this->hasMap(
            array(
                'column'   => 'description',
                'property' => 'description',
                'type'     => self::TYPE_STRING,
            )
        );
        $this->hasMap(
            array(
                'column'   => 'hidden',
                'property' => 'hidden',
                'type'     => self::TYPE_STRING,
            )
        );
        $this->hasMap(
            array(
                'column'            => 'submitted',
                'property'          => 'submitted',
                'type'              => self::TYPE_DATETIME,
                'created_timestamp' => true,
            )
        );
        $this->hasMap(
            array(
                'column'            => 'modified',
                'property'          => 'modified',
                'type'              => self::TYPE_DATETIME,
                'updated_timestamp' => true,
            )
        );
        $this->hasMap(
            array(
                'column'   => 'deleted_at',
                'property' => 'deletedAt',
                'type'     => self::TYPE_DATETIME,
            )
        );
        $this->hasJoin(
            'user', array(
                'mapper'    => '\\Schoolbox\\UserMapper',
                'this_key'  => 'owner',
                'other_key' => 'id',
            )
        );
        $this->hasJoin(
            'category', array(
                'mapper'    => '\\Schoolbox\\Resource\\CategoryMapper',
                'this_key'  => 'category',
                'other_key' => 'id'
            )
        );

        $this->hasJoin(
            'perms',
            array(
                'mapper' => '\\Schoolbox\\Permission\\Access\\RoleAccessMapper',
                'this_key' => 'category',
                'other_key' => 'cid'
            )
        );

        $this->hasJoin(
            'userAccess',
            array(
                'mapper' => '\\Schoolbox\\Permission\\Access\\UserAccessMapper',
                'this_key' => 'category',
                'other_key' => 'sid'
            )
        );

        $this->hasSoftDeleteDateField('deleted_at');
    }

    /**
     * Find all the links in a given category
     *
     * @param Category $category
     * @param $options
     * @return Collection
     */
    public function findAllInCategory(Category $category, $options)
    {
        $categoryMapper = $this->getJoinMapper('category');
        $userMapper     = $this->getJoinMapper('user');

        /* Special case misrepresentations in db
         */
        if ($options['sort'] == 'Date') {
            $sort = 'submitted';
        } else {
            $sort = 'description';
        }

        $sortDir = strtoupper($options['sortdir']) == 'DESC' ? 'DESC' : 'ASC';

        $limit = (int)$options['size'];

        $query = "
            SELECT
            	{$this->getColumnString('l')},
            	{$categoryMapper->getColumnString('c')},
            	{$userMapper->getColumnString('o')}
            FROM `{$this->table}` l
            LEFT JOIN {$this->getJoinSql('category', 'l', 'c')}
            LEFT JOIN {$this->getJoinSql('user', 'l', 'o')}
            WHERE l.category = :categoryId
            AND l.deleted_at IS NULL
            ORDER BY l.{$sort} {$sortDir}
		";

        if ($options['size']) {
            $query .= "LIMIT {$limit}";
        }

        $statement = $this->database->prepare($query);
        $statement->bindParam(':categoryId', $category->getId());
        $statement->execute();

        /* Manually construct hierarchy of blog items and their comments
         */
        $collection = new Collection();

        foreach ($statement as $row) {
            $link = $this->hydrate($row, 'l');

            $link->setFolder($categoryMapper->hydrate($row, 'c'));
            $link->setOwner($userMapper->hydrate($row, 'o'));

            $collection->add($link);
        }

        return $collection;
    }

    /**
     * Log action
     *
     * @param User $user
     * @param $action
     * @param Link $link
     */
    public function logAction(User $user, $action, Link $link)
    {
        $logEntry = array(
            'object'   => 'link',
            'objectId' => $link->getId(),
            'action'   => $action,
            'user'     => $user->getId(),
        );

        $notif = new Notif(Notif::TOPIC_CMS);
        $notif->info("CRUD", $logEntry);
    }

    /**
     * Performs a search with the given parameters, and returns a Collection of the resultant set.
     *
     * @param User $user
     * @param string $keyword
     * @param int $offset
     * @param int $limit
     * @param string $sortProperty
     * @param string $sortDirection
     * @return Collection
     */
    public function search($user, $keyword, $offset = 0, $limit = self::DEFAULT_LIMIT, $sortProperty = '', $sortDirection = 'DESC')
    {
        $sanitiser = new Sanitiser($this, $keyword, $sortProperty, $sortDirection, $offset, $limit);

        $query = "
			SELECT
				{$this->getColumnString('l')},
				{$this->getJoinMapper('user')->getColumnString('u')},
				{$this->getJoinMapper('category')->getColumnString('c')},
				MATCH(`url`,`description`,`comment`) AGAINST ({$sanitiser->getKeyword()}) AS `score`
			FROM
				`{$this->getTable()}` `l`
				LEFT JOIN {$this->getJoinSql('user', 'l', 'u')}
				LEFT JOIN {$this->getJoinSql('category', 'l', 'c')}
				LEFT JOIN {$this->getJoinSql('perms', 'l', 'p')}
					AND p.gid = :role_id
					AND p.type > :min_access
				LEFT JOIN {$this->getJoinSql('userAccess', 'l', 'a')}
					AND a.service = :service
					AND a.uid = :user_id
			WHERE
				(p.cid OR a.sid)
				AND l.deleted_at IS NULL
				AND MATCH(`url`,`description`,`comment`) AGAINST ({$sanitiser->getKeyword()})
			ORDER BY l.{$sanitiser->getSortBy()} {$sanitiser->getSortDir()}
			LIMIT {$sanitiser->getOffset()}, {$sanitiser->getLimit()}
		";

        $stmt = $this->getDatabase()->prepare($query);
        $stmt->execute(array(
            'role_id' => $user->getRole()->getId(),
            'service' => UserAccess::SERVICE_HOMEPAGE,
            'min_access' => UserAccess::ACCESS_READ,
            'user_id' => $user->getId()
        ));

        $collection = $this->createCollection();

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $link = $this->hydrate($row, 'l');
            $owner = $this->hydrateJoin($row, 'user', 'u');
            $category = $this->hydrateJoin($row, 'category', 'c');

            $link->setOwner($owner)->setFolder($category);

            $collection->add($link);
        }

        return $collection;
    }
}