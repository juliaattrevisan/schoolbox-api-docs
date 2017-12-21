<?php
/**
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Tour\Step;

use PDOStatement;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Tour\Mapper as TourMapper;
use Schoolbox\UserMapper;

/**
 * Class Mapper
 *
 * @method Model createModel($id = null)
 * @method Model hydrate(array $row, $alias = '', $callback = null)
 * @package Schoolbox\Tour\Step
 */
class Mapper extends BaseMapper
{
    /**
     * @inheritdoc
     */
    protected function mapping()
    {
        $this->hasTable('tour_step');
        $this->hasModel(Model::class);
        $this->uses('uuid', self::TYPE_STRING, self::COLUMN_IDENTIFIER);
        $this->joins('tour_uuid', TourMapper::class, 'uuid', null, null, 'tour', 'tour');
        $this->joins('author_user_id', UserMapper::class, 'id', null, null, 'author', 'author');
        $this->uses('type', self::TYPE_STRING);
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);

        $this->registerCallback(self::CALLBACK_PRESAVE, function (Model $model) {
            return $this->preSave($model);
        });
    }

    /**
     * @param Model $model
     * @return bool
     */
    private function preSave(Model $model)
    {
        if ($model->getUuid()) {
            // already got an ID; do nothing

            // TODO consider validating that it's *actually* a UUID
            return true;
        }

        // TODO find a way to do this without the pre-query
        $model->setUuid($this->database->query('SELECT UUID()')->fetchColumn());

        return true;
    }

    /**
     * @inheritdoc
     */
    protected function getSelectString()
    {
        return "
            SELECT      {$this->getColumnString()},
                        {$this->getJoinColumnString('author', 'a')},
                        {$this->getJoinColumnString('tour', 't')}
            FROM        {$this->getTable()}
            INNER JOIN  {$this->getJoinSql('tour', '', 't')}
            LEFT JOIN   {$this->getJoinSql('author', '', 'a', true)}
        ";
    }

    /**
     * @inheritdoc
     */
    protected function hydrateSelect(PDOStatement $statement)
    {
        $collection = $this->createCollection();

        /** @var UserMapper $authorUserMapper */
        $authorUserMapper = $this->getJoinMapper('author');

        /** @var TourMapper $tourMapper */
        $tourMapper = $this->getJoinMapper('tour');

        foreach ($statement as $row) {
            $model = $this->hydrate($row);

            if ($model instanceof Model) {
                $model->setAuthor($authorUserMapper->hydrate($row, 'a'));
                $model->setTour($tourMapper->hydrate($row, 't'));
                $collection->add($model);
            }
        }

        return $collection;
    }
}