<?php
/**
 * Mapper for curriculum nodes.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Curriculum\Node;

use Schoolbox\Collection;
use Schoolbox\Mapper as BaseMapper;

class Mapper extends BaseMapper implements BaseMapper\Searchable
{
    public function mapping()
    {
        $this->hasTable('curriculum_node');
        $this->hasModel('\\Schoolbox\\Curriculum\\Node\\Model');
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
                'column'   => 'curriculum_node_parent_id',
                'property' => 'parent',
                'type'     => self::TYPE_MODEL,
                'join'     => 'parent',
                'join_def' => array(
                    'other_key' => 'id',
                    'mapper'    => '\\Schoolbox\\Curriculum\\Node\\Mapper',
                ),
            )
        );
        $this->hasMap(
            array(
                'column'   => 'curriculum_learning_level_id',
                'property' => 'learningLevel',
                'type'     => self::TYPE_MODEL,
                'join'     => 'level',
                'join_def' => array(
                    'other_key' => 'id',
                    'mapper'    => '\\Schoolbox\\Curriculum\\LearningLevel\\Mapper',
                ),
            )
        );
        $this->hasMap(
            array(
                'column'   => 'curriculum_learning_area_id',
                'property' => 'learningArea',
                'type'     => self::TYPE_MODEL,
                'join'     => 'area',
                'join_def' => array(
                    'other_key' => 'id',
                    'mapper'    => '\\Schoolbox\\Curriculum\\LearningArea\\Mapper',
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
                'column'   => 'code',
                'property' => 'code',
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
        $this->registerCallback('postSoftDelete', array($this, 'deleteChildren'));
    }

    /**
     * Saves an entire tree of nodes, recursively.
     *
     * @param Model $model
     */
    public function saveTree(Model $model)
    {
        $this->save($model);
        $i = 0;
        foreach ($model->getChildren() as $child) {
            $child
                ->setLearningArea($model->getLearningArea())
                ->setLearningLevel($model->getLearningLevel())
                ->setParent($model->getId())
                ->setOrder($i++);
            $this->saveTree($child);
        }
    }

    /**
     * Soft-deletes all child nodes of the given node.
     *
     * @param Model $model
     */
    public function deleteChildren(Model $model)
    {
        // Soft-delete all children (recursively)
        $this->delete(
            $this->findByColumn(
                'curriculum_node_parent_id',
                $model->getId(),
                array(
                    'deleted' => true,
                )
            )
        );
    }

    /**
     * @param \Schoolbox\User $user
     * @param string          $keyword
     * @param int             $offset
     * @param int             $limit
     * @param string          $sortProperty
     * @param string          $sortDirection
     *
     * @return Collection|Model[]
     */
    public function search(
        $user,
        $keyword,
        $offset = 0,
        $limit = self::DEFAULT_LIMIT,
        $sortProperty = '',
        $sortDirection = 'DESC'
    ) {
        $query = "
            SELECT
                {$this->getColumnString('n')},
                {$this->getJoinColumnString('level', 'nl')},
                {$this->getJoinColumnString('level.source', 'nls')},
                {$this->getJoinColumnString('area', 'na')},
                {$this->getJoinColumnString('area.source', 'nas')}
            FROM {$this->getTable()} n
            INNER JOIN {$this->getJoinSql('level', 'n', 'nl')}
            INNER JOIN {$this->getJoinSql('level.source', 'nl', 'nls')}
            INNER JOIN {$this->getJoinSql('area', 'n', 'na')}
            INNER JOIN {$this->getJoinSql('area.source', 'na', 'nas')}
            WHERE n.code LIKE :codeLike
            OR (n.code != '' AND n.name LIKE :nameLike)
        ";
        $statement  = $this->getDatabase()->prepare($query);
        $quotedLike = $this->getDatabase()->quoteForLike($keyword);
        $statement->execute(
            array(
                'codeLike' => $quotedLike . '%',
                'nameLike' => '%' . $quotedLike . '%',
            )
        );
        return $this->hydrateList(
            $statement,
            'n',
            function ($mapper, $model, $row) {
                /** @var self $mapper */
                /** @var Model $model */
                $model
                    ->setLearningArea($mapper->hydrateJoin($row, 'area', 'na'))
                    ->setLearningLevel($mapper->hydrateJoin($row, 'level', 'nl'))
                ;
                $model->getLearningArea()
                    ->setSource(
                        $mapper->hydrateJoin($row, 'area.source', 'nas')
                    );
                $model->getLearningLevel()
                    ->setSource(
                        $mapper->hydrateJoin($row, 'level.source', 'nls')
                    );
                return $model;
            }
        );
    }
}
