<?php

/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Curriculum\Node;

use Schoolbox\Collection;
use Schoolbox\Comms\News\Audience\ArticleResolver;
use Schoolbox\Curriculum\LearningArea;
use Schoolbox\Curriculum\LearningLevel;
use Schoolbox\GenericCollection;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Mapper\Exception as MapperException;

/**
 * Mapper for curriculum nodes
 *
 * @method Model createModel($idValue = null)
 * @method Model findById($identifierValue)
 * @method Model findOneByColumn($columnName, $columnValue, array $options = [])
 * @method Model findOneByColumnList($columnList, array $options = [])
 */
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
        $this->uses('enabled', self::TYPE_BOOLEAN);
        $this->uses('reference', self::TYPE_STRING);
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('modified_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);
        $this->registerCallback(self::CALLBACK_PRESAVE, [$this, 'assertHeritage']);
        $this->registerCallback(self::CALLBACK_POSTSAVE, [$this, 'disableOrReEnableChildren']);
        $this->registerCallback(self::CALLBACK_POSTSOFTDELETE, [$this, 'deleteChildren']);
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
        } catch (\Schoolbox\Mapper\Exception $e) {
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
     * Pre-save callback: ensure that this node's parent is not being set to
     * itself or one of its own children.
     *
     * @param Model      $model
     * @param Model|null $previous
     * @return bool
     * @throws MapperException
     */
    public function assertHeritage(Model $model, Model $previous = null): bool
    {
        // Only need to check if the model's parentage has changed
        $parentId         = $model->getParent()?->getId();
        $previousParentId = $previous?->getParent()?->getId();
        if ($parentId && $previousParentId !== $parentId) {
            // Check parentage, all the way up to the top:
            // If I find myself: error out
            $ancestry = [$model->getId()];
            while (isset($parentId)) {
                $ancestry[] = $parentId;
                if ($parentId === $model->getId()) {
                    throw new MapperException(
                        sprintf(
                            "Ancestry loop detected: node #%d would be its own ancestor [%s]",
                            $model->getId(),
                            implode(' > ', $ancestry),
                        )
                    );
                }
                // Get next parent
                $result = $this->findScalarByPredicates(
                    'curriculum_node_parent_id',
                    $this->getPredicateBuilder()->equals('id', $parentId),
                );
                if (empty($result)) {
                    $parentId = null;
                } else {
                    [$parentId] = $result;
                }
            }
        }
        return true;
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
     * Post-save callback:
     * * if this node is no longer enabled, disable all child nodes
     * * if this node was not enabled but is now, enable all child nodes
     *
     * AS: I don't think enabling a node which was disabled should re-enable
     * its children, because the node might have children which should remain
     * disabled.
     *
     * This method applies recursively to each node's children: the action of
     * disabling or re-enabling a node will call this method on the children.
     *
     * @param Model      $model
     * @param Model|null $previous
     */
    public function disableOrReEnableChildren(Model $model, Model $previous = null)
    {
        if ($previous) {
            $isEnabled  = $model->getEnabled();
            $wasEnabled = $previous->getEnabled();
            if ($isEnabled !== $wasEnabled) {
                $this->updateAll(
                    $this->findByColumn(
                        'curriculum_node_parent_id',
                        $model->getId(),
                    ),
                    'enabled',
                    $isEnabled,
                    self::CALLBACK_PRESAVE,
                    self::CALLBACK_POSTSAVE,
                );
            }
        }
    }

    /**
     * @param ArticleResolver $articleResolver
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
        ArticleResolver $articleResolver,
        $user,
        $keyword,
        $offset = 0,
        $limit = 0,
        $sortProperty = '',
        $sortDirection = 'DESC'
    ) {
        // Load all ancestors, no descendants
        return $this->findByKeyword($keyword, $limit, $offset, 50, 0, false, null, null);
    }

    /**
     * Find all nodes which match a keyword.
     *
     * @param string    $keyword      The keyword to search for
     * @param int       $limit        The max number of nodes to return
     * @param int       $offset       The number of matching nodes to skip in
     *                                the result
     * @param int       $ancestors    How many levels of parent node to include
     * @param int       $descendants  How many levels of child node to include
     * @param bool      $topLevelOnly If true, return only nodes with no parent
     * @param int|null  $parentId     If not null, search only within this node
     *                                and descendants
     * @param bool|null $enabled      If not null: return only nodes which are
     *                                enabled (if true) or disabled (if false)
     * @return Collection|Model[]
     */
    public function findByKeyword(
        string $keyword,
        int $limit,
        int $offset,
        int $ancestors,
        int $descendants,
        bool $topLevelOnly,
        ?int $parentId,
        ?bool $enabled,
    ): Collection {
        $filteredIds = null;
        if (isset($parentId)) {
            // Collate a list of descendant IDs
            // (impossible without recursive query)
            $filteredIds   = $this->findDescendantIds([$parentId], 'curriculum_node_parent_id');
            $filteredIds[] = $parentId;
        }
        $query = "
            SELECT
                {$this->getColumnString('n')}
            FROM {$this->getTable()} n
        ";
        $quotedLike = $this->getDatabase()->getQuoter()->escapeForLike($keyword);
        $pb = $this->getPredicateBuilder()
            ->isNull('n.deleted_at');
        if ($keyword !== '') {
            $pb = $pb->any(
                $this->getPredicateBuilder()
                    ->like('n.code', $quotedLike . '%')
                    ->all(
                        $this->getPredicateBuilder()
                            ->notEquals('n.code', '')
                            ->like('n.name', '%' . $quotedLike . '%')
                    )
            );
        }
        if ($topLevelOnly) {
            $pb = $pb->isNull('n.curriculum_node_parent_id');
        }
        if (isset($filteredIds)) {
            $pb = $pb->in('n.id', $filteredIds);
        }
        if (isset($enabled)) {
            $pb = $pb->equals('n.enabled', $enabled);
        }

        $query .= ' WHERE ' . $pb->getWhereIntersect() . ' ORDER BY n.virtual_order ASC, n.id ASC';
        $params = $pb->getParams();
        if ($limit > 0) {
            $query .= ' LIMIT :limit';
        }
        if ($offset) {
            $query .= ' OFFSET :offset';
        }
        $statement = $this->getDatabase()->prepare($query);
        foreach ($params as $param => $value) {
            $statement->bindValue($param, $value);
        }
        if ($limit > 0) {
            $statement->bindValue('limit', $limit, \PDO::PARAM_INT);
        }
        if ($offset) {
            $statement->bindValue('offset', $offset, \PDO::PARAM_INT);
        }

        $statement->execute();
        $result = $this->hydrateList($statement, 'n');
        $result = $this->loadAncestors($result, 'parent', $ancestors);
        $result = $this->loadDescendants($result, 'parent', 'children', $descendants);
        return $result;
    }

    /**
     * Order by the virtual label field, then by ID.
     *
     * @return array
     */
    protected function getDefaultOrderByParams(): array
    {
        return [
            'virtual_order' => 'asc',
            'id'            => 'asc',
        ];
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
            if ($curriculumNode->getLearningArea() instanceof LearningArea\Model) {
                $curriculumNode->getLearningArea()->setSource(
                    $this->getJoinMapper('area.source')->hydrate($row, $areaSourceAlias)
                );
            }

            $curriculumNode->setLearningLevel($this->getJoinMapper('level')->hydrate($row, $levelAlias));
            if ($curriculumNode->getLearningLevel() instanceof LearningLevel\Model) {
                $curriculumNode->getLearningLevel()->setSource(
                    $this->getJoinMapper('level.source')->hydrate($row, $levelSourceAlias)
                );
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
     * @param int $assessmentId
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


    /**
     * Retrieves curriculum nodes for a set of IDs that reference objects from
     * a related mapper.
     *
     * The related mappers have `joinsVia` relations to this one, but this
     * mapper doesn't have relations back.
     *
     * @param BaseMapper $otherMapper The mapper that joins onto this one
     * @param string     $otherJoin   The name of the join through which
     *                                `$otherMapper` joins onto this
     * @param array      $ids         A list of `$otherMapper` model instances
     *                                to fetch nodes for
     *
     * @return array|GenericCollection[]|Model[][] First level key: `$otherMapper` model instance ID
     *                                             Second level key: `Model` instance ID
     *                                             Second level value: `Model` instance
     */
    public function findForOtherMapper(BaseMapper $otherMapper, string $otherJoin, array $ids): array
    {
        if (empty($ids)) {
            // do nothing
            return [];
        }

        $nodePb    = $otherMapper->getPredicateBuilder()->in('id', $ids);
        $where     = implode(' AND ', $nodePb->getWhere());
        $nodeQuery = "
                SELECT
                    {$otherMapper->getTable()}.{$otherMapper->getIdentifierColumn()},
                    {$otherMapper->getJoinColumnString($otherJoin, 'cn')},
                    {$otherMapper->getJoinColumnString("$otherJoin.level", 'cnl')},
                    {$otherMapper->getJoinColumnString("$otherJoin.level.source", 'cnls')},
                    {$otherMapper->getJoinColumnString("$otherJoin.area", 'cna')},
                    {$otherMapper->getJoinColumnString("$otherJoin.area.source", 'cnas')}
                FROM {$otherMapper->getTable()}
                INNER JOIN {$otherMapper->getJoinSql($otherJoin, '', 'cn')}
                LEFT  JOIN {$otherMapper->getJoinSql("$otherJoin.level", 'cn', 'cnl')}
                LEFT  JOIN {$otherMapper->getJoinSql("$otherJoin.level.source", 'cnl', 'cnls')}
                LEFT  JOIN {$otherMapper->getJoinSql("$otherJoin.area", 'cn', 'cna')}
                LEFT  JOIN {$otherMapper->getJoinSql("$otherJoin.area.source", 'cna', 'cnas')}
                WHERE {$where}
            ";

        $statement = $this->getDatabase()->prepare($nodeQuery);
        $statement->execute($nodePb->getParams());
        $result = [];
        foreach ($statement as $row) {
            $id   = $row['id'];
            $node = $this->hydrateWithRelatedJoins($row, 'cn', 'cna', 'cnas', 'cnl', 'cnls');
            if (!isset($result[$id])) {
                $result[$id] = $this->createCollection();
            }
            if ($node) {
                $result[$id][$node->getId()] = $node;
            }
        }
        return $result;
    }
}
