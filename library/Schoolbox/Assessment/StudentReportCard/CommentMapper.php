<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Assessment\StudentReportCard;

use Schoolbox\Collection;
use Schoolbox\Mapper;

class CommentMapper extends Mapper
{

    protected function mapping()
    {
        // register table and model
        $this->hasTable('reportcard_comment');
        $this->hasModel(Comment::class);

        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('body', self::TYPE_STRING);
        $this->uses('student_visibility', self::TYPE_BOOLEAN);
        $this->uses('parent_visibility', self::TYPE_BOOLEAN);
        $this->uses('created', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP, 'createdAt');
        $this->uses('modified', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP, 'updatedAt');
        $this->uses('deleted', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE, 'deletedAt');


        $this->joins('category_id', \Schoolbox\Resource\Folder\Mapper::class, 'id', null, null, 'category', 'category');
        $this->joins('commenter_id', \Schoolbox\UserMapper::class, 'id', null, null, 'commenter', 'commenter');
        $this->joins('student_id', \Schoolbox\UserMapper::class, 'id', null, null, 'student', 'student');
    }

    public function findByColumn($columnName, $columnValue, $options = array())
    {
        $commentCollection = parent::findByColumn($columnName, $columnValue, $options);

        $categoryMapper = $this->getJoinMapper('category');
        $studentMapper = $this->getJoinMapper('student');
        $commenterMapper = $this->getJoinMapper('commenter');

        foreach ($commentCollection as &$comment) {
            /** @var Comment $comment */
            $comment->setCategory($categoryMapper->findById($comment->getCategory()->getId()));
            $comment->setStudent($studentMapper->findById($comment->getStudent()->getId()));
            $comment->setCommenter($commenterMapper->findById($comment->getCommenter()->getId()));
        }

        return $commentCollection;
    }

    public function findById($identifierValue)
    {
        return $this->findByColumn('id', $identifierValue)->first();
    }


    public function findByStudentAndCategory($studentId, $categoryId, $includeDeletions = false)
    {
        $categoryMapper = $this->getJoinMapper('category');
        $studentMapper = $this->getJoinMapper('student');
        $commenterMapper = $this->getJoinMapper('commenter');
        $roleMapper = $commenterMapper->getJoinMapper('role');

        $query = "
            SELECT
                {$this->getColumnString('com')},
                {$categoryMapper->getColumnString('cat')},
                {$studentMapper->getColumnString('su')},
                {$roleMapper->getColumnString('r')},
                {$commenterMapper->getColumnString('cu')}
            FROM {$this->getTable()} as `com`
            LEFT JOIN {$this->getJoinSql('category','com','cat')}
            LEFT JOIN {$this->getJoinSql('student','com','su')}
            LEFT JOIN {$this->getJoinSql('commenter','com','cu', true)}
            LEFT JOIN {$commenterMapper->getJoinSql('role','cu','r')}
            WHERE com.student_id = ?
            AND com.category_id = ?
        ";

        if (!$includeDeletions) {
            $query .= 'AND com.deleted IS NULL';
        }

        $query .= ' ORDER BY created DESC';

        $stmt = $this->database->prepare($query);
        $stmt->execute(array($studentId, $categoryId));

        $commentCollection = new Collection();

        while ($record = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            /** @var Comment $commentObject */
            $commentObject = $this->hydrate($record, 'com');
            $commentObject->setCategory($categoryMapper->hydrate($record, 'cat'));
            $commentObject->setStudent($studentMapper->hydrate($record, 'su'));
            $commentObject->setCommenter($commenterMapper->hydrate($record, 'cu'));
            $commentObject->getCommenter()->setRole($roleMapper->hydrate($record, 'r'));
            $commentCollection->add($commentObject);
        }

        return $commentCollection;
    }
}
