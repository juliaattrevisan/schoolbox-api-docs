<?php

/**
 * Mapper for user JWT ID in Schoolbox.
 *
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\User\Token;

use PDOStatement;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\User;
use Schoolbox\UserMapper;

/**
 * Class Mapper
 * @package Schoolbox\User\Token
 * @method Model createModel($idValue = null)
 * @method Model findOneByColumn($columnName, $columnValue, array $options = [])
 */
class Mapper extends BaseMapper
{
    /**
     * {@inheritdoc}
     */
    public function mapping()
    {
        $this->hasTable('user_jwt_id');
        $this->hasModel(Model::class);
        $this->uses('jwt_id', self::TYPE_STRING, self::COLUMN_IDENTIFIER_UUID);
        $this->uses('expiry', self::TYPE_DATETIME);
        $this->uses('type', self::TYPE_STRING, self::COLUMN_ENUM, null, [Model::TYPE_API, Model::TYPE_QRCODE, null]);
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);
        $this->joins('user_id', UserMapper::class, 'id', null, null, 'user', 'user');
        $this->joins('created_by', UserMapper::class, 'id', null, null, 'createdBy', 'createdBy');
        $this->joins('updated_by', UserMapper::class, 'id', null, null, 'updatedBy', 'updatedBy');
        $this->joins('deleted_by', UserMapper::class, 'id', null, null, 'deletedBy', 'deletedBy');
    }

    /**
     * @return string
     * @throws \Schoolbox\Mapper\DefinitionException
     */
    protected function getSelectString(): string
    {
        return "
            SELECT
                {$this->getColumnString()},
                {$this->getJoinColumnString('createdBy', 'c')},
                {$this->getJoinColumnString('updatedBy', 'u')}
            FROM {$this->getDatabase()->getQuoter()->quoteIdentifier($this->getTable())}
            INNER JOIN {$this->getJoinSql('createdBy', '', 'c', true)}
            LEFT JOIN {$this->getJoinSql('updatedBy', '', 'u', true)}
        ";
    }

    /**
     * @inheritDoc
     */
    protected function hydrateSelect(PDOStatement $statement)
    {
        $collection = $this->createCollection();

        foreach ($statement as $row) {
            $model = $this->hydrate($row);

            $createdBy = $this->hydrateJoin($row, 'createdBy', 'c');
            if ($createdBy instanceof User) {
                $model->setCreatedBy($createdBy);
            }

            $updatedBy = $this->hydrateJoin($row, 'updatedBy', 'u');
            if ($updatedBy instanceof User) {
                $model->setUpdatedBy($updatedBy);
            }
            $collection->add($model);
        }

        return $collection;
    }

    /**
     * @param User                      $user
     * @param User                      $effector
     * @param \Alaress_Date_Format|null $expiry
     * @param string|null               $type
     * @return Model
     * @throws BaseMapper\Exception
     */
    public function generateForUser(User $user, User $effector, ?\Alaress_Date_Format $expiry = null, ?string $type = null): Model
    {
        // delete existing, if type is provided
        if ($type) {
            $existingJwtIds = $this->findByColumnList(['user_id' => $user->getId(), 'type' => $type]);
            /** @var Model $existing */
            foreach ($existingJwtIds as $existing) {
                $existing->setDeletedBy($effector)->setDeletedAt(\Alaress_Date_Format::now());
                $this->save($existing);
            }
        }
        // create new
        $userJwtId = $this->createModel()
                          ->setUser($user)
                          ->setCreatedBy($effector)
                          ->setExpiry($expiry)
                          ->setType($type);
        $this->save($userJwtId);
        return $userJwtId;
    }

    /**
     * @inheritDoc
     */
    protected function getDefaultOrderByParams(): array
    {
        return ['expiry' => 'ASC'];
    }
}
