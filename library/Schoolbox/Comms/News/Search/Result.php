<?php
/**
 * Result
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
namespace Schoolbox\Comms\News\Search;
use Schoolbox\Collection;
use Schoolbox\Mapper;
use Schoolbox\Resource\Search\MapperResult\Base;
use Schoolbox\Resource\Search\Sanitiser;
use Schoolbox\User;

class Result extends Base
{
    /**
     * Get all the search results
     *
     * @param User      $user
     * @param Mapper    $mapper
     * @param Sanitiser $sanitiser
     * @return Collection
     * @throws Mapper\DefinitionException
     * @throws Mapper\Exception
     */
    protected function executeSearch(User $user, Mapper $mapper, Sanitiser $sanitiser): Collection
    {
        $query = "
            SELECT
                {$mapper->getColumnString('n')},
                {$mapper->getJoinColumnString('author', 'a')},
                MATCH(n.title, n.blurb, n.body) AGAINST ({$sanitiser->getKeyword()}) AS `score`
            FROM
                `{$mapper->getTable()}` `n`
            INNER JOIN {$mapper->getJoinSql('author', 'n', 'a', true)}
            WHERE
                n.deleted_at IS NULL
                AND n.publish_at <= NOW()
                AND n.accepted_at <= NOW()
                AND MATCH(n.title, n.blurb, n.body) AGAINST ({$sanitiser->getKeyword()})
            ORDER BY {$sanitiser->getSortByWithAlias('n')} {$sanitiser->getSortDir()}
        ";

        $stmt = $mapper->getDatabase()->prepare($query);
        $stmt->execute();

        $collection = $mapper->createCollection();

        foreach ($this->buildSearchResult($stmt, $user, 'n.id', $sanitiser->getOffset(), $sanitiser->getLimit()) as $row) {
            $article = $mapper->hydrate($row, 'n');

            if ($article) {
                $author = $mapper->hydrateJoin($row, 'author', 'a');

                $article->setAuthor($author);

                $collection->add($article);
            }
        }

        return $collection;
    }

    /**
     * The total number of items found by the search
     *
     * @param User              $user
     * @param \Schoolbox\Mapper $mapper
     * @param Sanitiser         $sanitiser
     * @return int
     * @throws Mapper\DefinitionException
     */
    protected function executeSearchCount(User $user, Mapper $mapper, Sanitiser $sanitiser): int
    {
        $query = "
            SELECT
                n.id
            FROM
                `{$mapper->getTable()}` `n`
            INNER JOIN {$mapper->getJoinSql('author', 'n', 'a', true)}
            WHERE
                n.deleted_at IS NULL
                AND n.publish_at <= NOW()
                AND n.accepted_at <= NOW()
                AND MATCH(n.title, n.blurb, n.body) AGAINST ({$sanitiser->getKeyword()})
        ";

        $stmt = $mapper->getDatabase()->prepare($query);
        $stmt->execute();

        return $this->buildSearchCount($stmt, $user, 'id');
    }
}