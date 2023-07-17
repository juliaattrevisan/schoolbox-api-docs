<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Turnitin\File;

use Schoolbox\Assessment\Component\SubmissionBox\Submission\SingleFile\Model as SubmissionFile;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Turnitin\ValidFile;

/**
 * Transacts Turnitin models into the turnitin_report db table
 *
 * @method Model createModel($idValue = null)
 * @method Model findById($identifierValue)
 * @method Model findOneByColumn($columnName, $columnValue, array $options = [])
 * @method GenericCollection|Model[] findByColumn($columnName, $columnValue, $options = [])
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
        $this->uses('internet_match_percentage', self::TYPE_DECIMAL);
        $this->uses('publication_match_percentage', self::TYPE_DECIMAL);
        $this->uses('submitted_works_match_percentage', self::TYPE_DECIMAL);
        $this->uses('historical', self::TYPE_BOOLEAN);
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
            ->setHistorical(false)
            ->setCreatedAt($now)
            ->setUpdatedAt($now);

        $this->save($model);

        return $model;
    }


    /**
     * Find the manifest for each student's most recent submission in $assessmentId
     * This is a scalarised partial payload; you'll have to add the routes and user
     * data separately via decoration
     *
     * This payload disregards any Historically-added turnitin documents - those
     * will never appear in the result set
     *
     * @param int $assessmentId
     * @return array
     * @throws \PDOException
     */
    public function findMostRecentForAssessment(int $assessmentId): array
    {
        $query = '
            SELECT
                ssf.student_submission_id,
                ss.submitted_at,
                ss.owner,
                ssf.*,
                storage.ext,
                tr.id AS turnitinReportId,
                tr.turnitin_submission_id AS turnitinSubId,
                overall_match_percentage,
                internet_match_percentage,
                publication_match_percentage,
                submitted_works_match_percentage
            FROM assessment a
            INNER JOIN submission_box sb ON a.id = sb.assessment_id
            INNER JOIN (
                SELECT student_submission.box_id, student_submission.owner, MAX(submitted_at) as maxdate
                FROM student_submission
                GROUP BY box_id, owner
            ) maxdates ON sb.id = maxdates.box_id
            INNER JOIN student_submission ss ON maxdates.owner = ss.owner AND maxdate = ss.submitted_at
            INNER JOIN student_submission_file ssf ON ss.id = ssf.student_submission_id
            LEFT JOIN storage ON ssf.hash = storage.hash
            LEFT JOIN turnitin_report tr ON ssf.id = tr.student_submission_file_id AND tr.historical = 0
            WHERE a.id = :assessmentId
            AND tr.id IS NOT NULL
            AND tr.status = :status
            ORDER BY ssf.id
        ';

        $statement = $this->getDatabase()->prepare($query);
        $statement->execute([
            'assessmentId' => $assessmentId,
            'status' => Model::STATUS_COMPLETE
        ]);

        $models = [];

        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $userId = $row['owner'];

            /* This will be the container for scalarised User instance
             * with their role/group stuff etc. which I don't want to
             * hydrate here explicitly; plus I need a router later anyway
             */
            if (!isset($models[$userId]['user'])) {
                $models[$userId]['id'] = $userId;
                $models[$userId]['user'] = [
                    'id' => $userId,
                    'title' => null,
                    'firstname' => null,
                    'lastname' => null,
                    'fullname' => null,
                    'role' => null,
                ];
            }

            $models[$userId]['files'][] = [
                '_links' => [
                    'turnitin_report' => null
                ],
                'id' => $row['id'],
                'name' => $row['realname'],
                'ext' => $row['ext'],
                'hash' => $row['hash'],
                'turnitin' => [
                    'id' => $row['turnitinReportId'],
                    'turnitin_submission_id' => $row['turnitinSubId'],
                    'percentage' => [
                        'overall' => $row['overall_match_percentage'],
                        'internet' => $row['internet_match_percentage'],
                        'publication' => $row['publication_match_percentage'],
                        'submitted_works' => $row['submitted_works_match_percentage']
                    ],
                ],
            ];

            /* Submission timestamp applies to all files in a submission
             */
            $timestamp = strtotime($row['submitted_at']);

            $models[$userId]['submission'] = [
                '_links' => [
                    'inline_grader' => null,
                ],
                'id' => $row['student_submission_id'],
                'submitted_at' => [
                    'database' => $row['submitted_at'],
                    'relative' => \Alaress_Date_Format::newFromTimestamp($timestamp)->getRelativeTime(),
                    'nice' => \Alaress_Date_Format::newFromTimestamp($timestamp)->getShort(),
                    'timestamp' => $timestamp
                ]
            ];
        }

        return $models;
    }
}
