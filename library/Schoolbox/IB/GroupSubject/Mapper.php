<?php

/**
 * IB Group-Subjects Junction Mapper
 * @author    Haris Ali
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\IB\GroupSubject;

use Schoolbox\GenericCollection;
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
     * Find group-subject relationships by group ID with subject details
     * @param int $groupId
     * @return array
     */
    public function findByGroupIdWithSubjects(int $groupId): array
    {
        $sql = "
            SELECT 
                gs.id,
                gs.group_id,
                gs.subject_id,
                gs.level,
                s.name as subject_name
            FROM ib_group_subjects gs
            INNER JOIN lu_ib_subjects s ON gs.subject_id = s.id
            WHERE gs.group_id = :groupId
            ORDER BY s.name ASC, gs.level ASC
        ";

        $statement = $this->getDatabase()->prepare($sql);
        $statement->bindValue(':groupId', $groupId, \PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll(\PDO::FETCH_OBJ);
    }
}
