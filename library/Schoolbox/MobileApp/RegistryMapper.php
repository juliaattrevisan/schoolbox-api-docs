<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\MobileApp;


use PDOStatement;
use Schoolbox\Collection;
use Schoolbox\Mapper;
use Schoolbox\UserMapper;

/**
 * Class RegistryMapper
 *
 * @package Schoolbox\MobileApp
 * @method Collection|Registry[] findAll($options = [])
 * @method Registry hydrate(array $row, $alias = '', $callback = null)
 */
class RegistryMapper extends Mapper
{

    /**
     * @return void
     */
    protected function mapping()
    {
        $this->hasTable('mobile_app_registry');

        $this->hasModel(Registry::class);

        $this->joins('user_id', UserMapper::class);

        $this->uses('app_token',  self::TYPE_STRING, null, 'token');
        $this->uses('device_id',  self::TYPE_STRING);
        $this->uses('provider',   self::TYPE_STRING);
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('touched_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
    }


    /**
     * @param int    $userId
     * @param string $token
     * @param string $provider
     * @return Registry
     * @throws Mapper\Exception
     */
    public function findById($userId, $token = null, $provider = null)
    {
        return $this->findOneByColumnList(array(
            'user_id'   => $userId,
            'app_token' => $token,
            'provider'  => $provider,
        ));
    }


    /**
     * Return the first part of the string required to select from this mapper
     *
     * @return string
     */
    protected function getSelectString()
    {
        return "
            SELECT {$this->getColumnString()},
            {$this->getJoinColumnString('user_id', 'u')}
        ";
    }


    /**
     * It seems we're going down this road...
     * To prevent cataclysmic backward-incompatibility, in the core, the FROM is absorbed into the SELECT... for now...
     *
     * @return string
     */
    protected function getFromString()
    {
        return "
            FROM {$this->table}
            LEFT JOIN {$this->getJoinSql('user_id', '', 'u', true)}
        ";
    }


    /**
     * @param PDOStatement $statement
     * @return Collection
     */
    protected function hydrateSelect(PDOStatement $statement)
    {
        $c = new Collection();

        while ($r = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $reg = $this->hydrate($r);
            $reg->setUser($this->hydrateJoin($r, 'user_id', 'u'));

            $c->add($reg);
        }

        return $c;
    }


}
