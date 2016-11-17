<?php
/**
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Menu\Top;

use Schoolbox\Mapper as BaseMapper;

class Mapper extends BaseMapper
{
    /**
     * Setup mapper
     */
    protected function mapping()
    {
        $this->hasTable('menu_top');
        $this->hasModel(Model::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('sort_order', self::TYPE_INTEGER);
        $this->uses('title', self::TYPE_STRING);
        $this->uses('text', self::TYPE_STRING);
        $this->uses('token', self::TYPE_STRING);
        $this->uses('icon', self::TYPE_STRING);
        $this->uses('url', self::TYPE_STRING);
        $this->uses('webmail', self::TYPE_BOOLEAN);
        $this->uses('target', self::TYPE_STRING);
        $this->uses('deleted_at', self::TYPE_DATETIME);
        $this->hasSoftDeleteDateField('deleted_at');
    }


    /**
     * @param \Schoolbox\User $user
     * @return \Schoolbox\Collection
     */
    public function findTabsForUser($user)
    {
        $service = HOMEPAGE_SERVICE;

        $campusList = array();
        foreach ($user->getCampusCollection() as $campus) {
            $campusList[] = $campus->getId();
        }

        $webmail = "";

        if (!$user->getWebmail()) {
            $webmail = "AND menu_top.webmail = 0";
        }

        $query = "
                SELECT DISTINCT menu_top.*
                FROM menu_top
                INNER JOIN menu_top_campus ON menu_top.id = menu_top_campus.menu_top_id
                INNER JOIN menu_top_role ON menu_top.id = menu_top_role.menu_top_id
                WHERE menu_top_campus.campus_id IN " . $this->getArrayPlaceholders($campusList, 'campus') . "
                AND menu_top_role.role_id = :roleId                
                {$webmail}
                ORDER BY sort_order ASC
        ";

        $params = $this->getArrayParameters($campusList, 'campus');
        $params['roleId'] = $user->getRole()->getId();

        $statement = $this->getDatabase()->prepare($query);
        $statement->execute($params);
        return $this->hydrateList($statement);
    }    
}
