<?php
/**
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Tour\Step\Tooltip;

use PDOStatement;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Tour\Step\Mapper as TourStepMapper;
use Schoolbox\Tour\Step\Model as TourStep;
use Schoolbox\User;
use Schoolbox\UserMapper;

class Mapper extends BaseMapper
{
    /**
     * @inheritdoc
     */
    protected function mapping()
    {
        $this->hasTable('tour_step_tooltip');
        $this->hasModel(Model::class);
        $this->uses('uuid', self::TYPE_STRING, self::COLUMN_IDENTIFIER);
        $this->joins('tour_step_uuid', TourStepMapper::class, 'uuid', null, null, 'tourStep', 'tourStep');
        $this->joins('author_user_id', UserMapper::class, 'id', null, null, 'author', 'author');
        $this->uses('title', self::TYPE_STRING);
        $this->uses('body', self::TYPE_STRING);
        $this->uses('selector', self::TYPE_STRING, null, 'javascriptSelector');
        $this->uses('css_class', self::TYPE_STRING);
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);
    }

    /**
     * @inheritdoc
     */
    protected function getSelectString()
    {
        return "
            SELECT      {$this->getColumnString()},
                        {$this->getJoinColumnString('author', 'a')},
                        {$this->getJoinColumnString('tourStep', 's')}
            FROM        {$this->getTable()}
            LEFT JOIN   {$this->getJoinSql('author', '', 'a', true)}
            LEFT JOIN   {$this->getJoinSql('tourStep', '', 's', true)}
        ";
    }

    /**
     * @inheritdoc
     */
    protected function hydrateSelect(PDOStatement $statement)
    {
        $collection = $this->createCollection();

        foreach ($statement as $row) {
            /** @var Model $model */
            $model = $this->hydrate($row);

            if ($model instanceof Model) {
                // Hydrate author

                /** @var UserMapper $authorUserMapper */
                $authorUserMapper = $this->getJoinMapper('author');

                /** @var User $author */
                $author = $authorUserMapper->hydrate($row, 'a');
                $model->setAuthor($author);

                // Hydrate tour step

                /** @var TourStepMapper $tourStepMapper */
                $tourStepMapper = $this->getJoinMapper('tourStep');

                /** @var TourStep $tourStep */
                $tourStep = $tourStepMapper->hydrate($row, 's');
                $model->setTourStep($tourStep);

                // Add hydrated record to results

                $collection->add($model);
            }
        }

        return $collection;
    }
}