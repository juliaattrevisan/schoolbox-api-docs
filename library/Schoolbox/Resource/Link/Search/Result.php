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
				{$mapper->getJoinMapper('category')->getColumnString('c')},
				MATCH(`url`,`description`,`comment`) AGAINST ({$sanitiser->getKeyword()}) AS `score`
			FROM
				`{$mapper->getTable()}` `l`
				LEFT JOIN {$mapper->getJoinSql('user', 'l', 'u')}
				INNER JOIN {$mapper->getJoinSql('category', 'l', 'c')}
				LEFT JOIN {$mapper->getJoinSql('perms', 'l', 'p')}
					AND p.gid = :role_id
					AND p.type >= :min_access
				LEFT JOIN {$mapper->getJoinSql('userAccess', 'l', 'a')}
					AND a.service = :service
					AND a.uid = :user_id
			WHERE
				(p.cid OR a.sid)
				AND l.deleted_at IS NULL
				AND MATCH(`url`,`description`,`comment`) AGAINST ({$sanitiser->getKeyword()})
			ORDER BY {$sanitiser->getSortByWithAlias('l')} {$sanitiser->getSortDir()}
			LIMIT {$sanitiser->getOffset()}, {$sanitiser->getLimit()}
		";

        $stmt = $mapper->getDatabase()->prepare($query);
        $stmt->execute(array(
            'role_id' => $user->getRole()->getId(),
            'service' => UserAccess::SERVICE_HOMEPAGE,
            'min_access' => UserAccess::ACCESS_READ,
            'user_id' => $user->getId()
        ));

        $collection = $mapper->createCollection();

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $link = $mapper->hydrate($row, 'l');
            $owner = $mapper->hydrateJoin($row, 'user', 'u');
            $category = $mapper->hydrateJoin($row, 'category', 'c');

            $link->setOwner($owner)->setFolder($category);

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
			    COUNT(*) AS num
			FROM
				`{$mapper->getTable()}` `l`
				LEFT JOIN {$mapper->getJoinSql('user', 'l', 'u')}
				INNER JOIN {$mapper->getJoinSql('category', 'l', 'c')}
				LEFT JOIN {$mapper->getJoinSql('perms', 'l', 'p')}
					AND p.gid = :role_id
					AND p.type >= :min_access
				LEFT JOIN {$mapper->getJoinSql('userAccess', 'l', 'a')}
					AND a.service = :service
					AND a.uid = :user_id
			WHERE
				(p.cid OR a.sid)
				AND l.deleted_at IS NULL
				AND MATCH(`url`,`description`,`comment`) AGAINST ({$sanitiser->getKeyword()})
		";

        $stmt = $mapper->getDatabase()->prepare($query);
        $stmt->execute(array(
            'role_id' => $user->getRole()->getId(),
            'min_access' => UserAccess::ACCESS_READ,
            'service' => UserAccess::SERVICE_HOMEPAGE,
            'user_id' => $user->getId()
        ));

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return (int)$row['num'];
    }
}
