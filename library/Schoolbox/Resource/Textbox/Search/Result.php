<?php
/**
 * Result
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Resource\Textbox\Search;

use PDO;
use Schoolbox\Collection;
use Schoolbox\Mapper;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\Search\Sanitiser;
use Schoolbox\User;
use Schoolbox\Resource\Search\MapperResult\Base;

class Result extends Base
{

    /**
     * Get all the search results
     *
     * @param User $user
     * @param Mapper $mapper
     * @param Sanitiser $sanitiser
     * @return Collection
     */
    protected function executeSearch(User $user, Mapper $mapper, Sanitiser $sanitiser)
    {
        $query = "
            SELECT
                {$mapper->getColumnString('t')},
                {$mapper->getJoinMapper('category')->getColumnString('c')},
                MATCH(`heading`,`body`) AGAINST ({$sanitiser->getKeyword()}) AS `score`
            FROM
                `{$mapper->getTable()}` `t`
                INNER JOIN {$mapper->getJoinSql('category', 't', 'c')}
            WHERE
                t.deleted IS NULL AND 
                (
                  MATCH(`heading`,`body`) AGAINST ({$sanitiser->getKeyword()}) OR
                  `heading` LIKE ?
                )
            ORDER BY {$sanitiser->getSortByWithAlias('t')} {$sanitiser->getSortDir()}
        ";

        $quotedLike = $mapper->getDatabase()->quoteForLike($sanitiser->getUnquotedKeyword());

        $stmt = $mapper->getDatabase()->prepare($query);
        $stmt->execute(['%' . $quotedLike . '%']);

        $collection = $mapper->createCollection();

        foreach ($this->buildSearchResult($stmt, $user, 'c.id', $sanitiser->getOffset(), $sanitiser->getLimit()) as $row) {
            $textbox = $mapper->hydrate($row, 't');
            $category = $mapper->hydrateJoin($row, 'category', 'c');

            $textbox->setCategory($category);

            $collection->add($textbox);
        }

        return $collection;
    }

    /**
     * The total number of items found by the search
     *
     * @param User $user
     * @param \Schoolbox\Mapper $mapper
     * @param Sanitiser $sanitiser
     * @return int
     */
    protected function executeSearchCount(User $user, Mapper $mapper, Sanitiser $sanitiser)
    {
        $query = "
            SELECT
                c.id
            FROM
                `{$mapper->getTable()}` `t`
                INNER JOIN {$mapper->getJoinSql('category', 't', 'c')}
            WHERE
                t.deleted IS NULL
                AND MATCH(`heading`,`body`) AGAINST ({$sanitiser->getKeyword()})
        ";

        $stmt = $mapper->getDatabase()->prepare($query);
        $stmt->execute();

        return $this->buildSearchCount($stmt, $user, 'id');
    }
}
