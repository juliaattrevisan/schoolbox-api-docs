<?php
/**
 * Mapper for e-portfolio blog comments
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Blog\Eportfolio\Comment;

use PDOStatement;
use Schoolbox\Blog\Eportfolio\Comment\Model as EportfolioBlogCommentModel;
use Schoolbox\Blog\Eportfolio\Post\Mapper as EportfolioBlogMapper;
use Schoolbox\Collection;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\User;
use Schoolbox\UserMapper;

class Mapper extends BaseMapper
{
    /**
     * Setup mapper
     */
    protected function mapping()
    {
        $this->hasTable('eportfolio_component_blog_comment');
        $this->hasModel(EportfolioBlogCommentModel::class);

        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->joins('blog_id', EportfolioBlogMapper::class, 'id', null, null, 'blog');
        $this->joins('owner_id', UserMapper::class, 'id', null, null, 'owner');
        $this->uses('body', self::TYPE_STRING);
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);
        $this->hasSoftDeleteDateField('deleted_at');

        $this->hasJoin(
            'owner',
            array(
                'mapper' => UserMapper::class,
                'this_key' => 'owner_id',
                'other_key' => 'id'
            )
        );

        $this->hasJoin(
            'blog',
            array(
                'mapper' => EportfolioBlogMapper::class,
                'this_key' => 'blog',
                'other_key' => 'id'
            )
        );
    }


    /**
     * This mapper's find* methods always hydrate the owner
     *
     * @return string
     */
    protected function getSelectString()
    {
        return "
            SELECT
                {$this->getColumnString()},
                {$this->getJoinColumnString('owner', 'u')}
            FROM {$this->database->quoteIdentifier($this->table)}
            INNER JOIN {$this->getJoinSql('owner', '', 'u',true)}
        ";
    }

    /**
     * Hydrate joins added in `getSelectString`.
     *
     * @param  PDOStatement $statement
     * @return Collection
     */
    protected function hydrateSelect(PDOStatement $statement)
    {
        $collection = $this->createCollection();
        foreach ($statement as $row) {
            $model = $this->hydrate($row);

            // set user and division only if retrieved properly
            $user = $this->hydrateJoin($row, 'owner', 'u');
            if ($user instanceof User) {
                $model->setOwner($user);
            }
            $collection->add($model);
        }
        return $collection;
    }

}
