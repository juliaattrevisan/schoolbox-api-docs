<?php
/**
 * Result
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Resource\File\Search;

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
			    {$mapper->getColumnString('f')},
			    {$mapper->getJoinColumnString('hash', 's')},
			    {$mapper->getJoinMapper('hash')->getJoinColumnString('allowedFileExtension', 'e')},
                {$mapper->getJoinColumnString('owner', 'o')},
				{$mapper->getJoinColumnString('category', 'c')},
				MATCH(f.realname, f.description, f.comment) AGAINST ({$sanitiser->getKeyword()}) AS `score`
			FROM
				`{$mapper->getTable()}` `f`
				LEFT JOIN {$mapper->getJoinSql('hash', 'f', 's')}
				LEFT JOIN {$mapper->getJoinMapper('hash')->getJoinSql('allowedFileExtension', 's', 'e', true)}
				LEFT JOIN {$mapper->getJoinSql('owner', 'f', 'o')}
				INNER JOIN {$mapper->getJoinSql('category', 'f', 'c')}
				LEFT JOIN `perms` p ON `f`.category = `p`.cid
					AND p.gid = :role_id
					AND p.type >= :min_access
				LEFT JOIN `userAccess` a ON `f`.category = `a`.sid
					AND a.service = :service
					AND a.uid = :user_id
			WHERE
				(p.cid OR a.sid)
				AND f.deleted_at IS NULL
				AND f.hidden IS FALSE
				AND MATCH(f.realname, f.description, f.comment) AGAINST ({$sanitiser->getKeyword()})
			ORDER BY {$sanitiser->getSortByWithAlias('f')} {$sanitiser->getSortDir()}
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
            $file = $mapper->hydrate($row, 'f');
            $storage = $mapper->hydrateJoin($row, 'hash', 's');
            $mime = $mapper->getJoinMapper('hash')->hydrateJoin($row, 'allowedFileExtension', 'e');
            $owner = $mapper->hydrateJoin($row, 'owner', 'o');
            $category = $mapper->hydrateJoin($row, 'category', 'c');

            $storage->setMimetype($mime);
            $file->setStorage($storage);
            $file->setOwner($owner)->setFolder($category);

            $collection->add($file);
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
				`{$mapper->getTable()}` `f`
				LEFT JOIN {$mapper->getJoinSql('hash', 'f', 's')}
				LEFT JOIN {$mapper->getJoinMapper('hash')->getJoinSql('allowedFileExtension', 's', 'e', true)}
				LEFT JOIN {$mapper->getJoinSql('owner', 'f', 'o')}
				INNER JOIN {$mapper->getJoinSql('category', 'f', 'c')}
				LEFT JOIN `perms` p ON `f`.category = `p`.cid
					AND p.gid = :role_id
					AND p.type >= :min_access
				LEFT JOIN `userAccess` a ON `f`.category = `a`.sid
					AND a.service = :service
					AND a.uid = :user_id
			WHERE
				(p.cid OR a.sid)
				AND f.deleted_at IS NULL
				AND f.hidden IS FALSE
				AND MATCH(f.realname, f.description, f.comment) AGAINST ({$sanitiser->getKeyword()})
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
