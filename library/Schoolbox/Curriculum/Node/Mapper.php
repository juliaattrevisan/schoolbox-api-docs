<?php
/**
 * Mapper for curriculum nodes.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Curriculum\Node;

use Schoolbox\Mapper as BaseMapper;

class Mapper extends BaseMapper
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
}
