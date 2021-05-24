<?php

/**
 * Mapper for learning areas.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Curriculum\LearningArea;

use Schoolbox\Collection;
use Schoolbox\Mapper as BaseMapper;

class Mapper extends BaseMapper
{
    public function mapping()
    {
        $this->hasTable('curriculum_learning_area');
        $this->hasModel(Model::class);
        $this->hasMap(
            array(
                'column'     => 'id',
                'property'   => 'id',
                'type'       => self::TYPE_INTEGER,
                'identifier' => true
            )
        );
        $this->hasMap(
            array(
                'column'   => 'curriculum_source_id',
                'property' => 'source',
                'type'     => self::TYPE_MODEL,
                'join'     => 'source',
                'join_def' => array(
                    'other_key' => 'id',
                    'mapper'    => '\\Schoolbox\\Curriculum\\Source\\Mapper',
                ),
            )
        );
        $this->hasMap(
            array(
                'column'   => 'name',
                'property' => 'name',
                'type'     => self::TYPE_STRING,
            )
        );
        $this->hasMap(
            array(
                'column'   => 'description',
                'property' => 'description',
                'type'     => self::TYPE_STRING
            )
        );
        $this->hasMap(
            array(
                'column'   => 'reference',
                'property' => 'reference',
                'type'     => self::TYPE_STRING
            )
        );
        $this->hasMap(
            array(
                'column'   => 'order',
                'property' => 'order',
                'type'     => self::TYPE_INTEGER
            )
        );

        // Created/Modified/Deleted
        $this->hasMap(
            array(
                'column'            => 'created_at',
                'property'          => 'createdAt',
                'type'              => self::TYPE_DATETIME,
                'created_timestamp' => true,
            )
        );
        $this->hasMap(
            array(
                'column'            => 'modified_at',
                'property'          => 'modifiedAt',
                'type'              => self::TYPE_DATETIME,
                'updated_timestamp' => true,
            )
        );
        $this->hasMap(
            array(
                'column'   => 'deleted_at',
                'property' => 'deletedAt',
                'type'     => self::TYPE_DATETIME,
            )
        );
        $this->hasSoftDeleteDateField('deleted_at');
    }

    /**
     * Gets all learning areas, along with their source.
     *
     * @return Collection|Model[]
     */
    public function findAllWithSource()
    {
        $query = "
            SELECT
                {$this->getColumnString('a')},
                {$this->getJoinColumnString('source', 's')}
            FROM `{$this->getTable()}` `a`
            INNER JOIN {$this->getJoinSql('source', 'a', 's')}
            WHERE `a`.`deleted_at` IS NULL
            ORDER BY `s`.`name` ASC, `a`.`order` ASC
        ";
        $statement = $this->getDatabase()->prepare($query);
        $statement->execute();
        return $this->hydrateList(
            $statement,
            'a',
            function ($mapper, $model, $row) {
                $model->setSource($mapper->hydrateJoin($row, 'source', 's'));
                return $model;
            }
        );
    }
}
