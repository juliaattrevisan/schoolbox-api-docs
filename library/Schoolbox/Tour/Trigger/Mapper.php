<?php
/**
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Tour\Trigger;

use PDOStatement;
use Schoolbox\GenericCollection;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Tour\Mapper as TourMapper;
use Schoolbox\Tour\Model as Tour;
use Schoolbox\UserMapper;

/**
 * Class Mapper
 *
 * @method Model createModel($id = null)
 * @method Model hydrate(array $row, $alias = '', $callback = null)
 * @package Schoolbox\Tour\Trigger
 */
class Mapper extends BaseMapper
{
    /**
     * @inheritdoc
     */
    protected function mapping()
    {
        $this->hasTable('tour_trigger');
        $this->hasModel(Model::class);
        $this->uses('uuid', self::TYPE_STRING, self::COLUMN_IDENTIFIER);
        $this->joins('author_user_id', UserMapper::class, 'id', null, null, 'author', 'author');
        $this->joins('tour_uuid', TourMapper::class, 'uuid', null, null, 'tour', 'tour');
        $this->uses('selector', self::TYPE_STRING, null, 'javascriptSelector');
        $this->uses('event', self::TYPE_STRING, null, 'javascriptEvent');
        $this->uses('url_regex', self::TYPE_STRING);
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

        /** @var TourMapper $tourMapper */
        $tourMapper = $this->getJoinMapper('tour');

        /** @var UserMapper $authorUserMapper */
        $authorUserMapper = $this->getJoinMapper('author');

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
     * Find all triggers matching the supplied URL and tour
     *
     * @param Tour   $tour
     * @param string $url
     *
     * @return GenericCollection
     */
    public function findForUrl(Tour $tour, string $url): GenericCollection
    {
        $sql = "{$this->getSelectString()} WHERE `tour_uuid` = :tourUuid AND :url REGEXP `url_regex`";

        $statement = $this->getDatabase()->prepare($sql);
        $statement->execute([
            'url'      => $url,
            'tourUuid' => $tour->getUuid(),
        ]);

        return $this->hydrateSelect($statement);
    }
}