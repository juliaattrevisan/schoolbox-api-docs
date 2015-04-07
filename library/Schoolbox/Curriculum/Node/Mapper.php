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
     * @param string          $sortProperty  Ignored for this search for now
     * @param string          $sortDirection Ignored for this search for now
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
        $quotedLike = $this->getDatabase()->quoteForLike($keyword);
        $params     = [
            'codeLike' => $quotedLike . '%',
            'nameLike' => '%' . $quotedLike . '%',
        ];
        if ($limit) {
            $query .= ' LIMIT :limit';
        }
        if ($offset) {
            $query .= ' OFFSET :offset';
        }
        $statement = $this->getDatabase()->prepare($query);
        foreach ($params as $param => $value) {
            $statement->bindValue($param, $value);
        }
        if ($limit) {
            $statement->bindValue('limit', intval($limit), \PDO::PARAM_INT);
        }
        if ($offset) {
            $statement->bindValue('offset', intval($offset), \PDO::PARAM_INT);
        }

        $statement->execute();
        return $this->hydrateList(
            $statement,
            'n',
            function ($mapper, $model, $row) {
                /** @var self $mapper */
                /** @var Model $model */
                return $mapper->hydrateWithRelatedJoins($row, 'na', 'nas', 'nl', 'nls');
            }
        );
    }

    /**
     * Hydrates a curriculum node, with joins to area+source and level+source.
     *
     * @param array  $row
     * @param string $alias
     * @param string $areaAlias
     * @param string $areaSourceAlias
     * @param string $levelAlias
     * @param string $levelSourceAlias
     *
     * @return Model
     */
    public function hydrateWithRelatedJoins(
        $row,
        $alias = null,
        $areaAlias = 'cna',
        $areaSourceAlias = 'cnas',
        $levelAlias = 'cnl',
        $levelSourceAlias = 'cnls'
    ) {
        /** @var Model $curriculumNode */
        $curriculumNode = $this->hydrate($row, $alias);
        if ($curriculumNode->getId()) {
            $curriculumNode->setLearningArea(
                $this->getJoinMapper('area')->hydrate($row, $areaAlias)
                    ->setSource(
                        $this->getJoinMapper('area.source')->hydrate($row, $areaSourceAlias)
                    )
            );
            $curriculumNode->setLearningLevel(
                $this->getJoinMapper('level')->hydrate($row, $levelAlias)
                    ->setSource(
                        $this->getJoinMapper('level.source')->hydrate($row, $levelSourceAlias)
                    )
            );
        }
        return $curriculumNode;
    }
}
