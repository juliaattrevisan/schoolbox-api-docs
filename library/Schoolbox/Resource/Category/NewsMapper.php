<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Resource\Category;

use Alaress\Database\Predicate\Builder;
use PDO;
use Schoolbox\Collection;
use Schoolbox\Feed\FeedableMapperInterface;
use Schoolbox\GenericCollection;
use Schoolbox\Mapper;
use Schoolbox\Mapper\Searchable;
use Schoolbox\Permission\Access\RoleAccessMapper;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\User;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\Folder;
use Schoolbox\UserMapper;

/**
 * @method News hydrate(array $row, $alias = '', $callback = null)
 */
class NewsMapper extends Mapper implements Searchable, FeedableMapperInterface
{

    /**
     * @return void
     * @throws Mapper\DefinitionException If an invalid join name is specified
     */
    protected function mapping()
    {
        $this->hasTable('subject_news');
        $this->hasModel(News::class);

        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('heading', self::TYPE_STRING);
        $this->uses('datetime_posted', self::TYPE_DATETIME, null, 'postDate');
        $this->uses('datetime_published', self::TYPE_DATETIME, null, 'publishDate');
        $this->uses('datetime_expires', self::TYPE_DATETIME, null, 'expiryDate');
        $this->uses('body', self::TYPE_STRING);
        $this->uses('deleted', self::TYPE_BOOLEAN, self::COLUMN_SOFTDELETE);

        $this->joins('code', Folder\Mapper::class, 'id', null, null, 'category', 'folder');
        $this->joins('owner', UserMapper::class, 'id', null, null, 'owner', 'owner');


        $this->hasJoin('perms', [
            'mapper'    => RoleAccessMapper::class,
            'this_key'  => 'code',
            'other_key' => 'cid',
        ]);

        $this->hasJoin('userAccess', [
            'mapper'    => UserAccessMapper::class,
            'this_key'  => 'code',
            'other_key' => 'sid',
        ]);
    }


    protected function getSelectString()
    {
        return "
            SELECT
                {$this->getColumnString()},
                {$this->getJoinColumnString('folder', 'f')},
                {$this->getJoinColumnString('owner', 'o')}
            FROM {$this->database->quoteIdentifier($this->table)}
            LEFT JOIN {$this->getJoinSql('owner', '', 'o', true)}
            INNER JOIN {$this->getJoinSql('folder', '', 'f')}
        ";
    }


    /**
     * @param \PDOStatement $statement
     * @return GenericCollection|News[]
     */
    protected function hydrateSelect(\PDOStatement $statement)
    {
        $collection = $this->createCollection();

        /** @noinspection PhpAssignmentInConditionInspection */
        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
            $news = $this->hydrate($row);

            /** @var Folder\Model $folder */
            $folder = $this->hydrateJoin($row, 'folder', 'f');
            $news->setCategory($folder);

            /** @var User $user */
            $user = $this->hydrateJoin($row, 'owner', 'o');
            $news->setOwner($user);
            $collection[$news->getId()] = $news;
        }

        return $collection;
    }


    /**
     * Finds all non-archived news items in specified folder
     * Note that the user will see unpublished items that they own
     *
     * @param int      $folderId
     * @param User     $user
     * @param null|int $limit
     * @return GenericCollection|News[]
     */
    public function findForFolder($folderId, $user, $limit = null)
    {
        if (0 === $limit) {
            return $this->createCollection();
        }

        $pb = $this->getPredicateBuilder();
        $pb->equals('code', $folderId);
        $pb->greaterThanOrEqualTo('datetime_expires', \Alaress_Date_Format::now()->getDatabase());

        $subBuilder = $this->getPredicateBuilder();
        $subBuilder->lessThan('datetime_published', \Alaress_Date_Format::now()->getDatabase());
        $subBuilder->equals('owner', $user->getId());
        $pb->any($subBuilder);

        $options = [];
        if (null !== $limit) {
            $options['limit'] = $limit;
        }

        $options['order'] = ['datetime_published' => 'DESC'];

        return $this->findByPredicates($pb, $options);
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

        $sortBy  = isset($this->map[$sortProperty]) ? $this->map[$sortProperty]['field'] : $this->getIdentifierColumn();
        $sortDir = strtoupper($sortDirection) == 'DESC' ? 'DESC' : 'ASC';

        $offset = (int)$offset;
        $limit  = (int)$limit;

        $stmt = $this->database->prepare("
            SELECT
                {$this->getColumnString('n')},
                {$this->getJoinMapper('owner')->getColumnString('u')},
                {$this->getJoinMapper('folder')->getColumnString('c')},
                {$this->getJoinMapper('foldercart')->getColumnString('c')},
                MATCH(`heading`,`body`) AGAINST (:keyword) AS `score`
            FROM
                `{$this->getTable()}` `n`
                LEFT JOIN {$this->getJoinSql('owner',      'n', 'u')}
                INNER JOIN {$this->getJoinSql('folder',   'n', 'c')}
                LEFT JOIN {$this->getJoinSql('perms',      'n', 'p')}
                    AND p.gid = :role_id
                    AND p.type > :min_access
                LEFT JOIN {$this->getJoinSql('userAccess', 'n', 'a')}
                    AND a.service = :service
                    AND a.uid = :user_id
            WHERE
                (p.cid OR a.sid)
                AND MATCH(`heading`,`body`) AGAINST (:keyword) AS `score`
                AND (
                    n.datetime_published <= :publish_date
                    OR u.id = :user_id
                )
                AND n.deleted = FALSE
            ORDER BY $sortBy $sortDir
            LIMIT $offset, $limit
        ");

        $stmt->execute([
            'keyword'      => $keyword,
            'role_id'      => $user->getRole()->getId(),
            'min_access'   => UserAccess::ACCESS_READ,
            'service'      => UserAccess::SERVICE_HOMEPAGE,
            'user_id'      => $user->getId(),
            'publish_date' => date('Y-m-d H:i:s'),
        ]);

        $collection = $this->createCollection();

        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $collection->add(
                $this->hydrate($r, 'n')
                     ->setOwner($this->hydrateJoin($r, 'owner', 'u'))
                     ->setCategory($this->hydrateJoin($r, 'folder', 'c'))
            );
        }

        return $collection;
    }


    /**
     * @param     $categoryId
     * @param     $limit
     * @param int $offset
     * @param int $time
     * @return Collection|News[]
     */
    public function findForFeed($categoryId, $limit, $offset = 0, $time = 0)
    {
        $time   = date('Y-m-d H:i:s', $time ?: time());
        $limit  = (int)$limit;
        $offset = (int)$offset;

        $stmt = $this->database->prepare("
            SELECT
                {$this->getColumnString('n')},
                {$this->getJoinColumnString('folder', 'c')},
                {$this->getJoinColumnString('owner', 'u')}
            FROM
                `{$this->getTable()}` `n`
                INNER JOIN {$this->getJoinSql('folder', 'n', 'c')}
                INNER JOIN {$this->getJoinSql('owner', 'n', 'u', true)}
            WHERE
                `n`.`deleted` = FALSE
                AND `n`.`datetime_published` < :time
                AND `n`.`code` = :cat_id
            ORDER BY `n`.`datetime_published` DESC
            LIMIT $offset, $limit
        ");

        $stmt->execute([
            'time'   => $time,
            'cat_id' => $categoryId,
        ]);

        $c = $this->createCollection();
        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
            /** @var News $news */
            $news = $this->hydrate($r, 'n');
            $news->setOwner($this->hydrateJoin($r, 'owner', 'u'));
            $news->setCategory($this->hydrateJoin($r, 'folder', 'c'));
            $c->add($news);
        }
        return $c;
    }


    /**
     * Checks if there's any archived news items in a folder
     * Used to determine whether to show an archive link or not
     *
     * @param $folderId
     * @param $userId
     * @return bool
     */
    public function checkArchive($folderId, $userId)
    {
        $query = "
            SELECT    COUNT(*)
            FROM      `{$this->getTable()}` `n`
            WHERE     `n`.`code` = :catId 
                      AND (datetime_published <= CURRENT_TIMESTAMP() OR owner = :userId)
                      AND datetime_expires < CURRENT_TIMESTAMP()
                      AND `n`.deleted = 0
        ";

        $stmt = $this->database->prepare($query);

        $stmt->execute([
            'catId'  => $folderId,
            'userId' => $userId,
        ]);

        if ($stmt->fetchColumn() > 0) {
            return true;
        }

        return false;

    }

}
