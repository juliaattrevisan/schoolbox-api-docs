<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Tour\Audience;

use PDOStatement;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\RoleMapper;
use Schoolbox\Tour\Mapper as TourMapper;
use Schoolbox\UserMapper;

/**
 * Class Mapper
 * @package Schoolbox\Tour\Audience
 */
class Mapper extends BaseMapper
{
    // Table definition
    public const TABLE_NAME              = 'tour_audience_role';
    public const FIELD_MODERATOR_USER_ID = 'moderator_user_id';
    public const FIELD_TOUR_UUID         = 'tour_uuid';
    public const FIELD_ROLE_ID           = 'role_id';

    /**
     * @inheritdoc
     */
    protected function mapping()
    {
        $this->hasTable(self::TABLE_NAME);
        $this->hasModel(Model::class);
        $this->joins(self::FIELD_MODERATOR_USER_ID, UserMapper::class, 'id', null, null, 'moderator', 'moderator');
        $this->joins(self::FIELD_TOUR_UUID, TourMapper::class, 'uuid', null, null, 'tour', 'tour');
        $this->joins(self::FIELD_ROLE_ID, RoleMapper::class, 'id', null, null, 'role', 'role');
    }

    /**
     * @inheritDoc
     */
    protected function getSelectString()
    {
        return "
            SELECT      {$this->getColumnString()},
                        {$this->getJoinColumnString('moderator', 'm')},
                        {$this->getJoinColumnString('tour', 't')},
                        {$this->getJoinColumnString('role', 'r')}
            ";
    }

    /**
     * @inheritDoc
     */
    protected function getFromString()
    {
        return "
            FROM      {$this->table} 
            JOIN      {$this->getJoinSql('moderator', '', 'm')}
            JOIN      {$this->getJoinSql('tour', '', 't')}
            JOIN      {$this->getJoinSql('role','', 'r')}
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

            $moderator = $this->hydrateJoin($row, 'moderator', 'm');
            $model->setModerator($moderator);

            $tour = $this->hydrateJoin($row, 'tour', 't');
            $model->setTour($tour);

            $role = $this->hydrateJoin($row, 'role', 'r');
            $model->setRole($role);

            $collection->add($model);
        }

        return $collection;
    }
}
