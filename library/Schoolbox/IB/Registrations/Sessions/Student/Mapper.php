<?php

/**
 * IB Registration Session Student Mapper
 * @author    Haris Ali
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\IB\Registrations\Sessions\Student;

use PDO;
use PDOStatement;
use Schoolbox\Collection;
use Schoolbox\GenericCollection;
use Schoolbox\IB\Registrations\Sessions\Mapper as SessionMapper;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\UserMapper;

/**
 * Class Mapper
 * @method Model createModel($idValue = null)
 * @method Model hydrate(array $row, $alias = '', $callback = null)
 * @method GenericCollection|Model[] findByColumnList($columnList, $options = [])
 *
 * @package Schoolbox\IB\Registrations\Sessions\Student
 */
class Mapper extends BaseMapper
{
    /**
     * @inheritdoc
     */
    protected function mapping()
    {
        $this->hasTable('ib_registration_session_student');
        $this->hasModel(Model::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->joins('registration_session_id', SessionMapper::class, 'id', null, null, 'registrationSession', 'registrationSession');
        $this->joins('user_id', UserMapper::class, 'id', null, null, 'user', 'user');
        $this->uses('status', self::TYPE_INTEGER);
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
    }

    /**
     * @inheritDoc
     */
    protected function getSelectString(): string
    {
        return "
            SELECT {$this->getColumnString()},
                   {$this->getJoinColumnString('registrationSession', 'rs')},
                   {$this->getJoinColumnString('registrationSession.systemPurpose', 'sp')},
                   {$this->getJoinColumnString('registrationSession.systemPurpose.category', 'c')},
                   {$this->getJoinColumnString('registrationSession.createdBy', 'uc')},
                   {$this->getJoinColumnString('registrationSession.updatedBy', 'uu')},
                   {$this->getJoinColumnString('registrationSession.deletedBy', 'ud')},
                   {$this->getJoinColumnString('user', 'u')}
        ";
    }

    /**
     * @inheritDoc
     */
    protected function getFromString(): string
    {
        return "
            FROM      {$this->getTable()}
            INNER JOIN {$this->getJoinSql('registrationSession', '', 'rs')}
            LEFT JOIN {$this->getJoinSql('registrationSession.systemPurpose', 'rs', 'sp')}
            LEFT JOIN {$this->getJoinSql('registrationSession.systemPurpose.category', 'sp', 'c')}
            LEFT JOIN {$this->getJoinSql('registrationSession.createdBy', 'rs', 'uc')}
            LEFT JOIN {$this->getJoinSql('registrationSession.updatedBy', 'rs', 'uu')}
            LEFT JOIN {$this->getJoinSql('registrationSession.deletedBy', 'rs', 'ud')}
            INNER JOIN {$this->getJoinSql('user', '', 'u')}
        ";
    }

    /**
     * @param PDOStatement $statement
     * @return GenericCollection
     * @throws BaseMapper\DefinitionException
     * @throws BaseMapper\Exception
     */
    protected function hydrateSelect(PDOStatement $statement): GenericCollection
    {
        /** @var SessionMapper $sessionMapper */
        $sessionMapper = $this->getJoinMapper('registrationSession');
        /** @var UserMapper $userMapper */
        $userMapper = $this->getJoinMapper('user');
        $students = $this->createCollection();

        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
            $student = $this->hydrate($row);

            if ($student) {
                $session = $sessionMapper->hydrate($row, 'rs');

                if ($session) {
                    $systemPurpose = $sessionMapper->getJoinMapper('systemPurpose')->hydrate($row, 'sp');

                    if ($systemPurpose) {
                        $systemPurpose->setCategory(
                            $sessionMapper->getJoinMapper('systemPurpose')->getJoinMapper('category')->hydrate($row, 'c')
                        );
                        $session->setSystemPurpose($systemPurpose);
                    }

                    $createdBy = $sessionMapper->getJoinMapper('createdBy')->hydrate($row, 'uc');
                    $updatedBy = $sessionMapper->getJoinMapper('updatedBy')->hydrate($row, 'uu');
                    $deletedBy = $sessionMapper->getJoinMapper('deletedBy')->hydrate($row, 'ud');

                    if ($createdBy) {
                        $session->setCreatedBy($createdBy);
                    }

                    if ($updatedBy) {
                        $session->setUpdatedBy($updatedBy);
                    }

                    if ($deletedBy) {
                        $session->setDeletedBy($deletedBy);
                    }

                    $student->setRegistrationSession($session);
                }

                $user = $userMapper->hydrate($row, 'u');

                if ($user) {
                    $student->setUser($user);
                }

                $students[$student->getId()] = $student;
            }
        }

        return $students;
    }

    /**
     * Retrieve all students for the given registration session's system purpose,
     * including users who do not yet have a session-student record.
     *
     * @param int $registrationSessionId
     * @return Collection
     * @throws BaseMapper\DefinitionException
     * @throws BaseMapper\Exception
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     */
    public function findStudentsForSession(int $registrationSessionId): Collection
    {
        /** @var SessionMapper $sessionMapper */
        $sessionMapper = $this->getJoinMapper('registrationSession');
        /** @var UserMapper $userMapper */
        $userMapper = $this->getJoinMapper('user');

        $registrationSession = $sessionMapper->findById($registrationSessionId);

        if (!$registrationSession || !$registrationSession->getSystemPurpose()?->getId()) {
            return $this->createCollection();
        }

        $statement = $this->getDatabase()->executeQuery("
            SELECT DISTINCT
                {$this->getColumnString('ss')},
                u.id AS user_id
            FROM {$userMapper->getTable()} u
            INNER JOIN {$userMapper->getJoinSql('role', 'u', 'g', true)}
            INNER JOIN user_qualification uq
                ON uq.user_id = u.id
            INNER JOIN lu_qualification q
                ON q.id = uq.qualification_id
                AND q.deleted_at IS NULL
            LEFT JOIN {$this->getTable()} ss
                ON ss.registration_session_id = :registrationSessionId
                AND ss.user_id = u.id
            WHERE u.deleted_at IS NULL
              AND g.group_type = :studentRoleType
              AND q.system_purpose_id = :systemPurposeId
            ORDER BY u.lastname ASC, u.preferred_name ASC, u.firstname ASC, u.id ASC
        ", [
            'registrationSessionId' => $registrationSessionId,
            'studentRoleType' => \Schoolbox\Role::STUDENT,
            'systemPurposeId' => $registrationSession->getSystemPurpose()->getId(),
        ]);

        $rows = [];

        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
            $rows[] = $row;
        }

        if (empty($rows)) {
            return $this->createCollection();
        }

        $userIds = array_map('intval', array_values(array_unique(array_column($rows, 'user_id'))));

        $users = $userMapper->findByPredicates(
            $userMapper->getPredicateBuilder()->in('id', $userIds)
        );

        $usersById = [];

        foreach ($users as $user) {
            $usersById[$user->getId()] = $user;
        }

        $students = $this->createCollection();

        foreach ($rows as $row) {
            $student = $this->hydrate($row, 'ss') ?? $this->createModel();
            $student->setRegistrationSession($registrationSession);

            $userId = (int) $row['user_id'];

            if (isset($usersById[$userId])) {
                $student->setUser($usersById[$userId]);
            }

            if (!$student->getId()) {
                $student->setStatus(0);
            }

            $students->add($student);
        }

        return $students;
    }
}
