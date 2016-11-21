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
        $this->uses('deleted_at', self::TYPE_DATETIME);
        $this->hasSoftDeleteDateField('deleted_at');
    }

    /**
     * @param \Schoolbox\User $user
     * @return \Schoolbox\Collection
     */
    public function findNodesForUser($user)
    {
        $campusList = array();
        foreach ($user->getCampusCollection() as $campus) {
            $campusList[] = $campus->getId();
        }

        $query = "
                SELECT DISTINCT menu_side.*
                FROM menu_side
                INNER JOIN menu_side_campus ON menu_side.id = menu_side_campus.menu_side_id
                INNER JOIN menu_side_role ON menu_side.id = menu_side_role.menu_side_id
                WHERE menu_side_campus.campus_id IN " . $this->getArrayPlaceholders($campusList, 'campus') . "
                AND menu_side_role.role_id = :roleId
                AND (menu_side.parent_id IN (SELECT id FROM menu_side) OR menu_side.parent_id = 0)
                ORDER BY sort_order ASC
        ";

        $params = $this->getArrayParameters($campusList, 'campus');
        $params['roleId'] = $user->getRole()->getId();

        $statement = $this->getDatabase()->prepare($query);
        $statement->execute($params);
        return $this->hydrateList($statement);
    }

    /**
     * @param \Schoolbox\User $user
     * @return \Schoolbox\Collection
     */
    public function findGroupsForUser($user) {

        $campusList = array();
        foreach ($user->getCampusCollection() as $campus) {
            $campusList[] = $campus->getId();
        }

        $query = "
            SELECT `ua`.*, `f`.name
            FROM userAccess `ua`
            JOIN `folder` AS `f` ON `f`.id = `ua`.sid AND `f`.`deleted_at` IS NULL
            WHERE `ua`.`service` = :service AND `ua`.`uid` = :userId
            ORDER BY `f`.`name`
        ";

        $params = [];
        $params['userId'] = $user->getId();
        $params['service'] = HOMEPAGE_SERVICE;

        $statement = $this->getDatabase()->prepare($query);
        $statement->execute($params);

        $groups = [];
        foreach ($statement as $row) {
            $groups[] = $row;
        }

        return $groups;
    }
}
