<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Turnitin;

use PDOException;
use Psr\Log\LoggerInterface;
use Schoolbox\Mapper\Exception;
use Symfony\Component\Lock\LockInterface;
use UnexpectedValueException;

/**
 * Class Historical
 *
 * Find all the most recent submissions for submission boxes, between two dates,
 * and add them into turnitin_report for processing later. This allows you to
 * mass-upload ancient documents that should still be part of turnitin's brain
 * when it does its cheat checking.
 *
 * As per the usual algorithm, these are set to status Pending for upload into turnitin.
 * However, we remember that this was a Historical upload which requires slightly different
 * processing.
 *
 * @package Schoolbox\Learning\Turnitin
 */
class Historical
{
    use LogTrait;


    /**
     * @var bool
     */
    private bool $perform;


    /**
     * @var File\Mapper
     */
    private File\Mapper $turnitinFileMapper;


    /**
     * @var \Schoolbox\Assessment\Component\SubmissionBox\Submission\SingleFile\Mapper
     */
    private \Schoolbox\Assessment\Component\SubmissionBox\Submission\SingleFile\Mapper $singleFileMapper;


    /**
     * @var \Schoolbox\Resource\Storage\Mapper
     */
    private \Schoolbox\Resource\Storage\Mapper $storageMapper;


    /**
     * Historical constructor.
     *
     * @param File\Mapper                                                                $turnitinFileMapper
     * @param \Schoolbox\Assessment\Component\SubmissionBox\Submission\SingleFile\Mapper $singleFileMapper
     * @param \Schoolbox\Resource\Storage\Mapper                                         $storageMapper
     * @param bool                                                                       $perform
     * @param LoggerInterface|null                                                       $log
     */
    public function __construct(
        File\Mapper $turnitinFileMapper,
        \Schoolbox\Assessment\Component\SubmissionBox\Submission\SingleFile\Mapper $singleFileMapper,
        \Schoolbox\Resource\Storage\Mapper $storageMapper,
        bool $perform,
        ?LoggerInterface $log = null
    ) {
        $this->turnitinFileMapper = $turnitinFileMapper;
        $this->singleFileMapper = $singleFileMapper;
        $this->storageMapper = $storageMapper;
        $this->perform = $perform;
        $this->log = $log;
    }


    /**
     * Find all the student_submission_file_id's that don't already
     * have a turnitin_report row associated, where the file was
     * authored between the dates specified
     *
     * @param \Alaress_Date_Format $start
     * @param \Alaress_Date_Format $end
     * @param LockInterface|null   $lock
     * @return void
     * @throws Exception
     * @throws PDOException
     * @throws UnexpectedValueException
     * @throws \Symfony\Component\Lock\Exception\LockAcquiringException
     * @throws \Symfony\Component\Lock\Exception\LockConflictedException
     */
    public function historical(\Alaress_Date_Format $start, \Alaress_Date_Format $end, ?LockInterface $lock): void
    {

        $this->logInfo('Preparing the following Student Submission Files for indexing.');

        $this->logInfo(sprintf(
            'They were submitted between %s and %s:',
            $start->getLocal(),
            $end->getLocal()
        ));

        $this->logInfo('');

        /* Find the most recent student_submission records
         * and from there, find all the student_submission_file_id values
         *
         * Once you have those, reduce the set to ones which don't already have a turnitin_report
         * row in the db. Once it gets into turnitin_report, the async tools will take over the
         * transmission responsibilities etc.
         *
         * Ignore softdeleted items (the bidness logic postdelete hooks ec. should have cascaded
         * that down the line but in case it didn't...exclude them explicitly)
         *
         * Ignore submission_boxes that did not enable similarity check
         */
        $query = "
            SELECT
                sb.id AS submission_box_id, a.title AS submission_box_name,
                ssf.*,
                s.*
            FROM submission_box sb
            INNER JOIN (
                SELECT student_submission.box_id, student_submission.owner, MAX(submitted_at) as maxdate
                FROM student_submission
                WHERE deleted_at IS NULL
                GROUP BY box_id, owner
            ) maxdates ON sb.id = maxdates.box_id
            INNER JOIN student_submission ss ON maxdates.owner = ss.owner AND maxdate = ss.submitted_at
            INNER JOIN student_submission_file ssf ON ss.id = ssf.student_submission_id
            INNER JOIN storage s ON ssf.hash = s.hash
            INNER JOIN assessment a ON sb.assessment_id = a.id
            LEFT JOIN turnitin_report tr ON ssf.id = tr.student_submission_file_id
            WHERE ss.submitted_at >= :startDate
            AND ss.submitted_at <= :endDate
            AND tr.id IS NULL
            AND ssf.deleted_at IS NULL
            AND a.deleted_at IS NULL
            AND sb.similarity_check_enabled = 1
            ORDER BY ssf.id
        ";

        $statement = $this->turnitinFileMapper->getDatabase()->prepare($query);
        $statement->execute([
            'startDate' => $start->getDatabase(),
            'endDate' => $end->getDatabase()
        ]);

        $rows = $statement->fetchAll();

        $numFiles = 0;

        /* Build a collection of models
         */
        foreach ($rows as $row) {
            $singleFile = $this->singleFileMapper->hydrate($row);
            $storage = $this->storageMapper->hydrate($row);

            $singleFile->setStorage($storage);

            /* All files discovered for historical addition must adhere to the usual criteria
             */
            if (!(new ValidFile())->isSatisfiedBy($singleFile)) {
                $this->logDebug(sprintf(
                    'Student Submission File Id %s: "%s" not suitable for historical addition (invalid file)',
                    $singleFile->getId(),
                    $singleFile->getBasename()
                ));

                continue;
            }

            $turnitinFile = $this->turnitinFileMapper->createModel()
                ->setStudentSubmissionFileId((int)$singleFile->getId())
                ->setHistorical(true)
                ->setStatus(File\Model::STATUS_TRANSMIT_PENDING);

            $this->logInfo(sprintf(
                'Id %s (%s): "%s", in box %s called "%s"',
                $singleFile->getId(),
                $singleFile->getCreatedAt()->getDatabase(),
                $singleFile->getBasename(),
                $row['submission_box_id'],
                $row['submission_box_name']
            ));

            /* Only slam it into turnitin_report in Perform mode
             */
            if ($this->perform) {
                $this->turnitinFileMapper->save($turnitinFile);
            }

            $this->logDebug(sprintf(
                'Student Submission File Id %s: historically adding into turnitin_report id %s with status [%s]',
                $turnitinFile->getStudentSubmissionFileId(),
                $turnitinFile->getId() ?: '(as a new record)',
                File\Model::STATUS_TRANSMIT_PENDING
            ));

            /* Refresh lock while we're still processing; keep using another
             * $timeout seconds until we're done, or until we died
             */
            if ($lock) {
                $lock->refresh();
            }

            $numFiles++;
        }

        if (!$numFiles) {
            $this->logInfo('There are no files for historical addition; exiting');

            return;
        }

        $this->logInfo('');

        $this->logInfo(sprintf(
            '%s file(s) prepared for historical addition',
            $numFiles
        ));
    }
}
