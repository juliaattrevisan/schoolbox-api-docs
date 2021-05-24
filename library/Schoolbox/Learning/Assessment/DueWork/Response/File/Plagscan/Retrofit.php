<?php

/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\DueWork\Response\File\Plagscan;

use PDOException;
use Schoolbox\Assessment\Component\SubmissionBox\Submission\SingleFile;
use Schoolbox\Mapper\Exception;
use UnexpectedValueException;

/**
 * Class Retrofit
 *
 * For an assessment id, find all the student_submission_file_id's that don't have a corresponding
 * plagscan_document row and create one for them, setting the status to QUEUED
 *
 * This class retrofits these documents into plagscan, but I can't guarantee the order in which they
 * will be processed by plagscan
 *
 * @package Schoolbox\Learning\Assessment\DueWork\Response\File\Plagscan
 */
class Retrofit
{

    /**
     * @var Mapper
     */
    private $plagscanMapper;


    /**
     * @var SingleFile\Mapper
     */
    private $submissionFileMapper;


    /**
     * Retrofit constructor.
     *
     * @param Mapper   $plagscanMapper
     * @param SingleFile\Mapper $submissionFileMapper
     */
    public function __construct(Mapper $plagscanMapper, SingleFile\Mapper $submissionFileMapper)
    {
        $this->plagscanMapper = $plagscanMapper;
        $this->submissionFileMapper = $submissionFileMapper;
    }


    /**
     * Retrofit all the student_submission_file_id's for $assessmentId that don't already
     * have a plagscan_document associated
     *
     * @param int $assessmentId
     * @return void
     * @throws PDOException
     * @throws Exception
     * @throws UnexpectedValueException
     */
    public function retrofit(int $assessmentId): void
    {

        /* For the given $assessmentId, find the most recent student_submission records
         * and from there, find all the student_submission_file_id values
         *
         * Once you have those, reduce the set to ones which don't already have a plagscan_document
         * row in the db; you can't tell when the retrofit will occur so some submissions might already
         * have made their way to plagscan already. This reduced set is the candidate set of ids who
         * need to be given a plagscan_document record
         */
        $query = '
            SELECT ssf.*
            FROM assessment a
            INNER JOIN submission_box sb ON a.id = sb.assessment_id
            INNER JOIN (
                SELECT student_submission.box_id, student_submission.owner, MAX(submitted_at) as maxdate
                FROM student_submission
                GROUP BY box_id, owner
                ) maxdates
             ON sb.id = maxdates.box_id
            INNER JOIN student_submission ss ON maxdates.owner = ss.owner AND maxdate = ss.submitted_at
            INNER JOIN student_submission_file ssf ON ss.id = ssf.student_submission_id
            LEFT JOIN plagscan_document pd ON ssf.id = pd.student_submission_file_id
            WHERE a.id = :assessmentId
            AND pd.id IS NULL
            ORDER BY ssf.id
        ';

        $statement = $this->plagscanMapper->getDatabase()->prepare($query);
        $statement->execute([
            'assessmentId' => $assessmentId
        ]);

        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $file = $this->submissionFileMapper->hydrate($row);

            $this->plagscanMapper->saveNewPlagscanStub($file);
        }

        /* Wait for submit.php crontab to execute which handles the upload etc.
         */
    }
}
