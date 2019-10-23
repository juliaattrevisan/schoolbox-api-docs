<?php
/**
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Menu\Top;

use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Mapper\Exception as MapperException;

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
        $this->uses('webmail', self::TYPE_BOOLEAN);
        $this->uses('title', self::TYPE_STRING);
        $this->uses('text', self::TYPE_STRING);
        $this->uses('token', self::TYPE_STRING);
        $this->uses('icon', self::TYPE_STRING);
        $this->uses('url', self::TYPE_STRING);
        $this->uses('target', self::TYPE_STRING);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);
    }


    /**
     * @param \Schoolbox\User $user
     * @return \Schoolbox\Collection
     * @throws MapperException
     */
    public function findTabsForUser($user)
    {
        if (!$user->getCampusCollection()) {
            throw new MapperException('User has no campus, and therefore has no menu');
        }

        $campusList = [];
        foreach ($user->getCampusCollection() as $campus) {
            $campusList[] = $campus->getId();
        }

        if (count($campusList) < 1) {
            throw new MapperException('User has no campus, and therefore has no menu');
        }

        $webmail = '';

        if (!$user->getWebmail()) {
            $webmail = "AND menu_top.webmail = 0";
        }

        $eportfolio = '';

        if (!$user->getEportfolioEnable()) {
            $eportfolio = "AND menu_top.text NOT like 'ePortfolio'";
        }

        $query = "
                SELECT DISTINCT {$this->getColumnString()}
                FROM menu_top
                INNER JOIN menu_top_campus ON menu_top.id = menu_top_campus.menu_top_id
                INNER JOIN menu_top_role ON menu_top.id = menu_top_role.menu_top_id
                WHERE menu_top_campus.campus_id IN " . $this->getPredicateBuilder()->getArrayPlaceholders($campusList, 'campus') . "
                AND menu_top_role.role_id = :roleId    
                AND menu_top.deleted_at IS NULL
                {$webmail}
                {$eportfolio}
                ORDER BY sort_order ASC
        ";

        $params = $this->getPredicateBuilder()->getArrayParameters($campusList, 'campus');
        $params['roleId'] = $user->getRole()->getId();

        $statement = $this->getDatabase()->prepare($query);
        $statement->execute($params);
        return $this->hydrateList($statement);
    }
}
