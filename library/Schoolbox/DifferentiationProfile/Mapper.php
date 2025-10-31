<?php

/**
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\DifferentiationProfile;

use Schoolbox\GenericCollection;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\UserMapper;

/**
 * Class Mapper
 * @method GenericCollection|Model[] findByColumnList($columnList, $options = [])
 *
 * @package Schoolbox\DifferentiationProfile
 */
class Mapper extends BaseMapper
{
    /**
     * Setup mapper
     */
    protected function mapping(): void
    {
        $this->hasTable('differentiation_profiles');
        $this->hasModel(Model::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->joins('student_id', UserMapper::class, 'id', null, null, 'student', 'student');
        $this->uses('adjustment_category', self::TYPE_STRING);
        $this->uses('adjustment_level', self::TYPE_STRING);
        $this->uses('overview', self::TYPE_STRING);
        $this->uses('interests_strengths', self::TYPE_STRING);
        $this->uses('needs_challenges', self::TYPE_STRING);
        $this->uses('strategies_adjustments', self::TYPE_STRING);
        $this->uses('goals', self::TYPE_STRING);
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);
    }

    /**
     * Find the list of students that required has or will require differentiation profile
     *
     * @return GenericCollection
     * @throws BaseMapper\Exception
     */
    public function findStudentList()
    {
        $query = "SELECT {$this->getColumnString()}, {$this->getJoinColumnString('student', 's')}
                FROM pastoral p
                INNER JOIN user s ON p.student_id = s.id AND s.deleted_at IS NULL
                LEFT JOIN lu_pastoral_type lpt ON p.type_id = lpt.id
                LEFT JOIN lu_pastoral_subtype lps ON p.subtype_id = lps.id
                LEFT JOIN differentiation_profiles ON p.student_id = differentiation_profiles.student_id AND differentiation_profiles.deleted_at IS NULL
                WHERE p.expires_at IS NULL AND (lpt.contribute_differentiation_profile OR lps.contribute_differentiation_profile)
                GROUP BY p.student_id
                ORDER BY s.lastname ASC, s.chosen_name ASC
        ;";

        $statement = $this->getDatabase()->prepareQuery($query);
        $statement->execute();

        $collection = $this->createCollection();

        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $model = $this->hydrate($row);
            /* No profile exists yet, let's create an empty one
             */
            if (null === $model) {
                $model = $this->createModel();
            }
            $model->setStudent($this->getJoinMapper('student')->hydrate($row, 's'));
            $collection->add($model);
        }

        return $collection;
    }

    protected function getDefaultOrderByParams(): array
    {
        return [
            'student.lastname' => 'ASC',
            'student.chosen_name' => 'ASC',
        ];
    }
}
