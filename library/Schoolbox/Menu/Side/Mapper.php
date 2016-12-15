<?php
/**
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Menu\Side;

use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Mapper\Exception as MapperException;

class Mapper extends BaseMapper
{
    /**
     * Setup mapper
     */
    protected function mapping()
    {
        $this->hasTable('menu_side');
        $this->hasModel(Model::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('parent_id', self::TYPE_INTEGER);
        $this->uses('sort_order', self::TYPE_INTEGER);
        $this->uses('title', self::TYPE_STRING);
        $this->uses('url', self::TYPE_STRING);
        $this->uses('target', self::TYPE_STRING);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);
    }

    /**
     * @param \Schoolbox\User $user
     * @return \Schoolbox\Collection
     * @throws MapperException
     */
    public function findNodesForUser($user)
    {
        $campusList = array();
        foreach ($user->getCampusCollection() as $campus) {
            $campusList[] = $campus->getId();
        }

        if(count($campusList) < 1) {
            throw new MapperException('User has no campus, and therefore has no menu');
        }

        $query = "
                SELECT DISTINCT {$this->getColumnString()}
                FROM menu_side
                INNER JOIN menu_side_campus ON menu_side.id = menu_side_campus.menu_side_id
                INNER JOIN menu_side_role ON menu_side.id = menu_side_role.menu_side_id
                WHERE menu_side_campus.campus_id IN " . $this->getPredicateBuilder()->getArrayPlaceholders($campusList, 'campus') . "
                AND menu_side_role.role_id = :roleId
                AND (menu_side.parent_id IN (SELECT id FROM menu_side) OR menu_side.parent_id = 0)
                ORDER BY sort_order ASC
        ";

        $params = $this->getPredicateBuilder()->getArrayParameters($campusList, 'campus');
        $params['roleId'] = $user->getRole()->getId();

        $statement = $this->getDatabase()->prepare($query);
        $statement->execute($params);
        return $this->hydrateList($statement);
    }

    /**
     * @param \Schoolbox\User $user
     * @return array
     */
    public function findGroupsForUser($user) {

        $query = "
            SELECT `ua`.sid, `f`.name
            FROM userAccess `ua`
            JOIN `folder` AS `f` ON `f`.id = `ua`.sid AND `f`.`deleted_at` IS NULL
            WHERE `ua`.`service` = :service AND `ua`.`uid` = :userId
            ORDER BY `f`.`name`
        ";

        $params = [];
        $params['userId'] = $user->getId();
        $params['service'] = UserAccess::SERVICE_HOMEPAGE;

        $statement = $this->getDatabase()->prepare($query);
        $statement->execute($params);

        return $statement->fetchAll();
    }
}
