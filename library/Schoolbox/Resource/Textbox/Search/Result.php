<?php
/**
 * Result
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Resource\Textbox\Search;

use Schoolbox\Collection;
use Schoolbox\Comms\News\Audience\ArticleResolver;
use Schoolbox\Mapper;
use Schoolbox\Resource\Search\Sanitiser;
use Schoolbox\User;
use Schoolbox\Resource\Search\MapperResult\Base;

class Result extends Base
{

    /**
     * Get all the search results
     *
     * @param ArticleResolver $articleResolver
     * @param User            $user
     * @param Mapper          $mapper
     * @param Sanitiser       $sanitiser
     * @return Collection
     * @throws Mapper\DefinitionException
     * @throws Mapper\Exception
     */
    protected function executeSearch(ArticleResolver $articleResolver, User $user, Mapper $mapper, Sanitiser $sanitiser)
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
            LIMIT 0,{$sanitiser->getResultLimit()}
        ";

        $quotedLike = $mapper->getDatabase()->getQuoter()->escapeForLike($sanitiser->getUnquotedKeyword());

        $stmt = $mapper->getDatabase()->prepare($query);
        $stmt->execute(['%' . $quotedLike . '%']);

        $collection = $mapper->createCollection();

        foreach ($this->buildSearchResult($stmt, $user, 'c.id', $sanitiser->getOffset(), $sanitiser->getPageLimit()) as $row) {
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
     * @param ArticleResolver   $articleResolver
     * @param User              $user
     * @param \Schoolbox\Mapper $mapper
     * @param Sanitiser         $sanitiser
     * @return int
     * @throws Mapper\DefinitionException
     */
    protected function executeSearchCount(ArticleResolver $articleResolver, User $user, Mapper $mapper, Sanitiser $sanitiser)
    {
        $query = "
            SELECT
                c.id
            FROM
                `{$mapper->getTable()}` `t`
                INNER JOIN {$mapper->getJoinSql('category', 't', 'c')}
            WHERE
                t.deleted IS NULL AND
                (
                  MATCH(`heading`,`body`) AGAINST ({$sanitiser->getKeyword()}) OR
                  `heading` LIKE ?
                )
            LIMIT 0,{$sanitiser->getResultLimit()}
        ";

        $quotedLike = $mapper->getDatabase()->getQuoter()->escapeForLike($sanitiser->getUnquotedKeyword());

        $stmt = $mapper->getDatabase()->prepare($query);
        $stmt->execute(['%' . $quotedLike . '%']);

        return $this->buildSearchCount($stmt, $user, 'id');
    }
}
