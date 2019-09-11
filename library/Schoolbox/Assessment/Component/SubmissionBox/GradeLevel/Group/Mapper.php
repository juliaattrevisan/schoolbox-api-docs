<?php

/**
 * Mapper for submission box grade groups.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Assessment\Component\SubmissionBox\GradeLevel\Group;

use PDOStatement;
use Schoolbox\Assessment\Component\SubmissionBox\GradeLevel\Mapper as GradeMapper;
use Schoolbox\Assessment\Component\SubmissionBox\GradeLevel\Model as GradeModel;
use Schoolbox\GenericCollection;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Collection;

/**
 * Class Mapper
 *
 * @package Schoolbox\Assessment\Component\SubmissionBox\GradeLevel\Group
 * @method Model findByIdWithFallback($identifierValue)
 * @method Model findById($identifierValue)
 */
class Mapper extends BaseMapper
{
    /**
     * {@inheritdoc}
     */
    protected function mapping()
    {
        $this->hasTable('submission_grade_group');
        $this->hasModel(Model::class);
        $this->hasMap(
            array(
                'column'     => 'id',
                'property'   => 'id',
                'type'       => self::TYPE_INTEGER,
                'identifier' => true,
            )
        );
        $this->hasMap(
            array(
                'column'   => 'name',
                'property' => 'name',
                'type'     => self::TYPE_STRING,
            )
        );
        $this->hasJoin(
            'levels',
            array(
                'mapper'    => GradeMapper::class,
                'this_key'  => 'id',
                'other_key' => 'submission_grade_group_id',
                'multiple'  => true,
            )
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
}
