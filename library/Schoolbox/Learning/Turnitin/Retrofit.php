<?php

/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Turnitin;

use PDOException;
use Psr\Log\LoggerInterface;
use Schoolbox\Mapper\Exception;
use UnexpectedValueException;

/**
 * Class Retrofit
 *
 * For an assessment id, find all the student_submission_file_id's that don't have a corresponding
 * turnitin_report row and create one for them, setting the status to Pending. This applies to
 * all the student_submission_file_ids from the most recent submission that a student made to this
 * Due Work (it won't retroactively upload absolutely everything, just their most recent submission)
 *
 * @package Schoolbox\Learning\Turnitin
 */
class Retrofit
{
    use LogTrait;


    /**
     * @var File\Mapper
     */
    private File\Mapper $turnitinFileMapper;


    /**
     * @var \Schoolbox\Assessment\Component\SubmissionBox\Submission\SingleFile\Mapper
     */
    private \Schoolbox\Assessment\Component\SubmissionBox\Submission\SingleFile\Mapper $singleFileMapper;


    /**
     * Retrofit constructor.
     *
     * @param File\Mapper                                                                $turnitinFileMapper
     * @param \Schoolbox\Assessment\Component\SubmissionBox\Submission\SingleFile\Mapper $singleFileMapper
     * @param LoggerInterface|null                                                       $log
     */
    public function __construct(
        File\Mapper $turnitinFileMapper,
        \Schoolbox\Assessment\Component\SubmissionBox\Submission\SingleFile\Mapper $singleFileMapper,
        ?LoggerInterface $log = null
    ) {
        $this->turnitinFileMapper = $turnitinFileMapper;
        $this->singleFileMapper = $singleFileMapper;
        $this->log = $log;
    }


    /**
     * Retrofit all the student_submission_file_id's for $assessmentId that don't already
     * have a turnitin_report row associated
     *
     * @param int $assessmentId
     * @return void
     */
    public function retrofit(int $assessmentId): void
    {
        /* For the given $assessmentId, find the submission_box.id
         */

        /* See if there's a turnitin_group for it
         * If there isn't, create one
         */

        /* For a given turnitin_group record,
         * Find all its attachments and create turnitin_group_attachment records
         * (You can do this by giving Turnitin/Group/Process/Group.php the turnitin_group record)
         *
         * The crontab already does this step for us so we could count on that.
         * Alternatively, inject the dependencies and run the GroupTool directly,
         * guaranteeing that they process before staging each student submission's file
         * (That's a bit slower and might not be necessary)
         */

        /* For the given submission_box.id,
         * Find a list of each student's most recent submission
         */

        /* For each of the submissions found, find each of the files associated with it
         * and send them up to turnitin. You might be able to trust that each file's
         * submitted_at timestamp is identical for all the files in the same submission
         */
    }


    /**
     * Retrofit all the student_submission_file_id's for $assessmentId that don't already
     * have a turnitin_report row associated
     *
     * @param int $assessmentId
     * @return void
     * @throws PDOException
     * @throws Exception
     * @throws UnexpectedValueException
     */
    public function retrofitOld(int $assessmentId): void
    {
        /* For the given $assessmentId, find the most recent student_submission records
         * and from there, find all the student_submission_file_id values
         *
         * Once you have those, reduce the set to ones which don't already have a turnitin_report
         * row in the db. Once it gets into turnitin_report, the async tools will take over the
         * transmission responsibilities etc.
         */
        $query = '
            SELECT ssf.*
            FROM assessment a
            INNER JOIN submission_box sb ON a.id = sb.assessment_id
            INNER JOIN (
                SELECT student_submission.box_id, student_submission.owner, MAX(submitted_at) as maxdate
                FROM student_submission
                GROUP BY box_id, owner
            ) maxdates ON sb.id = maxdates.box_id
            INNER JOIN student_submission ss ON maxdates.owner = ss.owner AND maxdate = ss.submitted_at
            INNER JOIN student_submission_file ssf ON ss.id = ssf.student_submission_id
            INNER JOIN storage s ON ssf.hash = s.hash
            LEFT JOIN turnitin_report tr ON ssf.id = tr.student_submission_file_id
            WHERE a.id = :assessmentId
            AND tr.id IS NULL
            ORDER BY ssf.id
        ';

        $statement = $this->turnitinFileMapper->getDatabase()->prepare($query);
        $statement->execute([
            'assessmentId' => $assessmentId,
        ]);

        /* Find the ids so we can hydrate instances easier
         */
        $ids = [];

        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $ids[] = $row['id'];
        }

        $singleFiles = $this->singleFileMapper->findByColumn('id', $ids);

        /* Build a collection of models
         */
        $models = [];

        foreach ($singleFiles as $singleFile) {
            /* All files discovered for retrofit must adhere to the usual criteria
             */
            if (!(new ValidFile())->isSatisfiedBy($singleFile)) {
                $this->logDebug(sprintf(
                    'Student Submission File Id %s: not suitable for retrofit (invalid file)',
                    $singleFile->getId(),
                ));

                continue;
            }

            $models[] = $this->turnitinFileMapper->createModel()
                ->setStudentSubmissionFileId($singleFile->getId())
                ->setHistorical(false)
                ->setStatus(File\Model::STATUS_TRANSMIT_PENDING);

            $this->logDebug(sprintf(
                'Student Submission File Id %s: preparing for retrofit',
                $singleFile->getId(),
            ));
        }

        $numFiles = \count($models);

        if (!$numFiles) {
            $this->logInfo('There are no files to retrofit; exiting');

            return;
        }

        $this->logInfo(sprintf(
            '%s file(s) prepared for retrofit',
            $numFiles
        ));

        /* Save them all (I can probably use Collections in the save
         * but Support would lose the diagnostics)
         */
        foreach ($models as $turnitinFile) {
            $this->turnitinFileMapper->save($turnitinFile);

            $this->logDebug(sprintf(
                'Student Submission File Id %s: retrofitting into turnitin_report id %s with status [%s]',
                $turnitinFile->getStudentSubmissionFileId(),
                $turnitinFile->getId(),
                File\Model::STATUS_TRANSMIT_PENDING
            ));
        }
    }
}
