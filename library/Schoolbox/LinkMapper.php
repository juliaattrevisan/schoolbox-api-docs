<?php
/**
 * LinkMapper
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox;

use Schoolbox\Mapper;
use Schoolbox\Resource\Category;
use Schoolbox\UserMapper;
use Schoolbox\Collection;

class LinkMapper extends Mapper
{

    /**
     * Mapping
     *
     * @return Bool|void
     */
    protected function mapping()
    {
        $this->hasTable('link');
        $this->hasModel('\\Schoolbox\\Link');

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
                'property' => 'categoryId',
                'type'     => self::TYPE_MODEL,
                'join'     => 'category',
            )
        );

        $this->hasMap(
            array(
                'column'   => 'owner',
                'property' => 'ownerId',
                'type'     => self::TYPE_MODEL,
                'join'     => 'owner',
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
                'column'   => 'submitted',
                'property' => 'submittedAt',
                'type'     => self::TYPE_DATETIME,
            )
        );

        $this->hasMap(
            array(
                'column'   => 'modified',
                'property' => 'modifiedAt',
                'type'     => self::TYPE_DATETIME,
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
                'column'   => 'comment',
                'property' => 'comment',
                'type'     => self::TYPE_STRING,
            )
        );

        $this->hasMap(
            array(
                'column'   => 'hidden',
                'property' => 'hidden',
                'type'     => self::TYPE_INTEGER,
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
            'category', array(
                'multiple'  => false,
                'mapper'    => '\\Schoolbox\\Resource\\CategoryMapper',
                'this_key'  => 'category',
                'other_key' => 'id',
            )
        );

        $this->hasJoin(
            'owner', array(
                'multiple'  => false,
                'mapper'    => '\\Schoolbox\\UserMapper',
                'this_key'  => 'owner',
                'other_key' => 'id',
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
        $userMapper     = $this->getJoinMapper('owner');

        $query = "
            SELECT
            	{$this->getColumnString('l')},
            	{$categoryMapper->getColumnString('c')},
            	{$userMapper->getColumnString('o')}
            FROM `{$this->table}` l
            LEFT JOIN {$this->getJoinSql('category', 'l', 'c')}
            LEFT JOIN {$this->getJoinSql('owner', 'l', 'o')}
            WHERE l.category = :categoryId
            AND l.deleted_at IS NULL
		";

        $statement = $this->database->prepare($query);
        $statement->bindParam(':categoryId', $category->getId());
        $statement->execute();

        /* Manually construct hierarchy of blog items and their comments
         */
        $collection = new Collection();

        foreach ($statement as $row) {
            $link = $this->hydrate($row, 'l');

            $link->setCategoryId($categoryMapper->hydrate($row, 'c'));
            $link->setOwnerId($userMapper->hydrate($row, 'o'));

            $collection->add($link);
        }

        return $collection;
    }
}