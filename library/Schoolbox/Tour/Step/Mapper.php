<?php
/**
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Tour\Step;

use PDOStatement;
use Schoolbox\Application\Exception\NotFound;
use Schoolbox\GenericCollection;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Tour\Mapper as TourMapper;
use Schoolbox\UserMapper;
use Schoolbox\Tour\Step\Type\Tooltip;
use Schoolbox\Tour\Step\Type\Modal;

/**
 * Class Mapper
 *
 * @method Model createModel($id = null)
 * @method Model hydrate(array $row, $alias = '', $callback = null)
 * @method Model findOneByColumnList($columnList, $order = [], $options = [])
 * @method Model findByColumnList($columnList, $order = [], $options = [])
 * @method GenericCollection | Model[] findByColumn($columnName, $columnValue, $options = array())
 * @package Schoolbox\Tour\Step
 */
class Mapper extends BaseMapper
{
    // Step type constants
    const TYPE_TOOLTIP = 'tooltip';
    const TYPE_MODAL = 'modal';

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
        $this->joinsReverseSingle('tour_step_uuid', Modal\Mapper::class, 'type', 'modalStep');
        $this->joinsReverseSingle('tour_step_uuid', Tooltip\Mapper::class, 'type', 'tooltipStep');
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
                        {$this->getTable()}.`type`,
                        {$this->getJoinColumnString('author', 'a')},
                        {$this->getJoinColumnString('tour', 't')},
                        {$this->getJoinColumnString('modalStep', 'ms')},
                        {$this->getJoinColumnString('tooltipStep', 'ts')}
            FROM        {$this->getTable()}
            INNER JOIN  {$this->getJoinSql('tour', '', 't')}
            LEFT JOIN   {$this->getJoinSql('modalStep','','ms')}
            LEFT JOIN   {$this->getJoinSql('tooltipStep','','ts')}            
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

        $hydrators = [
            'tooltip' => [Tooltip\Mapper::getInstance(), 'ts'],
            'modal' => [Modal\Mapper::getInstance(), 'ms']
        ];

        foreach ($statement as $row) {
            $model = $this->hydrate($row);

            if ($model instanceof Model) {
                $model->setAuthor($authorUserMapper->hydrate($row, 'a'));
                $model->setTour($tourMapper->hydrate($row, 't'));

                if (!array_key_exists($row['type'], $hydrators)) {
                    continue;
                }

                [ $mapper, $alias ] = $hydrators[$row['type']];
                $model->setType($mapper->hydrate($row, $alias));

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
                        `tour_step_tooltip`.`selector` as `tooltip_selector`,
                        `tour_step_tooltip`.`css_class` as `tooltip_css_class`,
                        `tour_step_event`.`selector` as `event_selector`,
                        `tour_step_event`.`event` as `event`,
                        `tour_step_event`.`insertion_point` as `event_insertion_point`
            FROM        {$this->getTable()}
            LEFT JOIN   {$this->getJoinSql('tour', '', 'tour', true)}
            LEFT JOIN   `tour_step_tooltip` ON `tour_step_tooltip`.`tour_step_uuid` = `tour_step`.`uuid`
            LEFT JOIN   `tour_step_modal` ON `tour_step_modal`.`tour_step_uuid` = `tour_step`.`uuid`
            LEFT JOIN   `tour_step_event` ON `tour_step_event`.`tour_step_uuid` = `tour_step`.`uuid` AND `tour_step_event`.`deleted_at` IS NULL
            WHERE       `tour`.`uuid` = :tourUuid AND
                        `tour`.`active` = 1 AND
                        `tour`.`deleted_at` IS NULL AND
                        `tour_step`.`deleted_at` IS NULL AND
                        `tour_step_tooltip`.`deleted_at` IS NULL 
            ORDER BY    `tour_step_event`.`order`
        ";
        $statement = $this->getDatabase()->prepare($query);
        $statement->execute([
            'tourUuid' => $tourUuid
        ]);

        // Retrieve all of the steps details in an array indexed by their UUID
        $stepsStorage = [];
        $stepCurrent = null;
        foreach ($statement as $row) {
            // Store the first step in the tour if we haven't yet
            if ($stepCurrent === null) {
                $stepCurrent = $row['tour.tour_step_uuid'];
            }

            if (!isset($stepsStorage[$row['uuid']])) {
                $stepsStorage[$row['uuid']] = [
                    'uuid'           => $row['uuid'],
                    'next_step_uuid' => $row['next_step_uuid'],
                    'type'           => Model::TYPE_TOOLTIP,
                    'body'           => $row['tooltip_body'],
                    'css_class'      => $row['tooltip_css_class'],
                    'selector'       => $row['tooltip_selector'],
                    'events'         => [] // Populated below
                ];
            }

            // Add the event (can be multiple)
            if ($row['event_selector'] && $row['event'] && $row['event_insertion_point']) {
                $stepsStorage[$row['uuid']]['events'][] = [
                    'selector'        => $row['event_selector'],
                    'event'           => $row['event'],
                    'insertion_point' => $row['event_insertion_point'],
                ];
            }
        }

        // Starting with the initial step of the tour, follow the trail of UUIDs to create a correctly ordered
        // array of steps for use in the javascript code
        $steps = [];
        $iterations = 0;
        $maxIterations = 50;
        while ($stepCurrent !== null && $iterations < $maxIterations) {
            $steps[] = $stepsStorage[$stepCurrent];
            // Move onto the next step in the sequence
            $stepCurrent = $stepsStorage[$stepCurrent]->getTourStep() ? $stepsStorage[$stepCurrent]->getTourStep()->getUuid() : null;
            $iterations++;
        }

        return $steps;
    }
}
