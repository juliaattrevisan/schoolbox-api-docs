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
use Schoolbox\Curriculum\LearningArea;
use Schoolbox\Curriculum\LearningLevel;
use Schoolbox\Mapper as BaseMapper;

class Mapper extends BaseMapper implements BaseMapper\Searchable
{
    public function mapping()
    {
        $this->hasTable('curriculum_node');
        $this->hasModel(Model::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->joins('curriculum_node_parent_id', self::class, 'id', null, null, 'parent', 'parent');
        $this->joins('curriculum_learning_level_id', LearningLevel\Mapper::class, 'id', null, null, 'learningLevel', 'level');
        $this->joins('curriculum_learning_area_id', LearningArea\Mapper::class, 'id', null, null, 'learningArea', 'area');
        $this->uses('name', self::TYPE_STRING);
        $this->uses('code', self::TYPE_STRING);
        $this->uses('order', self::TYPE_INTEGER);
        $this->uses('description', self::TYPE_STRING);
        $this->uses('reference', self::TYPE_STRING);
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('modified_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);
        $this->registerCallback(self::CALLBACK_POSTSOFTDELETE, array($this, 'deleteChildren'));
    }

    /**
     * Saves an entire tree of nodes, recursively.
     *
     * @param Model $model
     */
    public function saveTree(Model $model)
    {
        try {
            //Avoid saving duplicates if possible
            $result = $this->findOneByColumn('reference', $model->getReference(), ["deleted" => false]);
            $model->setId($result->getId());
        }
        catch(\Schoolbox\Mapper\Exception $e) {
            $this->save($model);
        }

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
                {$this->getColumnString('n')}
            FROM {$this->getTable()} n
            WHERE n.deleted_at IS NULL
            AND   (n.code LIKE :codeLike OR (n.code != '' AND n.name LIKE :nameLike))
        ";
        $quotedLike = $this->getDatabase()->escapeForLike($keyword);
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
            $statement->bindValue('limit', (int) $limit, \PDO::PARAM_INT);
        }
        if ($offset) {
            $statement->bindValue('offset', (int) $offset, \PDO::PARAM_INT);
        }

        $statement->execute();
        $result = $this->hydrateList($statement, 'n')
            ->map(function (Model $model) {
                return $this->loadAncestors($model, 'parent');
            });
        return $result;
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
        if ($curriculumNode) {
            $curriculumNode->setLearningArea($this->getJoinMapper('area')->hydrate($row, $areaAlias));
            if($curriculumNode->getLearningArea() instanceof LearningArea\Model) {
                $curriculumNode->getLearningArea()->setSource(
                    $this->getJoinMapper('area.source')->hydrate($row, $areaSourceAlias));
            }

            $curriculumNode->setLearningLevel($this->getJoinMapper('level')->hydrate($row, $levelAlias));
            if($curriculumNode->getLearningLevel() instanceof LearningLevel\Model) {
                $curriculumNode->getLearningLevel()->setSource(
                    $this->getJoinMapper('level.source')->hydrate($row, $levelSourceAlias));
            }
        }
        return $curriculumNode;
    }

    /**
     * Delete curriculum nodes associated with $assessment
     *
     * @param int $assessmentId
     * @return $this
     */
    public function deleteRelations($assessmentId)
    {
        $query = "
            DELETE FROM curriculum_node_for_assessment
            WHERE assessment_id = :assessmentId
        ";

        $statement = $this->getDatabase()->prepare($query);
        $statement->execute([
            'assessmentId' => $assessmentId
        ]);

        return $this;
    }

    /**
     * Insert curriculum nodes associated with $assessment
     *
     * @param Collection $curriculumNodes
     * @param $assessmentId
     * @return $this
     */
    public function insertRelations(Collection $curriculumNodes, $assessmentId)
    {
        $query = "
            INSERT INTO curriculum_node_for_assessment (curriculum_node_id, assessment_id)
            VALUES (:curriculumNodeId, :assessmentId)
        ";

        $statement = $this->getDatabase()->prepare($query);

        foreach ($curriculumNodes as $currentNode) {
            $statement->execute([
                'curriculumNodeId' => $currentNode->getId(),
                'assessmentId' => $assessmentId
            ]);
        }

        return $this;
    }
}
