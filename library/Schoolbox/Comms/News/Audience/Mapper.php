<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Comms\News\Audience;

use Schoolbox\Comms\News;
use Alaress\Database\Predicate\Builder;
use Schoolbox\GenericCollection;
use InvalidArgumentException;
use PDOStatement;
use PDO;

/**
 * @method Model createModel($idValue = null)
 * @method Model hydrate(array $row, $alias = '', $callback = null)
 * @method GenericCollection|Model[] createCollection($contents = [])
 * @method GenericCollection|Model[] findByPredicates(Builder $pb, array $options = [])
 */
class Mapper extends \Schoolbox\Mapper
{
    // Table definition
    public const TABLE_NAME                  = 'comms_news_audience';

    public const FIELD_ID                    = 'id';
    public const FIELD_COMMS_NEWS_ARTICLE_ID = 'comms_news_article_id';
    public const FIELD_PARENT_ID             = 'parent_id';
    public const FIELD_ENTITY_TYPE           = 'entity_type';
    public const FIELD_ENTITY_VALUE          = 'entity_value';
    public const FIELD_RELATIONSHIP          = 'relationship';


    /**
     * @inheritdoc
     * @throws InvalidArgumentException
     */
    protected function mapping(): void
    {
        $this->hasTable(self::TABLE_NAME);
        $this->hasModel(Model::class);

        $this->uses(self::FIELD_ID, self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->joins(self::FIELD_COMMS_NEWS_ARTICLE_ID, News\Mapper::class, 'id', null, null, 'commsNewsArticle', 'commsNewsArticle');
        $this->joins(self::FIELD_PARENT_ID, self::class, 'id', null, null, 'parent', 'parent');
        $this->joinsReverseMulti(self::FIELD_PARENT_ID, self::class, 'children', 'children');
        $this->uses(self::FIELD_ENTITY_TYPE, self::TYPE_STRING);
        $this->uses(self::FIELD_ENTITY_VALUE, self::TYPE_STRING);
        $this->uses(self::FIELD_RELATIONSHIP, self::TYPE_STRING);
    }

    /**
     * Attempt to join to both parent and children
     *
     * @return string
     * @throws \Schoolbox\Mapper\DefinitionException
     */
    protected function getSelectString(): string
    {
        return "
            SELECT    {$this->getColumnString()},
                      {$this->getJoinColumnString('parent', 'parent')},
                      {$this->getJoinColumnString('children', 'child')}
            FROM      {$this->getTable()}
            LEFT JOIN {$this->getJoinSql('parent', '', 'parent')}
            LEFT JOIN {$this->getJoinSql('children', '', 'child')}
        ";
    }
    /**
     * Returns a structured collection of segments.
     *
     * ie, composite segments will be linked to their child components, which are linked back to the parent composite segment.
     *
     * Parent and children are always hydrated.
     *
     * @param PDOStatement $statement
     * @return Model[]|GenericCollection
     * @throws \Schoolbox\Mapper\Exception
     */
    protected function hydrateSelect(PDOStatement $statement)
    {
        $idColumn       = $this->getIdentifierColumn();
        $parentIdColumn = "parent.{$this->getIdentifierColumn()}";
        $childIdColumn  = "child.{$this->getIdentifierColumn()}";
        $segments = $this->createCollection();
        /** @noinspection PhpAssignmentInConditionInspection */
        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
            // Hydrate Segment
            $id = $row[$idColumn];
            if (!$segments->offsetExists($id)) {
                $segments[$id] = $this->hydrate($row);
            }
            $segment = $segments[$id];
            // Link parent back to child if this is a child of a composite.
            $parentId = $row[$parentIdColumn];
            if ($parentId) {
                $parent = $this->hydrate($row, 'parent');
                $segment->setParent($parent);
                /** @noinspection NullPointerExceptionInspection */
                $parent->getChildren()[$id] = $segment;
            }
            // Optionally hydrate children
            $childId = $row[$childIdColumn];
            if ($childId) {
                $child = $this->hydrate($row, 'child');
                /** @noinspection NullPointerExceptionInspection */
                $child->setParent($segments[$id]);
                $segments[$id]->getChildren()[$childId] = $child;
            }
        }
        return $segments;
    }


    /**
     * Efficiently returns structured audience segments structure for attaching to a set of articles
     *
     * @param int[] $articleIds
     * @param array $options
     * @return GenericCollection|Model[]
     */
    public function findForArticles(array $articleIds, array $options = []): GenericCollection
    {
        return $this->findByColumn(self::FIELD_COMMS_NEWS_ARTICLE_ID, $articleIds, $options);
    }


    /**
     * Deletes a segment and its children using leaf-first strategy
     *
     * Returns whether any records were deleted.
     *
     * @param Model $segment
     * @return bool
     * @throws \Schoolbox\Mapper\Exception
     */
    public function deleteSegment(Model $segment): bool
    {
        $deletedChildren = false;
        foreach ($segment->getChildren() as $child) {
            if ($this->deleteSegment($child)) {
                $deletedChildren = true;
            }
        }
        $deleted = $this->delete($segment);
        return $deletedChildren || $deleted;
    }

    /**
     * Saves a segment and its children using root-first strategy
     *
     * Returns whether any records were deleted.
     *
     * @param Model $segment
     * @return bool|int[]
     * @throws \Schoolbox\Mapper\Exception
     */
    public function saveSegment(Model $segment)
    {
        $affectedIds = [];
        $id = $this->save($segment);
        if(false === $id){
            return false;
        }
        $affectedIds[] = $id;

        foreach ($segment->getChildren() as $child) {
            $child->setParent($segment);
            $childId = $this->saveSegment($child);
            if(false === $childId){
                $affectedIds[] = $childId;
            }
        }
        return $affectedIds;
    }

    /**
     * Deletes related news article audience by folder id
     *
     * @param int $cid
     * @return void
     */
    public function homepageClean(int $cid) :void
    {
        $entityType = 'folder';

        // Query for composite folders
        $query = $this->getSelectString() . 'WHERE `child`.'. self::FIELD_ENTITY_VALUE .' = :folderId 
        AND `child`.'. self::FIELD_ENTITY_TYPE .' = :entityType';

        $statement = $this->database->prepare($query);
        $statement->bindParam(':folderId', $cid);
        $statement->bindParam(':entityType', $entityType);
        $statement->execute();

        // Delete composite folder audiences if any
        foreach ($statement->fetchAll() as $key => $segment) {
            $this->deleteSegment($this->findById($segment['id']));
        }

        // Delete non-composite folder audiences as well if any
        $this->deleteByColumnList([
            self::FIELD_ENTITY_VALUE   => $cid,
            self::FIELD_ENTITY_TYPE    => $entityType
        ]);
    }
}
