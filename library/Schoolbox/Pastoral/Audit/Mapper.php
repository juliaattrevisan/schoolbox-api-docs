<?php

/**
 * This maintains an audit trail of every record a staff member views, and all the searches that they perform (and the
 * results that these searches returned)
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Pastoral\Audit;

use PDOStatement;
use Schoolbox\Collection;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Pastoral\Mapper as PastoralMapper;
use Schoolbox\Pastoral\Model as Pastoral;
use Schoolbox\RoleMapper;
use Schoolbox\User;
use Schoolbox\UserMapper;

/**
 * Class Mapper
 * @method Model createModel($idValue = null)
 * @method Model hydrate(array $row, $alias = '', $callback = null)
 *
 * @package Schoolbox\Pastoral\Audit*
 */
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
            FROM          {$this->database->getQuoter()->quoteIdentifier($this->table)}
            JOIN          {$this->getJoinSql('user', '', 'u', true)}
            LEFT JOIN     {$this->getJoinSql('student', '', 's', true)}
            LEFT JOIN     {$this->getJoinSql('pastoral', '', 'p', true)}
        ";
    }

    /**
     * @inheritdoc
     */
    protected function hydrateSelect(PDOStatement $statement)
    {
        // Return audit records fully hydrated by default

        $collection = $this->createCollection();

        foreach ($statement as $row) {
            $model = $this->hydrate($row);

            /** @var User $user */
            $user = $this->hydrateJoin($row, 'user', 'u');
            $model->setUser($user);

            /** @var null|User $student */
            $student = $this->hydrateJoin($row, 'student', 's');
            $model->setStudent($student);

            /** @var null|Pastoral $pastoral */
            $pastoral = $this->hydrateJoin($row, 'pastoral', 'p');
            $model->setPastoral($pastoral);

            $collection->add($model);
        }

        return $collection;
    }


    /**
     * Return list of recently viewed students. This is used to display recently viewed students on the pastoral
     * dashboard
     *
     * @param User $viewer
     * @param int  $count  Number of records to return
     * @param int  $offset Offset from start of results
     *
     * @return Collection|User[]
     * @throws BaseMapper\DefinitionException
     * @throws BaseMapper\Exception
     */
    public function findRecentlyViewedStudentsForUser(User $viewer, int $count = 5, int $offset = 0): Collection
    {
        /** @var UserMapper $userMapper */
        $userMapper = $this->getJoinMapper('student');

        /** @var RoleMapper $roleMapper */
        $roleMapper = $userMapper->getJoinMapper('role');

        $statement = $this->getDatabase()->prepare("
            SELECT      {$this->getJoinColumnString('student', 's')},
                        {$userMapper->getJoinColumnString('role', 'r')}
            FROM        {$this->database->getQuoter()->quoteIdentifier($this->table)}
            INNER JOIN  {$this->getJoinSql('student', '', 's')}
            LEFT  JOIN  {$userMapper->getJoinSql('role', 's', 'r')}
            WHERE       {$this->table}.`user_id` = :userId
            GROUP BY    `s`.`id`
            ORDER BY    MAX({$this->table}.created_at) DESC
            LIMIT       {$count}
            OFFSET      {$offset}
        ");

        $statement->execute([
            'userId' =>  $viewer->getId(),
        ]);

        $students = $userMapper->createCollection();

        foreach ($statement as $row) {
            // Hydrate the student and their role for display purposes
            $student = $userMapper->hydrate($row, 's')->setRole($roleMapper->hydrate($row, 'r'));
            $students[$student->getId()] = $student;
        }

        return $students;
    }

    /**
     * @inheritDoc
     */
    public function raiseNotification($object, $verb, $previous = null)
    {
        return true;
    }
}
