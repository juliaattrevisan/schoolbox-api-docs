<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\DueWork\Response\File\Turnitin;

use Schoolbox\Assessment\Component\SubmissionBox\Submission\SingleFile\Model as SubmissionFile;
use Schoolbox\Learning\Turnitin\ValidFile;

/**
 * Transacts Turnitin models into the turnitin_report db table
 *
 * @method Model createModel($idValue = null)
 * @method Model findById($identifierValue)
 * @method Model findOneByColumn($columnName, $columnValue, array $options = [])
 */
class Mapper extends \Schoolbox\Mapper
{
    /**
     * @inheritDoc
     */
    protected function mapping(): void
    {
        $this->hasTable('turnitin_report');
        $this->hasModel(Model::class);

        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('status', self::TYPE_STRING);
        $this->uses('error_code', self::TYPE_STRING);
        $this->uses('error_message', self::TYPE_STRING);
        $this->uses('student_submission_file_id', self::TYPE_INTEGER);
        $this->uses('turnitin_submission_id', self::TYPE_STRING);
        $this->uses('overall_match_percentage', self::TYPE_DECIMAL);
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);
    }


    /**
     * Save a stub record in the db so an async script can come along and process it
     * if it doesn't already commence via the controller's detached function
     *
     * @param SubmissionFile $file
     * @return Model|null
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    public function saveStub(SubmissionFile $file): ?Model
    {
        /* Invalid file? Do nothing, exit early
         */
        if (!(new ValidFile())->isSatisfiedBy($file)) {
            return null;
        }

        /* Otherwise save a stub record for later
         */
        $now = \Alaress_Date_Format::now();

        $model = $this->createModel()
            ->setStatus(Model::STATUS_TRANSMIT_PENDING)
            ->setStudentSubmissionFileId($file->getId())
            ->setCreatedAt($now)
            ->setUpdatedAt($now);

        $this->save($model);

        return $model;
    }
}
