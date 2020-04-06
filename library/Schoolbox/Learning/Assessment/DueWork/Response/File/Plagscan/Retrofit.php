<?php
/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\DueWork\Response\File;

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
     * @var Plagscan\Mapper
     */
    private $plagscanMapper;


    /**
     * Retrofit constructor.
     *
     * @param Plagscan\Mapper $plagscanMapper
     */
    public function __construct(Plagscan\Mapper $plagscanMapper)
    {
        $this->plagscanMapper = $plagscanMapper;
    }


    /**
     * Retrofit all the student_submission_file_id's for $assessmentId that don't already
     * have a plagscan_document associated
     *
     * @param int $assessmentId
     * @return int
     */
    public function retrofit(int $assessmentId): int
    {

        /* For the given $assessmentId, find all the student_submission records
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
            INNER JOIN student_submission ss ON sb.id = ss.box_id
            INNER JOIN student_submission_file ssf ON ss.id = ssf.student_submission_id
            LEFT JOIN plagscan_document pd ON ssf.id = pd.student_submission_file_id
            WHERE a.id = :assessmentId
            AND pd.id IS NULL
            ORDER BY ssf.id
        ';

        /* Create a new plagscan stub for each candidate student_submission_file_id
         * Set its status to QUEUED which primes them for submit.php to execute
         */

        /* Wait for submit.php crontab to execute which handles the upload etc.
         */
    }
}
