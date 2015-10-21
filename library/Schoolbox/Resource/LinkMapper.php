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
use PDOStatement;
use Schoolbox\Collection;
use Schoolbox\Mapper;
use Schoolbox\Mapper\Searchable;
use Schoolbox\Notif\Notif;
use Schoolbox\Resource\File\IFile;
use Schoolbox\Resource\File\ILocator;
use Schoolbox\User;
use Schoolbox\Resource\Link\Search\Result;
use Schoolbox\Resource\Folder\Model as CategoryModel;

class LinkMapper extends Mapper implements ILocator, Searchable
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
     * @return string
     */
    protected function getSelectString()
    {
        // much join.
        $table = $this->getTable();
        return "
            SELECT {$this->getColumnString()},
                   {$this->getJoinColumnString('user', 'u')},
                   {$this->getJoinColumnString('category', 'c')}
            FROM `$table`
            LEFT JOIN {$this->getJoinSql('user', $table, 'u')}
            LEFT JOIN {$this->getJoinSql('category', $table, 'c')}
        ";
    }

    /**
     * @param PDOStatement $statement
     * @return Collection
     */
    protected function hydrateSelect(PDOStatement $statement)
    {
        $c = $this->createCollection();
        while ($r = $statement->fetch(PDO::FETCH_ASSOC)) {
            $link = $this->hydrate($r);
            $link->setOwner($this->hydrateJoin($r, 'user', 'u'));
            $link->setFolder($this->hydrateJoin($r, 'category', 'c'));
            $c->add($link);
        }
        return $c;
    }

    /**
     * Find all the links in a given category
     *
     * @param CategoryModel $category
     * @param $options
     * @return Collection
     */
    public function findAllInCategory(CategoryModel $category, $options)
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
        $notif->notice("CRUD", $logEntry);
    }

    /**
     * Performs a search with the given parameters, and returns a Collection of the resultant set.
     *
     * This also returns a count of all the items found in the search, not limited
     * by params
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
        if ($limit == 0) {
            $limit = self::DEFAULT_LIMIT;
        }

        $searcher = new Result();
        $searcher->search($user, $this, $keyword, $sortProperty, $sortDirection, $offset, $limit);

        return $searcher;
    }

    /**
     * Return the files contained within the folder with the given ID
     *
     * @param int $nodeId
     *
     * @return Collection|IFile[]
     */
    public function findByFolderId($nodeId)
    {
        // Sort by title
        return $this->findByColumn('category', $nodeId, ['order' => ['description' => 'asc',],]);
    }
}
