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
use Schoolbox\Mapper;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\Search\Sanitiser;
use Schoolbox\User;
use Schoolbox\Resource\Search\MapperResult\Base;
use Traversable;

class Result extends Base
{
    /**
     * Get all the search results
     *
     * @param User            $user
     * @param Mapper          $mapper
     * @param Sanitiser       $sanitiser
     * @return Collection
     * @throws Mapper\Exception
     */
    protected function executeSearch(User $user, Mapper $mapper, Sanitiser $sanitiser): Collection
    {
        /** @var \Schoolbox\Resource\Textbox\Mapper $mapper */

        $query = "
            SELECT
                {$mapper->getColumnString('t')},
                {$mapper->getJoinMapper('folder')->getColumnString('f')}
            FROM
                `{$mapper->getTable()}` `t`
                INNER JOIN {$mapper->getJoinSql('folder', 't', 'f')}
            WHERE
                t.deleted_at IS NULL AND
                `heading` LIKE :like
            ORDER BY {$sanitiser->getSortByWithAlias('t')} {$sanitiser->getSortDir()}
            LIMIT 0,{$sanitiser->getResultLimit()}
        ";

        $quotedLike = $mapper->getDatabase()->getQuoter()->escapeForLike($sanitiser->getUnquotedKeyword());

        $stmt = $mapper->getDatabase()->prepare($query);
        $stmt->setFetchMode(\PDO::FETCH_ASSOC);
        $stmt->execute(['like' => '%' . $quotedLike . '%']);

        $collection = $mapper->createCollection();

        foreach ($this->buildSearchResult($stmt, $user, 'f.id', $sanitiser->getOffset(), $sanitiser->getPageLimit()) as $row) {
            $textbox  = $mapper->hydrate($row, 't');
            $folder = $mapper->hydrateJoin($row, 'folder', 'f');

            $textbox->setFolder($folder);

            $collection->add($textbox);
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
    protected function executeSearchCount(User $user, Mapper $mapper, Sanitiser $sanitiser)
    {
        /** @var \Schoolbox\Resource\Textbox\Mapper $mapper */

        $query = "
            SELECT
                f.id
            FROM
                `{$mapper->getTable()}` `t`
                INNER JOIN {$mapper->getJoinSql('folder', 't', 'f')}
            WHERE
                t.deleted_at IS NULL AND
                `heading` LIKE :like
            LIMIT 0,{$sanitiser->getResultLimit()}
        ";

        $quotedLike = $mapper->getDatabase()->getQuoter()->escapeForLike($sanitiser->getUnquotedKeyword());

        $stmt = $mapper->getDatabase()->prepare($query);
        $stmt->execute(['like' => '%' . $quotedLike . '%']);

        return $this->buildSearchCount($stmt, $user, 'id');
    }

    /**
     * @inheritDoc
     */
    public function buildFilteredResult(Traversable $rawResult, User $user, $folderColumn)
    {
        return new \CallbackFilterIterator(
            new \IteratorIterator($rawResult),
            function ($row) use ($user, $folderColumn) {
                if (
                    isset($row['component_value'])
                    && (int)$row['component_value']
                    && ((int)$row['collapse'] === 0 || (int)$row['show_title'] === 1)
                ) {
                    // if component exists and it's not hidden, read only user can see this
                    return $this->accessor->getFolderAccess($user, (int)$row[$folderColumn]) > UserAccess::ACCESS_DENY;
                } else {
                    // if component doesn't exist, only write+ user can see this
                    return $this->accessor->getFolderAccess($user, (int)$row[$folderColumn]) > UserAccess::ACCESS_READ;
                }
            }
        );
    }
}
