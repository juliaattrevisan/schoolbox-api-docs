<?php
/**
 * Result
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Resource\File\Search;

use PDO;
use Schoolbox\Collection;
use Schoolbox\Comms\News\Audience\ArticleResolver;
use Schoolbox\Mapper;
use Schoolbox\Resource\File\Model;
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
                {$mapper->getColumnString('f')},
                {$mapper->getJoinColumnString('hash', 's')},
                {$mapper->getJoinMapper('hash')->getJoinColumnString('allowedFileExtension', 'e')},
                {$mapper->getJoinColumnString('owner', 'o')},
                {$mapper->getJoinColumnString('modified_by', 'um')},
                {$mapper->getJoinColumnString('category', 'c')},
                MATCH(f.realname, f.description, f.comment) AGAINST ({$sanitiser->getKeyword()}) AS `score`
            FROM
                `{$mapper->getTable()}` `f`
                JOIN {$mapper->getJoinSql('hash', 'f', 's')}
                LEFT JOIN {$mapper->getJoinMapper('hash')->getJoinSql('allowedFileExtension', 's', 'e', true)}
                JOIN {$mapper->getJoinSql('owner', 'f', 'o', true)}
                LEFT JOIN {$mapper->getJoinSql('modified_by', 'f', 'um', true)}
                INNER JOIN {$mapper->getJoinSql('category', 'f', 'c')}
            WHERE
                f.deleted_at IS NULL
                AND f.hidden IS FALSE
                AND MATCH(f.realname, f.description, f.comment) AGAINST ({$sanitiser->getKeyword()})
            ORDER BY {$sanitiser->getSortByWithAlias('f')} {$sanitiser->getSortDir()}
            LIMIT 0,{$sanitiser->getResultLimit()}
        ";

        $stmt = $mapper->getDatabase()->prepare($query);
        $stmt->execute();

        $collection = $mapper->createCollection();

        foreach ($this->buildSearchResult($stmt, $user, 'c.id', $sanitiser->getOffset(), $sanitiser->getPageLimit()) as $row) {
            $file = $mapper->hydrate($row, 'f');
            $storage = $mapper->hydrateJoin($row, 'hash', 's');
            $mime = $mapper->getJoinMapper('hash')->hydrateJoin($row, 'allowedFileExtension', 'e');
            $owner = $mapper->hydrateJoin($row, 'owner', 'o');
            $modifiedBy = $mapper->hydrateJoin($row, 'modified_by', 'um');
            $folder = $mapper->hydrateJoin($row, 'category', 'c');


            $storage->setMimetype($mime);
            $file->setStorage($storage);
            $file->setOwner($owner)->setFolder($folder)->setModifiedBy($modifiedBy);

            $collection->add($file);
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
                `{$mapper->getTable()}` `f`
                JOIN {$mapper->getJoinSql('hash', 'f', 's')}
                LEFT JOIN {$mapper->getJoinMapper('hash')->getJoinSql('allowedFileExtension', 's', 'e', true)}
                JOIN {$mapper->getJoinSql('owner', 'f', 'o', true)}
                INNER JOIN {$mapper->getJoinSql('category', 'f', 'c')}
            WHERE
                f.deleted_at IS NULL
                AND f.hidden IS FALSE
                AND MATCH(f.realname, f.description, f.comment) AGAINST ({$sanitiser->getKeyword()})
            LIMIT 0, {$sanitiser->getResultLimit()}
        ";

        $stmt = $mapper->getDatabase()->prepare($query);
        $stmt->execute();

        return $this->buildSearchCount($stmt, $user, 'id');
    }
}
