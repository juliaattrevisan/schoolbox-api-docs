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


/**
 * Class Mapper
 *
 * @package Schoolbox\External\Profile\Student
 * @method Model createModel($idValue = null)
 * @method Model findById($identifierValue)
 * @method Model hydrate(array $row, $alias = '', $callback = null)
 * @method Model[]|GenericCollection createCollection($contents = [])
 */
class Mapper extends \Schoolbox\Mapper
{
    /**
     * @inheritDoc
     */
    protected function mapping(): void
    {
        $this->hasTable('sis_user_profile_student');
        $this->hasModel(Model::class);
        $this->joins('sis_user_profile_id', Profile\Mapper::class, 'id', null, self::COLUMN_IDENTIFIER, 'baseProfile', 'baseProfile');
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
        $this->uses('tutor_group', self::TYPE_STRING);
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
        $this->uses('boarding_status', self::TYPE_STRING);
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
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
                {$this->getJoinColumnString('baseProfile', 'bp')}
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
            INNER JOIN {$this->getJoinSql('baseProfile', null, 'bp')}
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
            $studentProfile = $this->hydrate($row);

            if ($studentProfile) {
                $baseProfile = $this->getJoinMapper('baseProfile')->hydrate($row, 'bp');
                $studentProfile->setBaseProfile($baseProfile);
                $collection->add($studentProfile);
            }
        }

        return $collection;
    }


    /**
     * Find pieces of a student's profile by their externalId
     * Returns base and extension profile
     *
     * @param string $externalId
     * @return Model
     * @throws DefinitionException
     * @throws Exception
     * @throws Exception\NotFound
     * @throws \PDOException
     */
    public function findByExternalId(string $externalId): Model
    {
        $query = "
            SELECT * 
            FROM {$this->getTable()} student
            INNER JOIN sis_user_profile profile ON student.sis_user_profile_id = profile.id
            WHERE profile.external_id = :externalId
        ";

        $statement = $this->database->prepare($query);

        $statement->execute([
            'externalId' => $externalId
        ]);

        $row = $statement->fetch(\PDO::FETCH_ASSOC);

        if ($row) {
            /** @var Profile\Model $baseProfile */
            $baseProfile    = $this->getJoinMapper('baseProfile')->hydrate($row);
            $studentProfile = $this->hydrate($row);

            if ($baseProfile && $studentProfile) {
                $studentProfile->setBaseProfile($baseProfile);

                return $studentProfile;
            }
        }

        throw new Exception\NotFound('Unknown profile.');
    }
}
