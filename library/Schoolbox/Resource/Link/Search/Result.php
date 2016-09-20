<?php
/**
 * Result
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Resource\Link\Search;

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
				{$mapper->getColumnString('l')},
				{$mapper->getJoinMapper('user')->getColumnString('u')},
				{$mapper->getJoinMapper('folder')->getColumnString('c')},
				MATCH(`url`,`description`,`comment`) AGAINST ({$sanitiser->getKeyword()}) AS `score`
			FROM
				`{$mapper->getTable()}` `l`
				LEFT JOIN {$mapper->getJoinSql('user', 'l', 'u')}
				INNER JOIN {$mapper->getJoinSql('folder', 'l', 'c')}
			WHERE
				l.deleted_at IS NULL
				AND MATCH(`url`,`description`,`comment`) AGAINST ({$sanitiser->getKeyword()})
			ORDER BY {$sanitiser->getSortByWithAlias('l')} {$sanitiser->getSortDir()}
		";

        $stmt = $mapper->getDatabase()->prepare($query);
        $stmt->execute();

        $collection = $mapper->createCollection();

        foreach ($this->buildSearchResult($stmt, $user, 'c.id', $sanitiser->getOffset(), $sanitiser->getLimit()) as $row) {
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
     * @param User $user
     * @param \Schoolbox\Mapper $mapper
     * @param Sanitiser $sanitiser
     * @return int
     */
    protected function executeSearchCount(User $user, Mapper $mapper, Sanitiser $sanitiser)
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
		";

        $stmt = $mapper->getDatabase()->prepare($query);
        $stmt->execute();

        return $this->buildSearchCount($stmt, $user, 'category');
    }
}
