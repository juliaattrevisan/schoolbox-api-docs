<?php

/**
 * IB Group-Subjects Junction Mapper
 * @author    Haris Ali
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\IB\Group\Subject;

use PDO;
use PDOStatement;
use Schoolbox\GenericCollection;
use Schoolbox\IB\Group\Subject\Language\Mapper as GroupSubjectLanguageMapper;
use Schoolbox\IB\Group\Mapper as IBGroupMapper;
use Schoolbox\IB\Subject\Mapper as SubjectMapper;
use Schoolbox\Mapper as BaseMapper;

/**
 * Class Mapper
 * @method Model createModel($idValue = null)
 * @method ?Model hydrate(array $row, $alias = '', $callback = null)
 * @method GenericCollection|Model[] findByColumnList($columnList, $options = [])
 *
 * @package Schoolbox\IB\GroupSubject
 */
class Mapper extends BaseMapper
{
    /**
     * @inheritdoc
     */
    protected function mapping()
    {
        $this->hasTable('ib_group_subjects');
        $this->hasModel(Model::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('group_id', self::TYPE_INTEGER, null, 'groupId');
        $this->uses('level', self::TYPE_STRING);
        $this->joins('subject_id', SubjectMapper::class, 'id', null, null, 'subject', 'subject');
        $this->joins('group_id', IBGroupMapper::class, 'id', null, null, 'group', 'group');

        $this->joinsReverseMulti(
            'group_subject_id',
            GroupSubjectLanguageMapper::class,
            'languages',
            'languages'
        );
    }

    /**
     * @inheritdoc
     */
    protected function getDefaultOrderByParams(): array
    {
        return [
            'group_id' => 'ASC',
            'level' => 'ASC',
        ];
    }

    /**
     * @inheritdoc
     */
    protected function getSelectString(): string
    {
        $subjectMapper = $this->getJoinMapper('subject');
        $groupMapper   = $this->getJoinMapper('group');
        $languageMapper   = $this->getJoinMapper('languages');

        return "
            SELECT
                {$this->getColumnString()},
                {$subjectMapper->getColumnString('s')},
                {$groupMapper->getColumnString('g')},
                {$languageMapper->getColumnString('l')}
        ";
    }

    /**
     * @inheritdoc
     */
    protected function getFromString(): string
    {
        return "
            FROM `{$this->table}`
            INNER JOIN {$this->getJoinSql('subject', '', 's')}
            INNER JOIN {$this->getJoinSql('group', '', 'g')}
            LEFT JOIN {$this->getJoinSql('languages', '', 'l')}
        ";
    }

    /**
     * @inheritdoc
     */
    protected function hydrateSelect(PDOStatement $statement): GenericCollection
    {
        $subjectMapper = $this->getJoinMapper('subject');
        $groupMapper   = $this->getJoinMapper('group');
        $languageMapper   = $this->getJoinMapper('languages');
        $collection    = $this->createCollection();

        $groupSubjectIds = [];
        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
            $groupSubject = $this->hydrate($row);
            $groupSubject->setSubject($subjectMapper->hydrate($row, 's'));
            $groupSubject->setGroup($groupMapper->hydrate($row, 'g'));
            $groupSubjectIds[] = $groupSubject->getId();
            $collection[$groupSubject->getId()] = $groupSubject;
        }

        /* Hydrates the languages
         */
        $languages = $languageMapper
            ->findByColumn('group_subject_id', array_unique($groupSubjectIds))
            ->groupByCallback(fn ($l) => $l->getGroupSubject()->getId());
        /** @var Model $groupSubject */
        foreach ($collection as $id => $groupSubject) {
            $groupSubject->setLanguages($languages[$id] ?? $languageMapper->createCollection());
        }

        return $collection;
    }
}
