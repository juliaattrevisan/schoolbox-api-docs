<?php
/**
 * Result
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Resource\Folder\Search;

use PDO;
use Schoolbox\Collection;
use Schoolbox\Mapper;
use Schoolbox\Resource\Search\Sanitiser;
use Schoolbox\User;
use Schoolbox\Resource\Search\MapperResult\Base;
use Schoolbox\Resource\Folder\Model as Folder;

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
            LEFT JOIN `perms` cp ON c.id = cp.cid
                AND `perms`.gid = :gid
                AND `perms`.type > 0
            LEFT JOIN `userAccess` ca ON c.id = ca.sid
                AND `userAccess`.service = 1
                AND `userAccess`.uid = :uid
            WHERE (`c`.`name` LIKE :likeKeyword OR `cc`.`code` LIKE :likeKeyword)
            AND   (cp.cid OR ca.sid)
			ORDER BY {$sanitiser->getSortByWithAlias('c')} {$sanitiser->getSortDir()}
			LIMIT {$sanitiser->getOffset()}, {$sanitiser->getLimit()}
        ";

        $statement = $mapper->getDatabase()->prepare($query);
        $statement->execute([
            'likeKeyword' => '%'.$mapper->getDatabase()->quoteForLike($sanitiser->getUnquotedKeyword()).'%',
            'gid'         => $user->getRole()->getId(),
            'uid'         => $user->getId(),
        ]);

        $results = new Collection();

        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
            $folder = $mapper->hydrate($row, 'c');

            /* Accumulate folder codes here
             */
            if (!($results->getOffset($folder->getId()) instanceof Folder)) {
                $results->setOFfset($folder->getId(), $folder);
            }

            /* Reassign var
             */
            $folder = $results->getOffset($folder->getId());

            $code = $code_mapper->hydrate($row, 'cc');

            if (strlen($code->getCode())) {
                $folder->getCodes()->add($code);
            }

            /* Accumulate homepage configs
             */
            $homepageConfig = $homepageConfig_mapper->hydrate($row, 'hp');
            $folder->setHomepage($homepageConfig);
        }

        /* What, it times out doing new Collection(array_values($results))
         */
        $results->reindex();

        return $results;
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
            FROM {$mapper->getTable()} c
            LEFT JOIN {$mapper->getJoinSql('codes', 'c', 'cc')}
            LEFT JOIN {$mapper->getJoinSql('homepages', 'c', 'hp')}
            LEFT JOIN `perms` cp ON c.id = cp.cid
                AND `perms`.gid = :gid
                AND `perms`.type > 0
            LEFT JOIN `userAccess` ca ON c.id = ca.sid
                AND `userAccess`.service = 1
                AND `userAccess`.uid = :uid
            WHERE (`c`.`name` LIKE :likeKeyword OR `cc`.`code` LIKE :likeKeyword)
            AND   (cp.cid OR ca.sid)
		";

        $stmt = $mapper->getDatabase()->prepare($query);
        $stmt->execute([
            'likeKeyword' => '%'.$mapper->getDatabase()->quoteForLike($sanitiser->getUnquotedKeyword()).'%',
            'gid'         => $user->getRole()->getId(),
            'uid'         => $user->getId(),
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return (int)$row['num'];
    }
}
