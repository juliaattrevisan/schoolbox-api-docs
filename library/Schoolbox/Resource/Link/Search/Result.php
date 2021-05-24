<?php

/**
 * Result
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Resource\Link\Search;

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
                {$mapper->getColumnString('l')},
                {$mapper->getJoinMapper('user')->getColumnString('u')},
                {$mapper->getJoinMapper('folder')->getColumnString('c')},
                MATCH(`url`,`description`,`comment`) AGAINST ({$sanitiser->getKeyword()}) AS `score`
            FROM
                `{$mapper->getTable()}` `l`
                LEFT JOIN {$mapper->getJoinSql('user', 'l', 'u', true)}
                INNER JOIN {$mapper->getJoinSql('folder', 'l', 'c')}
            WHERE
                l.deleted_at IS NULL
                AND MATCH(`url`,`description`,`comment`) AGAINST ({$sanitiser->getKeyword()})
            ORDER BY {$sanitiser->getSortByWithAlias('l')} {$sanitiser->getSortDir()}
            LIMIT 0,{$sanitiser->getResultLimit()}
        ";

        $stmt = $mapper->getDatabase()->prepare($query);
        $stmt->execute();

        $collection = $mapper->createCollection();

        foreach ($this->buildSearchResult($stmt, $user, 'c.id', $sanitiser->getOffset(), $sanitiser->getPageLimit()) as $row) {
            $link = $mapper->hydrate($row, 'l');
            $owner = $mapper->hydrateJoin($row, 'user', 'u');
            $folder = $mapper->hydrateJoin($row, 'folder', 'c');

            $link->setOwner($owner)->setFolder($folder);

            $collection->add($link);
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
                category
            FROM
                `{$mapper->getTable()}` `l`
                LEFT JOIN {$mapper->getJoinSql('user', 'l', 'u')}
                INNER JOIN {$mapper->getJoinSql('folder', 'l', 'c')}
            WHERE
                l.deleted_at IS NULL
                AND MATCH(`url`,`description`,`comment`) AGAINST ({$sanitiser->getKeyword()})
            LIMIT 0,{$sanitiser->getResultLimit()}
        ";

        $stmt = $mapper->getDatabase()->prepare($query);
        $stmt->execute();

        return $this->buildSearchCount($stmt, $user, 'category');
    }
}
