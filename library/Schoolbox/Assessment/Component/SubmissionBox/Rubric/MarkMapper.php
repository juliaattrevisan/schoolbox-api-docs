<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Assessment\Component\SubmissionBox\Rubric;

use PDO;
use PDOStatement;
use Schoolbox\Collection;
use Schoolbox\GenericCollection;
use Schoolbox\Mapper;
use Schoolbox\User;
use Schoolbox\UserMapper;

/**
 * Class MarkMapper
 *
 * @package Schoolbox\Assessment\Component\SubmissionBox\Rubric
 * @method Mark hydrate(array $row, $alias = '', $callback = null)
 * @method Mark createModel($idValue = null)
 * @method GenericCollection|Mark[] findByColumnList($columnList, $options = array())
 */
class MarkMapper extends Mapper
{

    /**
     * Setup method. Implementing classes to define their SQL map here.
     *
     * @return void
     */
    protected function mapping()
    {
        $this->hasTable('rubric_mark');
        $this->hasModel(Mark::class);

        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('valid', self::TYPE_BOOLEAN);
        $this->joins('student_id', UserMapper::class, 'id', null, null, null, 'user');
        $this->joins('descriptor_id', DescriptorMapper::class, 'id', null, null, null, 'descriptor');

        // this cannot be self::COLUMN_CREATED_TIMESTAMP because the mapper will then ignore input values when saving
        $this->uses('tstamp', self::TYPE_DATETIME, null, 'createdAt');
    }


    /**
     * @return string
     */
    protected function getSelectString()
    {
        return "
            SELECT  {$this->getColumnString()},
                    {$this->getJoinColumnString('user', 'u')},
                    {$this->getJoinColumnString('descriptor', 'd')}
            FROM {$this->getTable()}
            LEFT JOIN {$this->getJoinSql('user', '', 'u')}
            LEFT JOIN {$this->getJoinSql('descriptor', '', 'd')}
        ";
    }


    /**
     * @param PDOStatement $statement
     * @return Collection|Mark[]
     */
    protected function hydrateSelect(PDOStatement $statement)
    {
        $marks = $this->createCollection();
        $students = $this->getJoinMapper('user')->createCollection();
        $descriptors = $this->getJoinMapper('descriptor')->createCollection();

        $studentKey = "u.{$this->getJoinMapper('user')->getIdentifierColumn()}";
        $descriptorKey = "d.{$this->getJoinMapper('descriptor')->getIdentifierColumn()}";

        while ($r = $statement->fetch(PDO::FETCH_ASSOC)) {

            $userId = $r[$studentKey];
            if(!$students->offsetExists($userId)){
                $students->setOffset($userId, $this->hydrateJoin($r, 'user', 'u'));
            }
            /** @var User $student */
            $student = $students->getOffset($userId);

            $descriptorId = $r[$descriptorKey];
            if(!$descriptors->offsetExists($descriptorId)){
                $descriptors->setOffset($descriptorId, $this->hydrateJoin($r, 'descriptor', 'd'));
            }
            /** @var Descriptor $descriptor */
            $descriptor = $descriptors->getOffset($descriptorId);

            $mark = $this->hydrate($r);
            $mark->setStudent($student);
            $mark->setDescriptor($descriptor);
            $marks->add($mark);
        }

        return $marks;
    }
}
