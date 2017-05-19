<?php
/**
 * This maintains an audit trail of every record a staff member views, and all the searches that they perform (and the
 * results that these searches returned)
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Pastoral\Audit;

use PDOStatement;
use Schoolbox\Collection;
use Schoolbox\Pastoral\Model as Pastoral;
use Schoolbox\Pastoral\Mapper as PastoralMapper;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Role;
use Schoolbox\User;
use Schoolbox\UserMapper;

class Mapper extends BaseMapper
{
    /**
     * @inheritdoc
     */
    protected function mapping()
    {
        $this->hasTable('pastoral_audit');
        $this->hasModel(Model::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('search', self::TYPE_STRING);
        $this->uses('report_id', self::TYPE_INTEGER);
        $this->uses('search_result', self::TYPE_STRING);
        $this->joins('pastoral_id', PastoralMapper::class, 'id', null, null, 'pastoral', 'pastoral');
        $this->joins('user_id', UserMapper::class, 'id', null, null, 'user', 'user');
        $this->joins('student_id', UserMapper::class, 'id', null, null, 'student', 'student');
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
    }

    /**
     * @inheritdoc
     */
    protected function getSelectString()
    {
        return "
            
            SELECT        {$this->getColumnString()},
                          {$this->getJoinColumnString('user', 'u')},
                          {$this->getJoinColumnString('student', 's')},
                          {$this->getJoinColumnString('pastoral', 'p')}
            FROM          {$this->database->quoteIdentifier($this->table)}
            LEFT JOIN     {$this->getJoinSql('user', '', 'u')}
            LEFT JOIN     {$this->getJoinSql('student', '', 's')}
            LEFT JOIN     {$this->getJoinSql('pastoral', '', 'p')}
        ";
    }

    /**
     * @inheritdoc
     */
    protected function hydrateSelect(PDOStatement $statement)
    {
        // Return audit records fully hydrated by default

        $collection = new Collection();

        foreach ($statement as $row) {

            /** @var Model $model */
            $model = $this->hydrate($row);

            /** @var User $user */
            $user = $this->hydrateJoin($row, 'user', 'u');
            $model->setUser($user);

            /** @var User $student */
            $student = $this->hydrateJoin($row, 'student', 's');
            $model->setStudent($student);

            /** @var Pastoral $pastoral */
            $pastoral = $this->hydrateJoin($row, 'pastoral', 'p');
            $model->setPastoral($pastoral);

            $collection->add($model);
        }

        return $collection;
    }

    /**
     * Return list of recently viewed students. This is used to display recently viewed students on the pastoral dashboard
     *
     * @param int $count Number of records to return
     * @param int $offset Offset from start of results
     * @return Collection
     */
    public function findRecentStudents($count = 5, $offset = 0)
    {
        $records = new Collection();

        $statement = $this->getDatabase()->prepare("
            
            SELECT DISTINCT   MAX(`created_at`) as `created_at`,
                              {$this->getJoinColumnString('student', 's')},
                              {$this->getJoinMapper('student')->getJoinColumnString('role', 'r')}
            FROM              {$this->database->quoteIdentifier($this->table)}
            LEFT JOIN         {$this->getJoinSql('student', '', 's')}
            LEFT JOIN         {$this->getJoinMapper('student')->getJoinSql('role', 's', 'r')}
            WHERE             `s`.`id` IS NOT NULL AND
                              `pastoral_audit`.`user_id` = :userId
            GROUP BY          `s`.`id`
            ORDER BY          MAX(`created_at`) DESC
            LIMIT             {$count}
            OFFSET            {$offset}
        ");

        $statement->execute([
            'userId' => $this->getSessionUser()->getId()
        ]);

        foreach ($statement as $row) {

            // Hydrate the student and their role for display purposes

            /** @var Model $model */
            $model = $this->hydrate($row);

            /** @var User $student */
            $student = $this->hydrateJoin($row, 'student', 's');

            /** @var Role $role */
            $role = $this->getJoinMapper('student')->hydrateJoin($row, 'role', 'r');
            $student->setRole($role);

            $model->setStudent($student);

            $records->add($this->hydrateJoin($row, 'student', 's'));
        }

        return $records;
    }
}