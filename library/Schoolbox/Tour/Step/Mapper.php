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
        $this->uses('uuid', self::TYPE_STRING, self::COLUMN_IDENTIFIER_UUID);
        $this->joins('next_step_uuid', Mapper::class, 'uuid', null, null, 'tourStep', 'tourStep');
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

    /**
     * Return an array of steps ready for injection into Javascript code
     *
     * @param string $tourUuid
     *
     * @return array
     */
    public function getStepArray(string $tourUuid): array
    {
        $query = "
            SELECT      {$this->getColumnString()},
                        {$this->getJoinColumnString('tour', 'tour')},
                        `tour_step_tooltip`.`uuid` as `tooltip_uuid`,
                        `tour_step_tooltip`.`body` as `tooltip_body`,
                        `tour_step_tooltip`.`selector` as `tooltip_selector`
            FROM        {$this->getTable('step')}
            LEFT JOIN   {$this->getJoinSql('tour', '', 'tour', true)}
            LEFT JOIN   `tour_step_tooltip` ON `tour_step_tooltip`.`tour_step_uuid` = `tour_step`.`uuid`
            WHERE       `tour`.`uuid` = :tourUuid AND
                        `tour`.`active` = 1 AND
                        `tour`.`deleted_at` IS NULL AND
                        `tour_step`.`deleted_at` IS NULL AND
                        `tour_step_tooltip`.`deleted_at` IS NULL 
        ";
        $statement = $this->getDatabase()->prepare($query);
        $statement->execute([
            'tourUuid' => $tourUuid
        ]);
        // Storage for current step in the tour
        $stepCurrent = null;
        // Retrieve all of the steps details in an array indexed by their UUID
        $stepsStorage = [];
        foreach ($statement as $row) {
            // Store the first step in the tour if we haven't yep
            if ($stepCurrent === null) {
                $stepCurrent = $row['tour.tour_step_uuid'];
            }
            switch ($row['type']) {
                case Model::TYPE_TOOLTIP:
                    $stepsStorage[$row['uuid']] = [
                        'uuid' => $row['uuid'],
                        'next_step_uuid' => $row['next_step_uuid'],
                        'type' => Model::TYPE_TOOLTIP,
                        'body' => $row['tooltip_body'],
                        'css_class' => $row['css_class'],
                        'selector' => $row['tooltip_selector'],
                    ];
                    break;
            }
        }
        // Starting with the initial step of the tour, follow the trail of UUIDs to create a correctly ordered
        // array of steps for use in the javascript code
        $steps = [];
        while ($stepCurrent !== null) {
            $steps[] = $stepsStorage[$stepCurrent];
            // Move onto the next step in the sequence
            $stepCurrent = $stepsStorage[$stepCurrent]['next_step_uuid'];
        }
        return $steps;
    }
}
