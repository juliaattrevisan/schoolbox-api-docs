<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Component\SubmissionBox\GradeLevel\Group;

use PDOStatement;
use Schoolbox\Assessment\Component\SubmissionBox\GradeLevel\Mapper as GradeMapper;
use Schoolbox\Assessment\Component\SubmissionBox\GradeLevel\Model as GradeModel;
use Schoolbox\Collection;
use Schoolbox\GenericCollection;
use Schoolbox\Mapper as BaseMapper;

/**
 * Mapper for due work grade groups.
 *
 * @package Schoolbox\Assessment\Component\SubmissionBox\GradeLevel\Group
 * @method Model findByIdWithFallback($identifierValue)
 * @method Model findById($identifierValue)
 * @method Model createModel($idValue = null)
 */
class Mapper extends BaseMapper
{
    /** @var int Fake ids for non letter group */
    public const GRADE_GROUP_ID_NONE       = -1;
    public const GRADE_GROUP_ID_PERCENTAGE = -2;

    /**
     * {@inheritdoc}
     */
    protected function mapping()
    {
        $this->hasTable('submission_grade_group');
        $this->hasModel(Model::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('name', self::TYPE_STRING);
        $this->hasJoin(
            'levels',
            [
                'mapper'    => GradeMapper::class,
                'this_key'  => 'id',
                'other_key' => 'submission_grade_group_id',
                'multiple'  => true,
            ]
        );
    }

    /**
     * @return string
     */
    protected function getSelectString()
    {
        return "
            SELECT
                {$this->getColumnString()},
                {$this->getJoinColumnString('levels', 'l')}
            FROM {$this->getDatabase()->quoteIdentifier($this->getTable())}
            LEFT JOIN {$this->getJoinSql('levels', '', 'l')}
        ";
    }

    /**
     * @param PDOStatement $statement
     * @return GenericCollection|Model[]
     * @throws BaseMapper\DefinitionException
     * @throws BaseMapper\Exception
     */
    public function hydrateSelect(PDOStatement $statement)
    {
        /** @var GradeMapper $gradeLevelMapper */
        $gradeLevelMapper = $this->getJoinMapper('levels');
        /** @var Model[] $groups */
        $groups = [];
        foreach ($statement as $row) {
            // Build a grade group if required
            $id = $row['id'];
            if (!isset($groups[$id])) {
                $groups[$id] = $this->hydrate($row);
                $groups[$id]->setGradeLevels($gradeLevelMapper->createCollection());
            }
            // Build levels
            $level = $gradeLevelMapper->hydrate($row, 'l');
            if ($level instanceof GradeModel) {
                $groups[$id]->addGradeLevel($level);
            }
        }
        // Sort each group's level by lower bound
        foreach ($groups as $group) {
            $group->getGradeLevels()->sortBy('lowerBound', 'asc');
        }
        return $this->createCollection(array_values($groups));
    }

    /**
     * Loads all groups, along with their constituent grade levels.
     *
     * @deprecated all queries on this mapper now hydrate all grade levels, so just use `findAll()`.
     *
     * @return Collection|Model[]
     */
    public function findAllWithGradeLevels()
    {
        return $this->findAll();
    }

    /**
     * Returns options for default grade group
     * @return GenericCollection
     */
    public function findAllForAdminOption()
    {
        $collection = $this->createCollection();
        $none       = (new Model())->setId(self::GRADE_GROUP_ID_NONE)->setName('None');
        $collection->add($none);
        $percentage = (new Model())->setId(self::GRADE_GROUP_ID_PERCENTAGE)->setName('Percentage');
        $collection->add($percentage);
        $collection = $collection->merge($this->findAll());
        return $collection;
    }
}
