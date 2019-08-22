<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\External\Profile\Student;

use PDO;
use PDOStatement;
use Schoolbox\External\Profile;
use Schoolbox\GenericCollection;
use Schoolbox\Mapper\{Exception, DefinitionException};
use UnexpectedValueException;

class Mapper extends \Schoolbox\Mapper
{
    /**
     * @inheritDoc
     */
    protected function mapping(): void
    {
        $this->hasTable('sis_user_profile_student');
        $this->hasModel(Model::class);
        $this->joins('sis_user_profile_id', Profile\Mapper::class, 'id', null, self::COLUMN_IDENTIFIER, 'user', 'user');
        $this->uses('date_of_birth', self::TYPE_DATE);
        $this->uses('username', self::TYPE_STRING);
        $this->uses('email', self::TYPE_STRING);
        $this->uses('gender', self::TYPE_STRING);
        $this->uses('head_of_house_id', self::TYPE_STRING);
        $this->uses('head_of_house_name', self::TYPE_STRING);
        $this->uses('house_name', self::TYPE_STRING);
        $this->uses('form_name', self::TYPE_STRING);
        $this->uses('form_teacher_id', self::TYPE_STRING);
        $this->uses('form_teacher_name', self::TYPE_STRING);
        $this->uses('tutor_name', self::TYPE_STRING);
        $this->uses('tutor_teacher_id', self::TYPE_STRING);
        $this->uses('tutor_teacher_name', self::TYPE_STRING);
        $this->uses('year_level', self::TYPE_STRING);
        $this->uses('year_level_coordinator_id', self::TYPE_STRING);
        $this->uses('year_level_coordinator_name', self::TYPE_STRING);
        $this->uses('mobile', self::TYPE_STRING);
        $this->uses('studies_code', self::TYPE_STRING);
        $this->uses('alert_medical', self::TYPE_STRING);
        $this->uses('alert_comment', self::TYPE_STRING);
        $this->uses('alert_message', self::TYPE_STRING);
        $this->uses('alert_legal', self::TYPE_STRING);
        $this->uses('enrolment_date', self::TYPE_DATE);
        $this->uses('leaving_date', self::TYPE_DATE);
        $this->uses('ib', self::TYPE_BOOLEAN);
        $this->uses('individual_learning_plans', self::TYPE_BOOLEAN);
        $this->uses('esl', self::TYPE_BOOLEAN);
        $this->uses('special_needs', self::TYPE_BOOLEAN);
        $this->uses('boarding_status', self::TYPE_BOOLEAN);
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);
    }

    /**
     * Return the first part of the string required to select from this mapper
     *
     * @return string
     */
    protected function getSelectString(): string
    {
        return "
            SELECT 
                {$this->getColumnString()},
                {$this->getJoinColumnString('user', 'u')}
        ";
    }

    /**
     * The Next Pieces
     *
     * @return string
     * @throws DefinitionException
     */
    protected function getFromString(): string
    {
        return "
            FROM {$this->getTable()}
            INNER JOIN {$this->getJoinSql('user', null, 'u')}
        ";
    }

    /**
     * @inheritdoc
     * @throws Exception
     * @throws DefinitionException
     * @throws UnexpectedValueException
     */
    protected function hydrateSelect(PDOStatement $statement): GenericCollection
    {
        $collection = $this->createCollection();

        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
            $student = $this->hydrate($row);

            if ($student) {
                $user = $this->getJoinMapper('user')->hydrate($row, 'u');
                $student->setUser($user);
                $collection->add($student);
            }
        }

        return $collection;
    }
}
