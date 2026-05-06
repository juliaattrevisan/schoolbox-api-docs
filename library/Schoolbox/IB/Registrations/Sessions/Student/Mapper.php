<?php

/**
 * IB Registration Session Student Mapper
 * @author    Haris Ali
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\IB\Registrations\Sessions\Student;

use Alaress\Database\Predicate;
use PDO;
use PDOStatement;
use Schoolbox\Collection;
use Schoolbox\External\SubjectMapper;
use Schoolbox\GenericCollection;
use Schoolbox\IB\Group\Model as IBGroupModel;
use Schoolbox\IB\Group\Subject\Language\Model as GroupSubjectLanguageModel;
use Schoolbox\IB\Group\Subject\Model as GroupSubjectModel;
use Schoolbox\IB\Registrations\Sessions\Mapper as SessionMapper;
use Schoolbox\IB\Subject\Model as IBSubjectModel;
use Schoolbox\Language\Model as LanguageModel;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\User;
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
    private const CORE_GROUP_NAME = 'Core';

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

        // Start from qualified student users so students without an existing session-student row are included.
        // Exclude users who already have an IB candidate number or are registered in another session for this system purpose.
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
              AND (u.ib_candidate_number IS NULL OR u.ib_candidate_number = '')
              AND g.group_type = :studentRoleType
              AND q.system_purpose_id = :systemPurposeId
              AND NOT EXISTS (
                  SELECT 1
                  FROM {$this->getTable()} other_ss
                  INNER JOIN {$sessionMapper->getTable()} other_rs
                      ON other_rs.id = other_ss.registration_session_id
                  WHERE other_ss.user_id = u.id
                    AND other_rs.id != :registrationSessionId
                    AND other_rs.deleted_at IS NULL
                    AND other_rs.system_purpose_id = :systemPurposeId
              )
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

        $ibGroupSubjectsByUserId = $this->findIbGroupSubjectsForUsers($usersById);
        $students = $this->createCollection();

        foreach ($rows as $row) {
            $student = $this->hydrate($row, 'ss') ?? $this->createModel();
            $student->setRegistrationSession($registrationSession);

            $userId = (int) $row['user_id'];

            if (isset($usersById[$userId])) {
                $student->setUser($usersById[$userId]);
            }

            $student->setIbGroupSubjects(
                $ibGroupSubjectsByUserId[$userId] ?? new GenericCollection(GroupSubjectModel::class)
            );

            if (!$student->getId()) {
                $student->setStatus(Model::STATUS_PREPARATION);
            }

            $students->add($student);
        }

        return $students;
    }

    /**
     * @param array<int, User> $usersById
     * @return array<int, GenericCollection>
     */
    private function findIbGroupSubjectsForUsers(array $usersById): array
    {
        if (empty($usersById)) {
            return [];
        }

        $classCodesByUserId = $this->findMatchedFolderCodesForUsers($usersById);
        $classCodes = [];

        foreach ($classCodesByUserId as $userClassCodes) {
            foreach ($userClassCodes as $classCode) {
                $classCodes[$classCode] = $classCode;
            }
        }

        $rowsByClassCode = !empty($classCodes)
            ? $this->findIbGroupSubjectRowsByClassCode(array_values($classCodes))
            : [];
        $coreRows = $this->findCoreIbGroupSubjectRows();
        $groupSubjectsByUserId = [];

        foreach (array_keys($usersById) as $userId) {
            $groupSubjects = new GenericCollection(GroupSubjectModel::class);

            foreach ($coreRows as $row) {
                $this->addIbGroupSubjectRow($groupSubjects, $row);
            }

            $userClassCodes = $classCodesByUserId[$userId] ?? [];

            foreach ($userClassCodes as $classCode) {
                foreach ($rowsByClassCode[$classCode] ?? [] as $row) {
                    $this->addIbGroupSubjectRow($groupSubjects, $row);
                }
            }

            if ($groupSubjects->count() > 0) {
                $groupSubjectsByUserId[$userId] = $groupSubjects->reindex();
            }
        }

        return $groupSubjectsByUserId;
    }

    /**
     * @param array<int, User> $usersById
     * @return array<int, string[]>
     */
    private function findMatchedFolderCodesForUsers(array $usersById): array
    {
        /** @var array<int, array<string, string>> $cachedClassCodesByUserId */
        static $cachedClassCodesByUserId = [];

        $classCodesByUserId = [];
        $uncachedUsersById = [];

        foreach ($usersById as $userId => $user) {
            if (array_key_exists($userId, $cachedClassCodesByUserId)) {
                $classCodesByUserId[$userId] = $cachedClassCodesByUserId[$userId];
                continue;
            }

            $uncachedUsersById[$userId] = $user;
        }

        if (!empty($uncachedUsersById)) {
            $subjectMapper = new SubjectMapper();

            foreach ($uncachedUsersById as $userId => $user) {
                $cachedClassCodesByUserId[$userId] = [];

                foreach ($subjectMapper->findAllForUser($user, null) as $subject) {
                    $classCode = trim((string) ($subject['matchedCode'] ?? $subject['code'] ?? ''));

                    if ($classCode !== '') {
                        $cachedClassCodesByUserId[$userId][$classCode] = $classCode;
                    }
                }

                $classCodesByUserId[$userId] = $cachedClassCodesByUserId[$userId];
            }
        }

        return $classCodesByUserId;
    }

    /**
     * @param string[] $classCodes
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function findIbGroupSubjectRowsByClassCode(array $classCodes): array
    {
        $bindings = Predicate\Builder::getBindings($classCodes, 'classCode');
        $statement = $this->getDatabase()->query(
            "
                SELECT
                    gsl.id AS group_subject_language_id,
                    gsl.class_code,
                    gs.id AS group_subject_id,
                    gs.group_id,
                    gs.level,
                    g.name AS group_name,
                    g.sequence AS group_sequence,
                    s.id AS subject_id,
                    s.name AS subject_name,
                    l.id AS language_id,
                    l.code AS language_code,
                    l.name AS language_name
                FROM ib_group_subject_languages gsl
                INNER JOIN ib_group_subjects gs
                    ON gs.id = gsl.group_subject_id
                INNER JOIN lu_ib_groups g
                    ON g.id = gs.group_id
                INNER JOIN lu_ib_subjects s
                    ON s.id = gs.subject_id
                INNER JOIN lu_language l
                    ON l.id = gsl.language_id
                WHERE gsl.class_code IN ({$bindings->getPlaceholder()})
                ORDER BY g.sequence ASC, s.name ASC, gs.level ASC, l.name ASC, gsl.class_code ASC
            ",
            $bindings->getParams()
        );
        $rowsByClassCode = [];

        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
            $rowsByClassCode[(string) $row['class_code']][] = $row;
        }

        return $rowsByClassCode;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function findCoreIbGroupSubjectRows(): array
    {
        $statement = $this->getDatabase()->query(
            "
                SELECT
                    gsl.id AS group_subject_language_id,
                    gsl.class_code,
                    gs.id AS group_subject_id,
                    gs.group_id,
                    gs.level,
                    g.name AS group_name,
                    g.sequence AS group_sequence,
                    s.id AS subject_id,
                    s.name AS subject_name,
                    l.id AS language_id,
                    l.code AS language_code,
                    l.name AS language_name
                FROM ib_group_subjects gs
                INNER JOIN lu_ib_groups g
                    ON g.id = gs.group_id
                INNER JOIN lu_ib_subjects s
                    ON s.id = gs.subject_id
                LEFT JOIN ib_group_subject_languages gsl
                    ON gsl.group_subject_id = gs.id
                LEFT JOIN lu_language l
                    ON l.id = gsl.language_id
                WHERE g.name = :groupName
                ORDER BY g.sequence ASC, s.name ASC, l.name ASC, gsl.class_code ASC
            ",
            [
                'groupName' => self::CORE_GROUP_NAME,
            ]
        );

        /** @var array<int, array<string, mixed>> $rows */
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        return $rows;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function addIbGroupSubjectRow(GenericCollection $groupSubjects, array $row): void
    {
        $groupSubjectId = (int) $row['group_subject_id'];

        if (!$groupSubjects->offsetExists($groupSubjectId)) {
            $groupSubject = (new GroupSubjectModel())
                ->setId($groupSubjectId)
                ->setGroupId((int) $row['group_id'])
                ->setLevel($row['level'] !== null ? (string) $row['level'] : null)
                ->setGroup(
                    (new IBGroupModel())
                        ->setId((int) $row['group_id'])
                        ->setName((string) $row['group_name'])
                        ->setSequence((int) $row['group_sequence'])
                )
                ->setSubject(
                    (new IBSubjectModel())
                        ->setId((int) $row['subject_id'])
                        ->setName((string) $row['subject_name'])
                )
                ->setLanguages(new GenericCollection(GroupSubjectLanguageModel::class));

            $groupSubjects[$groupSubjectId] = $groupSubject;
        }

        /** @var GroupSubjectModel $groupSubject */
        $groupSubject = $groupSubjects[$groupSubjectId];

        if ($row['group_subject_language_id'] === null) {
            return;
        }

        $groupSubject->getLanguages()->add(
            (new GroupSubjectLanguageModel())
                ->setId((int) $row['group_subject_language_id'])
                ->setGroupSubject($groupSubject)
                ->setLanguage(
                    (new LanguageModel())
                        ->setId((int) $row['language_id'])
                        ->setCode((string) $row['language_code'])
                        ->setName((string) $row['language_name'])
                )
                ->setClassCode((string) $row['class_code'])
        );
    }
}
