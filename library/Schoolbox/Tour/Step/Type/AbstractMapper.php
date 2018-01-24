<?php
/**
 * Created by PhpStorm.
 * User: matt.schembri
 * Date: 16/01/18
 * Time: 8:25 AM
 */

namespace Schoolbox\Tour\Step\Type;

use PDOStatement;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Tour\Step\Mapper as TourStepMapper;
use Schoolbox\UserMapper;
use Schoolbox\Tour\Step\Model;

abstract class AbstractMapper extends BaseMapper
{
    /**
     * @inheritdoc
     */
    protected function mapping()
    {
        $this->uses('uuid', self::TYPE_STRING, self::COLUMN_IDENTIFIER_UUID);
        $this->joins('tour_step_uuid', TourStepMapper::class, 'uuid', null, null, 'tourStep', 'tourStep');
        $this->uses('body', self::TYPE_STRING);
        $this->uses('css_class', self::TYPE_STRING);
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
                        {$this->getJoinColumnString('tourStep', 's')},
            FROM        {$this->getTable()}
            INNER JOIN  {$this->getJoinSql('tourStep', '', 's')}
            LEFT JOIN   {$this->getJoinSql('author', '', 'a', true)}
        ";
    }

    /**
     * @inheritdoc
     */
    protected function hydrateSelect(PDOStatement $statement)
    {
        $collection = $this->createCollection();

        /** @var TourStepMapper $tourStepMapper */
        $tourStepMapper = $this->getJoinMapper('tourStep');

        foreach ($statement as $row) {
            $model = $this->hydrate($row);

            if ($model instanceof Model) {
                $model->setTourStep($tourStepMapper->hydrate($row, 's'));
                $collection->add($model);
            }
        }

        return $collection;
    }
}