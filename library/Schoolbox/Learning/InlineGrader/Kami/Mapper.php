<?php

/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\InlineGrader\Kami;

use Alaress\Database\Predicate\Builder;
use PDOStatement;
use Schoolbox\Assessment\Component\SubmissionBox\Submission\SingleFile;
use Schoolbox\Assessment\Component\SubmissionBoxMapper;
use Schoolbox\Resource\Storage;
use Schoolbox\GenericCollection;
use Schoolbox\Mapper as BaseMapper;

/**
 * Mapper for relation between student submission files and Kami data.
 *
 * @method Model createModel($idValue = null)
 * @method Model findById($identifierValue)
 * @method Model hydrate(array $row, $alias = '', $callback = null)
 * @method GenericCollection|Model[] findByPredicates(Builder $pb, array $options = [])
 * @method GenericCollection|Model[] findByColumn($columnName, $columnValue, $options = [])
 */
class Mapper extends BaseMapper
{
    protected function mapping(): void
    {
        $this->hasTable('kami_upload');
        $this->hasModel(Model::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses(
            'type',
            self::TYPE_STRING,
            self::COLUMN_ENUM,
            null,
            [
                Model::TYPE_STUDENT_SUBMISSION_FILE_ANNOTATION,
                Model::TYPE_SUBMISSION_BOX_WORKSHEET_TEMPLATE,
                Model::TYPE_STUDENT_SUBMISSION_WORKSHEET,
            ],
        );
        $this->joins('student_submission_file_id', SingleFile\Mapper::class, 'id', null, null, null, 'file');
        $this->joins(
            'worksheet_template_submission_box_id',
            SubmissionBoxMapper::class,
            'id',
            null,
            null,
            null,
            'worksheetTemplateSubmissionBox',
        );
        $this->uses('kami_document_id', self::TYPE_STRING);
        $this->uses(
            'status',
            self::TYPE_STRING,
            self::COLUMN_ENUM,
            null,
            [
                Model::STATUS_QUEUED,
                Model::STATUS_UPLOADING,
                Model::STATUS_FAILED,
                Model::STATUS_PROCESSING,
                Model::STATUS_DONE,
                Model::STATUS_ERROR,
            ],
        );
        $this->uses('error_message', self::TYPE_STRING);
        $this->uses('kami_created_at', self::TYPE_DATETIME);
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);
    }


    /**
     * @inheritdoc
     */
    protected function getSelectString(): string
    {
        $submissionFileMapper = $this->getJoinMapper('file');

        return "
            SELECT
                {$this->getColumnString()},
                {$submissionFileMapper->getColumnString('f')},
                {$submissionFileMapper->getJoinColumnString('storage', 's')}
            FROM {$this->getDatabase()->getQuoter()->quoteIdentifier($this->getTable())}
            INNER JOIN {$this->getJoinSql('file', '', 'f', true)}
            INNER JOIN {$submissionFileMapper->getJoinSql('storage', 'f', 's', true)}
        ";
    }


    /**
     * @param PDOStatement $statement
     * @return GenericCollection|Model[]
     * @throws BaseMapper\DefinitionException If join $name isn't on this mapper
     */
    protected function hydrateSelect(PDOStatement $statement): GenericCollection
    {
        /**
         * @var SingleFile\Mapper $submissionFileMapper
         * @var Storage\Mapper    $storageMapper
         */
        $collection           = $this->createCollection();
        $submissionFileMapper = $this->getJoinMapper('file');
        $storageMapper        = $submissionFileMapper->getJoinMapper('storage');

        /** @noinspection PhpAssignmentInConditionInspection */
        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $model          = $this->hydrate($row);
            $submissionFile = $submissionFileMapper->hydrate($row, 'f');
            $submissionFile->setStorage($storageMapper->hydrate($row, 's'));
            $model->setStudentSubmissionFile($submissionFile);
            $collection[$model->getId()] = $model;
        }
        return $collection;
    }


    /**
     * Returns the kami model for the specified file if one exists
     *
     * @param SingleFile\Model $file
     * @param array            $options
     * @return null|Model
     */
    public function findForSubmissionFile(SingleFile\Model $file, array $options = []): ?Model
    {
        return $this->findByColumnList(['student_submission_file_id' => $file->getId()], $options)->first();
    }
}
