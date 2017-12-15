<?php
/**
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Tour\Step;

use PDOStatement;
use Schoolbox\GenericCollection;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Tour\Mapper as TourMapper;
use Schoolbox\Tour\Model as Tour;
use Schoolbox\User;
use Schoolbox\UserMapper;

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

                // Hydrate tour

                /** @var TourMapper $tourMapper */
                $tourMapper = $this->getJoinMapper('tour');

                /** @var Tour $tour */
                $tour = $tourMapper->hydrate($row, 't');
                $model->setTour($tour);

                // Add hydrated record to results

                $collection->add($model);
            }
        }

        return $collection;
    }
}