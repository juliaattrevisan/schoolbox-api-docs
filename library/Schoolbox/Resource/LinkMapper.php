<?php

/**
 * @author    James Leckie <jleckie@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Resource;

use PDO;
use PDOStatement;
use Schoolbox\Collection;
use Schoolbox\Mapper;
use Schoolbox\Notif\Notif;
use Schoolbox\Notif\Notifactory;
use Schoolbox\Resource\File\IFile;
use Schoolbox\Resource\File\ILocator;
use Schoolbox\User;
use Schoolbox\Resource\Folder\Model as FolderModel;

/**
 * Class LinkMapper
 *
 * Mapper for links in the resource browser and homepages.
 *
 * @package Schoolbox\Resource
 * @method Link hydrate(array $row, $alias = '', $callback = null)
 * @method Link createModel($idValue = null)
 */
class LinkMapper extends Mapper implements ILocator
{
    /**
     * Mapping
     *
     * @return void
     * @throws Mapper\DefinitionException
     */
    protected function mapping(): void
    {
        $this->hasTable('link');
        $this->hasModel(Link::class);
        $this->hasMap([
            'column'     => 'id',
            'property'   => 'id',
            'type'       => self::TYPE_INTEGER,
            'identifier' => true,
        ]);
        $this->hasMap([
            'column'   => 'category',
            'property' => 'folder',
            'type'     => self::TYPE_MODEL,
            'join'     => 'folder'
        ]);
        $this->hasMap([
            'column'   => 'owner',
            'property' => 'owner',
            'type'     => self::TYPE_MODEL,
            'join'     => 'user',
        ]);
        $this->hasMap([
            'column'   => 'url',
            'property' => 'url',
            'type'     => self::TYPE_STRING,
        ]);
        $this->hasMap([
            'column'   => 'comment',
            'property' => 'comment',
            'type'     => self::TYPE_STRING,
        ]);
        $this->hasMap([
            'column'   => 'description',
            'property' => 'description',
            'type'     => self::TYPE_STRING,
        ]);
        $this->hasMap([
            'column'   => 'hidden',
            'property' => 'hidden',
            'type'     => self::TYPE_STRING,
        ]);
        $this->hasMap([
            'column'   => 'target',
            'property' => 'target',
            'type'     => self::TYPE_STRING,
        ]);
        $this->hasMap([
            'column'            => 'submitted',
            'property'          => 'submitted',
            'type'              => self::TYPE_DATETIME,
            'created_timestamp' => true,
        ]);
        $this->hasMap([
            'column'            => 'modified',
            'property'          => 'modified',
            'type'              => self::TYPE_DATETIME,
            'updated_timestamp' => true,
        ]);
        $this->hasMap([
            'column'   => 'deleted_at',
            'property' => 'deletedAt',
            'type'     => self::TYPE_DATETIME,
        ]);
        $this->hasJoin('user', [
            'mapper'    => '\\Schoolbox\\UserMapper',
            'this_key'  => 'owner',
            'other_key' => 'id',
        ]);
        $this->hasJoin('folder', [
            'mapper'    => '\\Schoolbox\\Resource\\Folder\\Mapper',
            'this_key'  => 'category',
            'other_key' => 'id'
        ]);

        $this->hasJoin('perms', [
            'mapper' => '\\Schoolbox\\Permission\\Access\\RoleAccessMapper',
            'this_key' => 'category',
            'other_key' => 'cid'
        ]);

        $this->hasJoin('userAccess', [
            'mapper' => '\\Schoolbox\\Permission\\Access\\UserAccessMapper',
            'this_key' => 'category',
            'other_key' => 'sid'
        ]);

        $this->hasSoftDeleteDateField('deleted_at');
        $this->registerPersistSearchIndexCallbacks();
    }


    /**
     * @return string
     * @throws Mapper\DefinitionException
     */
    protected function getSelectString(): string
    {
        // much join.
        $table = $this->getTable();
        return "
            SELECT {$this->getColumnString()},
                   {$this->getJoinColumnString('user', 'u')},
                   {$this->getJoinColumnString('folder', 'c')}
            FROM `$table`
            INNER JOIN {$this->getJoinSql('user', $table, 'u',  true)}
            INNER JOIN {$this->getJoinSql('folder', $table, 'c', true)}
        ";
    }


    /**
     * @param PDOStatement $statement
     * @return Link[]|Collection
     * @throws Mapper\Exception
     * @throws \UnexpectedValueException
     */
    protected function hydrateSelect(PDOStatement $statement)
    {
        $c = $this->createCollection();
        while ($r = $statement->fetch(PDO::FETCH_ASSOC)) {
            $link = $this->hydrate($r);
            $link->setOwner($this->hydrateJoin($r, 'user', 'u'));
            $link->setFolder($this->hydrateJoin($r, 'folder', 'c'));
            $c->add($link);
        }
        return $c;
    }


    /**
     * Find all the links in a given category
     *
     * @param FolderModel $folder
     * @param array       $options
     * @param bool        $includeHidden
     * @return Collection
     * @throws Mapper\DefinitionException
     * @throws Mapper\Exception
     * @throws \PDOException
     */
    public function findAllInCategory(FolderModel $folder, $options, $includeHidden = true): Collection
    {
        $folderMapper = $this->getJoinMapper('folder');
        $userMapper     = $this->getJoinMapper('user');

        /* Special case misrepresentations in db
         */
        if ('Date' === ($options['sort'] ?? null)) {
            $sort = 'submitted';
        } else {
            $sort = 'description';
        }

        $sortDir = strtoupper($options['sortdir'] ?? '') === 'DESC' ? 'DESC' : 'ASC';

        $limit = $options['size'] ?? null;

        $hiddenClause = $includeHidden ? '' : ' AND l.hidden = 0 ';

        $query = "
            SELECT
                {$this->getColumnString('l')},
                {$folderMapper->getColumnString('c')},
                {$userMapper->getColumnString('o')}
            FROM `{$this->table}` l
            LEFT JOIN {$this->getJoinSql('folder', 'l', 'c')}
            LEFT JOIN {$this->getJoinSql('user', 'l', 'o', true)}
            WHERE l.category = :categoryId
            AND l.deleted_at IS NULL
            $hiddenClause
            ORDER BY l.{$sort} {$sortDir}
        ";

        if ($limit) {
            $query .= "LIMIT {$limit}";
        }

        $statement = $this->database->prepare($query);
        $statement->bindParam(':categoryId', $folder->getId());
        $statement->execute();

        /* Manually construct hierarchy of link items and their comments
         */
        $collection = new Collection();

        foreach ($statement as $row) {
            $link = $this->hydrate($row, 'l');

            $link->setFolder($folderMapper->hydrate($row, 'c'));
            $link->setOwner($userMapper->hydrate($row, 'o'));

            $collection->add($link);
        }

        // sort the array by description using natural sort
        if ($sort === 'description') {
            $collection = $collection->sortByCallback($this->getNaturalSortCallback($sortDir), true);
        }

        return $collection;
    }


    /**
     * Log action
     *
     * @param User $user
     * @param string $action
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

        $notif = Notifactory::create(Notif::TOPIC_CMS);
        $notif->notice('CRUD', $logEntry);
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
        $collection = $this->findByColumn('category', $nodeId);
        return $collection->sortByCallback($this->getNaturalSortCallback(), true);
    }


    /**
     * Gets callback for link natural sorting
     * @param string $direction
     * @return \Closure
     */
    private function getNaturalSortCallback(string $direction = 'ASC'): callable
    {
        if ($direction === 'ASC') {
            $callback = static function (Link $a, Link $b) {
                return strnatcmp($a->getDescription(), $b->getDescription());
            };
        } else {
            $callback = static function (Link $a, Link $b) {
                return strnatcmp($b->getDescription(), $a->getDescription());
            };
        }

        return $callback;
    }
}
