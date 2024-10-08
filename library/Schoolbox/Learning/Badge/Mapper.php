<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Badge;

use PDOStatement;
use Schoolbox\File\Mimetype;
use Schoolbox\GenericCollection;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Resource\Storage\Mapper as StorageMapper;
use Schoolbox\Role;
use Schoolbox\User;
use Schoolbox\UserMapper;
use Schoolbox\YearLevel\Mapper as YearLevelMapper;

/**
 * Class Mapper
 *
 * @method Model findById($id)
 * @method Model findOneByColumn($columnName, $columnValue, array $options = [])
 * @method Model findOneByColumnList($columnList, array $options = [])
 * @method Model[] findByColumnList($columnList, array $options = [])
 * @method Model hydrate(array $row, $alias = '', $callback = null)
 * @method Model createModel($idValue = null)
 *
 * @package Schoolbox\Learning\Badge\Category
 */
class Mapper extends \Schoolbox\Mapper
{
    /**
     * @inheritDoc
     * @throws MapperException
     */
    protected function mapping(): void
    {
        $this->hasTable('badge');
        $this->hasModel(Model::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('name', self::TYPE_STRING);
        $this->uses('body', self::TYPE_STRING);
        $this->joins('image_hash', StorageMapper::class, 'hash', null, null, 'image', 'image');
        $this->uses('image_path', self::TYPE_STRING, null, 'icon', null);
        $this->uses('allow_as_goal', self::TYPE_BOOLEAN);
        $this->joins('category_id', Category\Mapper::class, 'id', null, null, 'category', 'category');
        $this->uses(
            'achievement_expiry',
            self::TYPE_INTEGER,
            self::COLUMN_ENUM,
            'achievementExpiry',
            Model::VALID_ACHIEVEMENT_EXPIRY_VALUES,
        );
        $this->uses(
            'goal_expiry',
            self::TYPE_INTEGER,
            self::COLUMN_ENUM,
            'goalExpiry',
            Model::VALID_GOAL_EXPIRY_VALUES,
        );
        $this->joins('created_by', UserMapper::class, 'id', null, null, 'createdBy', 'createdBy');
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->joins('updated_by', UserMapper::class, 'id', null, null, 'updatedBy', 'updatedBy');
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);
        $this->uses('animate', self::TYPE_BOOLEAN);

        $this->joinsVia(
            'badge_year_level',
            YearLevelMapper::class,
            YearLevelMapper::COL_ID,
            'year_level_id',
            'badge_id',
            null,
            null,
            null,
            'yearLevels'
        );

        /* A bunch of Curriculum Nodes
         */
        $this->joinsVia(
            'curriculum_badge',
            \Schoolbox\Curriculum\Node\Mapper::class,
            'id',
            'curriculum_node_id',
            'badge_id',
            null,
            null,
            null,
            'curriculumNodes'
        );

        $this->registerCallback(self::CALLBACK_PRESOFTDELETE, [$this, 'deleteAwards']);
        $this->registerPersistSearchIndexCallbacks();
    }


    /**
     * @return string
     * @throws \Schoolbox\Mapper\DefinitionException
     */
    protected function getSelectString(): string
    {
        /** @var YearLevelMapper $yearLevelMapper */
        $yearLevelMapper = $this->getJoinMapper('badge_year_level');

        return "
            SELECT
                {$this->getColumnString()},
                {$this->getJoinColumnString('image', 'i')},
                {$this->getJoinColumnString('image.allowedFileExtension', 'm')},
                {$this->getJoinColumnString('category', 'bc')},
                {$this->getJoinColumnString('createdBy', 'c')},
                {$this->getJoinColumnString('createdBy.pronoun', 'cp')},
                {$this->getJoinColumnString('createdBy.role', 'cr')},
                {$this->getJoinColumnString('updatedBy', 'u')},
                {$this->getJoinColumnString('updatedBy.role', 'ur')},
                {$this->getJoinColumnString('updatedBy.pronoun', 'up')},
                {$yearLevelMapper->getColumnString('byl')}
            FROM {$this->getDatabase()->getQuoter()->quoteIdentifier($this->getTable())}
            LEFT JOIN {$this->getJoinSql('image', '', 'i', true)}
            LEFT JOIN {$this->getJoinSql('image.allowedFileExtension', 'i', 'm', true)}
            INNER JOIN {$this->getJoinSql('category', '', 'bc', true)}
            INNER JOIN {$this->getJoinSql('createdBy', '', 'c', true)}
            INNER JOIN {$this->getJoinSql('createdBy.role', 'c', 'cr', true)}
            LEFT  JOIN {$this->getJoinSql('createdBy.pronoun', 'c', 'cp')}
            INNER JOIN {$this->getJoinSql('updatedBy', '', 'u', true)}
            INNER JOIN {$this->getJoinSql('updatedBy.role', 'u', 'ur', true)}
            LEFT  JOIN {$this->getJoinSql('updatedBy.pronoun', 'u', 'up')}
            LEFT JOIN {$this->getJoinSql('badge_year_level', '', 'byl')}
        ";
    }


    /**
     * Find all the curriculum nodes that used to come with each Model instance
     *
     * @param Model $badge
     * @return GenericCollection
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\DefinitionException
     * @throws MapperException
     * @throws \UnexpectedValueException
     */
    public function findCurriculumNodes(Model $badge): GenericCollection
    {
        $curriculumNodeMapper = \Schoolbox\Curriculum\Node\Mapper::getInstance();

        $query = "
            SELECT {$curriculumNodeMapper->getColumnString('cn')}
            FROM {$this->getTable()}
            LEFT JOIN {$this->getJoinSql('curriculum_badge', '', 'cn')}
            WHERE {$this->getTable()}.{$this->getIdentifierColumn()} = :badgeId
        ";

        $statement = $this->getDatabase()->prepare($query);
        $statement->execute([
            'badgeId' => $badge->getId()
        ]);

        $nodes = $curriculumNodeMapper->createCollection();

        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $node = $curriculumNodeMapper->hydrate($row, 'cn');

            if ($node) {
                $nodes->add($node);
            }
        }

        return $nodes;
    }


    /**
     * Hydrate joins added in `getSelectString`.
     *
     * @param PDOStatement $statement
     * @return GenericCollection|Model[]
     * @throws MapperException
     * @throws \UnexpectedValueException
     */
    protected function hydrateSelect(PDOStatement $statement)
    {
        /** @var YearLevelMapper $yearLevelMapper */
        $yearLevelMapper = $this->getJoinMapper('badge_year_level');

        $collection = $this->createCollection();

        foreach ($statement as $row) {
            $badgeId = $row['id'];

            if (!$collection->offsetExists($badgeId)) {
                $collection[$badgeId] = $this->hydrate($row);
            }

            /* Set the Category
             */
            $category = $this->hydrateJoin($row, 'category', 'bc');

            if ($category instanceof Category\Model) {
                $collection[$badgeId]->setCategory($category);
            }

            if (!$collection[$badgeId]->getYearLevels()) {
                $collection[$badgeId]->setYearLevels($this->getJoinMapper('badge_year_level')->createCollection());
            }
            $yearLevelId = $row['byl.' . YearLevelMapper::COL_ID];

            if ($yearLevelId) {
                $collection[$badgeId]->getYearLevels()->setOffset($yearLevelId, $yearLevelMapper->hydrate($row, 'byl'));
            }

            // Set the list of curriculum codes as an empty collection:
            // these will be hydrated in `hydrateCurriculumNodesIntoCollection`
            $collection[$badgeId]->setCurriculumNodes($this->getJoinMapper('curriculum_badge')->createCollection());

            /* Set the creator and also their role and pronoun
             */
            $createdBy = $this->hydrateJoin($row, 'createdBy', 'c');
            if ($createdBy instanceof User) {
                $role = $this->hydrateJoin($row, 'createdBy.role', 'cr');
                if ($role instanceof Role) {
                    $createdBy->setRole($role);
                }
                $createdBy->setPronoun($this->hydrateJoin($row, 'createdBy.pronoun', 'cp'));
                $collection[$badgeId]->setCreatedBy($createdBy);
            }

            /* Set the updator and also their role and pronoun
             */
            $updatedBy = $this->hydrateJoin($row, 'updatedBy', 'u');
            if ($updatedBy instanceof User) {
                $role = $this->hydrateJoin($row, 'updatedBy.role', 'ur');
                if ($role instanceof Role) {
                    $updatedBy->setRole($role);
                }
                $updatedBy->setPronoun($this->hydrateJoin($row, 'updatedBy.pronoun', 'up'));
                $collection[$badgeId]->setUpdatedBy($updatedBy);
            }

            /* Add the optional image and its mime
             */
            $image = $this->hydrateJoin($row, 'image', 'i');

            if ($image instanceof \Schoolbox\Resource\Storage\Model) {
                $mime = $this->hydrateJoin($row, 'image.allowedFileExtension', 'm');

                if ($mime instanceof Mimetype) {
                    $image->setMimetype($mime);
                }

                $collection[$badgeId]->setImage($image);
            }
        }

        // Fetch curriculum nodes in a separate query
        return $this->hydrateCurriculumNodesIntoCollection($collection);
    }


    /**
     * Pre-delete hook - soft deletes awards associated to this badge.
     *
     * @param Model $model
     */
    public function deleteAwards(Model $model)
    {
        // using mapper to retract related messages
        $awardMapper = Award\Mapper::getInstance();
        $awardMapper->deleteByColumn('badge_id', $model->getId(), ['deleted' => true]);
    }


    /**
     * Helper method for hydrating curriculum nodes into the collection of
     * badges retrieved by `hydrateSelect`.
     *
     * @param GenericCollection $collection
     * @return GenericCollection
     */
    private function hydrateCurriculumNodesIntoCollection(GenericCollection $collection): GenericCollection
    {
        $badgeIds = $collection->keys();
        $pb       = $this->getPredicateBuilder()->in('id', $badgeIds);
        $query    = "
            SELECT
                {$this->getColumnString()},
                {$this->getJoinColumnString('curriculum_badge', 'cn')}
            FROM {$this->getDatabase()->getQuoter()->quoteIdentifier($this->getTable())}
            INNER JOIN curriculum_badge cb ON badge.id = cb.badge_id
            INNER JOIN curriculum_node cn ON cb.curriculum_node_id = cn.id AND cn.deleted_at IS NULL
            WHERE {$pb->getWhereIntersect()}
        ";

        $curriculumNodeStatement = $this->database->query($query, $pb->getParams());
        foreach ($curriculumNodeStatement as $row) {
            /** @var \Schoolbox\Curriculum\Node\Model $curriculumNode */
            $curriculumNode = $this->hydrateJoin($row, 'curriculum_badge', 'cn');
            $collection[$row['id']]->getCurriculumNodes()->setOffset($curriculumNode->getId(), $curriculumNode);
        }
        return $collection;
    }
}
