<?php

/**
 * IB registration session student subject language mapper
 *
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\IB\Registrations\Sessions\Student\Subject\Language;

use PDO;
use PDOStatement;
use Schoolbox\GenericCollection;
use Schoolbox\IB\Group\Subject\Mapper as GroupSubjectMapper;
use Schoolbox\IB\Registrations\Sessions\Student\Mapper as RegistrationSessionStudentMapper;
use Schoolbox\Language\Mapper as LanguageMapper;
use Schoolbox\Mapper as BaseMapper;

/**
 * Class Mapper
 * @method Model createModel($idValue = null)
 * @method ?Model hydrate(array $row, $alias = '', $callback = null)
 * @method GenericCollection|Model[] findByColumnList($columnList, $options = [])
 *
 * @package Schoolbox\IB\Registrations\Sessions\Student\Subject\Language
 */
class Mapper extends BaseMapper
{
    /**
     * @inheritdoc
     */
    protected function mapping()
    {
        $this->hasTable('ib_registration_session_student_subject_language');
        $this->hasModel(Model::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->joins(
            'registration_session_student_id',
            RegistrationSessionStudentMapper::class,
            'id',
            null,
            null,
            'registrationSessionStudent',
            'registrationSessionStudent'
        );
        $this->joins('group_subject_id', GroupSubjectMapper::class, 'id', null, null, 'groupSubject', 'groupSubject');
        $this->joins('language_id', LanguageMapper::class, 'id', null, null, 'language', 'language');
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
    }

    /**
     * @inheritdoc
     */
    protected function getSelectString(): string
    {
        return "
            SELECT {$this->getColumnString()},
                   {$this->getJoinColumnString('registrationSessionStudent', 'rss')},
                   {$this->getJoinColumnString('groupSubject', 'gs')},
                   {$this->getJoinColumnString('language', 'l')}
        ";
    }

    /**
     * @inheritdoc
     */
    protected function getFromString()
    {
        return "
            FROM `{$this->table}`
            INNER JOIN {$this->getJoinSql('registrationSessionStudent', '', 'rss')}
            INNER JOIN {$this->getJoinSql('groupSubject', '', 'gs')}
            INNER JOIN {$this->getJoinSql('language', '', 'l')}
        ";
    }

    /**
     * @inheritdoc
     */
    protected function hydrateSelect(PDOStatement $statement): GenericCollection
    {
        /** @var RegistrationSessionStudentMapper $registrationSessionStudentMapper */
        $registrationSessionStudentMapper = $this->getJoinMapper('registrationSessionStudent');
        /** @var GroupSubjectMapper $groupSubjectMapper */
        $groupSubjectMapper = $this->getJoinMapper('groupSubject');
        /** @var LanguageMapper $languageMapper */
        $languageMapper = $this->getJoinMapper('language');
        $collection = $this->createCollection();

        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
            $selection = $this->hydrate($row);

            if (!$selection) {
                continue;
            }

            $registrationSessionStudent = $registrationSessionStudentMapper->hydrate($row, 'rss');
            $groupSubject = $groupSubjectMapper->hydrate($row, 'gs');
            $language = $languageMapper->hydrate($row, 'l');

            if (!$registrationSessionStudent?->getId() || !$groupSubject?->getId() || !$language?->getId()) {
                continue;
            }

            $selection
                ->setRegistrationSessionStudent($registrationSessionStudent)
                ->setGroupSubject($groupSubject)
                ->setLanguage($language);

            $collection[$selection->getId()] = $selection;
        }

        return $collection;
    }

    /**
     * @param int[] $registrationSessionStudentIds
     * @return array<int, GenericCollection|Model[]>
     * @throws BaseMapper\Exception
     */
    public function findByRegistrationSessionStudentIds(array $registrationSessionStudentIds): array
    {
        $registrationSessionStudentIds = array_values(array_unique(array_filter(
            array_map('intval', $registrationSessionStudentIds),
            static fn(int $id): bool => $id > 0
        )));

        if ($registrationSessionStudentIds === []) {
            return [];
        }

        $selectionsByStudentId = [];
        $selections = $this->findByColumn('registration_session_student_id', $registrationSessionStudentIds, [
            'order' => [
                'registration_session_student_id' => 'ASC',
                'group_subject_id' => 'ASC',
            ],
        ]);

        foreach ($selections as $selection) {
            $studentId = $selection->getRegistrationSessionStudent()?->getId();

            if ($studentId === null) {
                continue;
            }
            if (!isset($selectionsByStudentId[$studentId])) {
                $selectionsByStudentId[$studentId] = new GenericCollection(Model::class);
            }

            $selectionsByStudentId[$studentId]->add($selection);
        }

        return $selectionsByStudentId;
    }
}
