<?php

/**
 * IB Group-Subjects Junction Mapper
 * @author    Haris Ali
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\IB\GroupSubject;

use PDO;
use PDOStatement;
use Schoolbox\GenericCollection;
use Schoolbox\IB\Subject\Mapper as SubjectMapper;
use Schoolbox\Mapper as BaseMapper;

/**
 * Class Mapper
 * @method Model createModel($idValue = null)
 * @method Model hydrate(array $row, $alias = '', $callback = null)
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
        $this->uses('subject_id', self::TYPE_INTEGER, null, 'subjectId');
        $this->uses('level', self::TYPE_STRING);
        $this->joins('subject_id', SubjectMapper::class, 'id', null, null, 'subject', 'subject');
    }

    /**
     * @inheritdoc
     */
    protected function getDefaultOrderByParams(): array
    {
        return [
            'group_id' => 'ASC',
            'subject_id' => 'ASC',
            'level' => 'ASC',
        ];
    }

    /**
     * @inheritdoc
     */
    protected function getSelectString(): string
    {
        $subjectMapper = $this->getJoinMapper('subject');

        return "
            SELECT
                {$this->getColumnString()},
                {$subjectMapper->getColumnString('s')}
        ";
    }

    /**
     * @inheritdoc
     */
    protected function getFromString(): string
    {
        return "
            FROM `{$this->table}`
            INNER JOIN {$this->getJoinSql('subject', '', 's', true)}
        ";
    }

    /**
     * @inheritdoc
     */
    protected function hydrateSelect(PDOStatement $statement): GenericCollection
    {
        $subjectMapper = $this->getJoinMapper('subject');
        $collection    = $this->createCollection();

        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
            $groupSubject = $this->hydrate($row);
            $groupSubject->setSubject($subjectMapper->hydrate($row, 's'));
            $collection[$groupSubject->getId()] = $groupSubject;
        }

        return $collection;
    }

    /**
     * Find group-subject relationships for a given group ID, with subjects hydrated.
     *
     * @param int $groupId
     * @return GenericCollection|Model[]
     */
    public function findByGroupId(int $groupId): GenericCollection
    {
        $pb = $this->getPredicateBuilder()->equals('group_id', $groupId);

        return $this->findByPredicates($pb, [
            'order' => ['s.name' => 'ASC', 'level' => 'ASC'],
        ]);
    }
}
