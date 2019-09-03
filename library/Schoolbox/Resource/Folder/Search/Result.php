<?php
/**
 * Result
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Resource\Folder\Search;

use PDO;
use Schoolbox\Collection;
use Schoolbox\Comms\News\Audience\ArticleResolver;
use Schoolbox\Mapper;
use Schoolbox\Resource\Search\Sanitiser;
use Schoolbox\User;
use Schoolbox\Resource\Search\MapperResult\Base;
use Schoolbox\Resource\Folder;

class Result extends Base
{

    /**
     * Effectively no limit
     * This is used when a folder has multiple codes - the executeSearch()
     * didn't take this into account
     */
    const NO_LIMIT = 999;


    /**
     * Get all the search results
     *
     * @param ArticleResolver      $articleResolver
     * @param User                 $user
     * @param Folder\Mapper|Mapper $mapper
     * @param Sanitiser            $sanitiser
     * @return Collection
     * @throws Mapper\DefinitionException
     * @throws Mapper\Exception
     * @throws \UnexpectedValueException
     */
    protected function executeSearch(ArticleResolver $articleResolver, User $user, Mapper $mapper, Sanitiser $sanitiser)
    {
        /** @var Folder\Code\Mapper $code_mapper */
        $code_mapper = $mapper->getJoinMapper('codes');
        $homepageConfig_mapper = $mapper->getJoinMapper('homepages');

        // Score search: no scoring, just sort by ID
        $query = "
            SELECT
                {$mapper->getColumnString('c')},
                {$code_mapper->getColumnString('cc')},
                {$homepageConfig_mapper->getColumnString('hp')},
                c.id AS `score`
            FROM {$mapper->getTable()} c
            LEFT JOIN {$mapper->getJoinSql('codes', 'c', 'cc')}
            LEFT JOIN {$mapper->getJoinSql('homepages', 'c', 'hp')}
            WHERE (`c`.`name` LIKE :likeKeyword OR `cc`.`code` LIKE :likeKeyword)
            AND   `c`.`deleted_at` IS NULL
            ORDER BY {$sanitiser->getSortByWithAlias('c')} {$sanitiser->getSortDir()}
            LIMIT 0,{$sanitiser->getResultLimit()}
        ";

        $statement = $mapper->getDatabase()->prepare($query);
        $statement->execute([
            'likeKeyword' => '%'.$mapper->getDatabase()->escapeForLike($sanitiser->getUnquotedKeyword()).'%',
        ]);

        $results = $mapper->createCollection();

        // Handle offset properly:
        // any folders found after offset+limit get skipped,
        // any before offset get sliced off the start
        foreach ($this->buildSearchResult($statement, $user, 'c.id', 0, self::NO_LIMIT) as $row) {

            // Not at offset + limit results yet? Skip
            if (count($results) > ($sanitiser->getOffset() + $sanitiser->getPageLimit())) {
                break;
            }

            if ($results->offsetExists($row['c.id'])) {
                $folder = $results->getOffset($row['c.id']);
            } else {
                $folder = $mapper->hydrate($row, 'c');
                $folder->setCodes($code_mapper->createCollection());
                $results->setOffset($folder->getId(), $folder);
            }

            /* Reassign var
             */
            $folder = $results[$folder->getId()];

            $code = $code_mapper->hydrate($row, 'cc');

            if ($code) {
                $folder->getCodes()->setOffset($code->getCode(), $code);
            }

            /* Accumulate homepage configs
             */
            $homepageConfig = $homepageConfig_mapper->hydrate($row, 'hp');
            $folder->setHomepage($homepageConfig);
        }

        /* What, it times out doing new Collection(array_values($results))
         */
        $results->reindex();
        $results = $results->slice($sanitiser->getOffset(), $sanitiser->getPageLimit());

        return $results;
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
            SELECT DISTINCT id
            FROM {$mapper->getTable()} c
            LEFT JOIN {$mapper->getJoinSql('codes', 'c', 'cc')}
            LEFT JOIN {$mapper->getJoinSql('homepages', 'c', 'hp')}
            WHERE (`c`.`name` LIKE :likeKeyword OR `cc`.`code` LIKE :likeKeyword)
            AND   `c`.`deleted_at` IS NULL
            LIMIT 0,{$sanitiser->getResultLimit()}
        ";

        $stmt = $mapper->getDatabase()->prepare($query);
        $stmt->execute([
            'likeKeyword' => '%'.$mapper->getDatabase()->escapeForLike($sanitiser->getUnquotedKeyword()).'%',
        ]);

        return $this->buildSearchCount($stmt, $user, 'id');
    }
}
