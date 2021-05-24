<?php

/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\User\Password\Reset;

use Schoolbox\Mapper as BaseMapper;
use Schoolbox\UserMapper;

/**
 * Mapper for password resets
 *
 * @package Schoolbox\User\Password\Reset
 * @method Model createModel($idValue = null)
 */
class Mapper extends BaseMapper
{
    /**
     * @inheritdoc
     */
    protected function mapping()
    {
        $this->hasTable('user_password_reset_request');
        $this->hasModel(Model::class);

        $this->uses('token', self::TYPE_STRING, self::COLUMN_IDENTIFIER);
        $this->uses('ip', self::TYPE_STRING);
        $this->uses('reset_time', self::TYPE_DATETIME);
        $this->uses('expiry', self::TYPE_DATETIME);
        $this->joins('user_id', UserMapper::class, 'id', null, null, null, 'user');
        $this->registerCallback(self::CALLBACK_PRESAVE, [$this, 'generateToken']);
    }

    /**
     * Adds user to all password resets retrieved.
     *
     * @return string
     */
    public function getSelectString()
    {
        return "
            SELECT
                {$this->getColumnString()},
                {$this->getJoinColumnString('user', 'u')}
            FROM {$this->getTable()}
            LEFT JOIN {$this->getJoinSql('user', null, 'u')}
        ";
    }

    /**
     * @param \PDOStatement $statement
     *
     * @return \Schoolbox\Collection
     */
    public function hydrateSelect(\PDOStatement $statement)
    {
        $collection = $this->createCollection();
        foreach ($statement as $row) {
            /** @var Model $model */
            $model = $this->hydrate($row);
            $model->setUser($this->hydrateJoin($row, 'user', 'u'));
            $collection->add($model);
        }
        return $collection;
    }

    /**
     * Pre-save callback - generates a unique token for this request.
     *
     * @param  Model $model
     * @return bool
     * @throws BaseMapper\Exception in the extraordinary case that a unique ID
     *                              could not be generated in 1000 attempts
     */
    public function generateToken(Model $model)
    {
        $i = 0;
        if (!empty($model->getToken())) {
            return true;
        }
        while (empty($model->getToken()) && $i < 1000) {
            $token = bin2hex(openssl_random_pseudo_bytes(32));
            if ($this->countByColumn('token', $token) === 0) {
                $model->setToken($token);
                return true;
            }
            $i++;
        }
        throw new BaseMapper\Exception('Could not generate a unique token after 1000 attempts!');
    }
}
