<?php

/**
 * Mapper
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Learning\Assessment\DueWork\Submission\File\Plagscan;

use Schoolbox\Assessment\Component\SubmissionBox\Submission\Submission;
use Schoolbox\Collection;
use Schoolbox\Learning\Assessment\DueWork\Submission\File\Plagscan\v3\Connector;
use Schoolbox\Learning\Assessment\DueWork\Submission\File\Plagscan\Model as Plagscan;
use Schoolbox\Learning\Assessment\DueWork\Submission\File\Model as SubmissionFile;
use Schoolbox\Assessment\Component\SubmissionBox\Submission\SingleFile\Model as StudentSubmissionFile;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\User;
use Schoolbox\Mapper\Exception as MapperException;

class Mapper extends \Schoolbox\Mapper
{

    /**
     * An instance of the Plagscan Connector
     * which is where files end up
     * 
     * @var Connector
     */
    private $connector;

    /**
     * A registry of allowed extension-mime tuples
     *
     * @var ValidFiles
     */
    private $validFiles;

    /**
     * Mapping.
     */
    protected function mapping () {
        $this->hasTable('plagscan_document');
        $this->hasModel(Plagscan::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('student_submission_file_id', self::TYPE_INTEGER);
        $this->uses('plagscan_id', self::TYPE_INTEGER);
        $this->uses('status', self::TYPE_INTEGER);
        $this->uses('score', self::TYPE_STRING);
        $this->uses('html', self::TYPE_STRING);
        $this->uses('interactive_url', self::TYPE_STRING);
        $this->uses('privatised_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE, 'deletedAt');
    }

    /**
     * Hook method
     */
    protected function setup()
    {
        $this->setValidFiles(new ValidFiles());

        /* If plagscan credentials exist, set up all the other stuff
         */
        $plagscanUsername = $this->getConfigLoader()->get('plagscan_username');
        $plagscanClientId = $this->getConfigLoader()->get('plagscan_client_id');
        $plagscanApiKey   = $this->getConfigLoader()->get('plagscan_api_key');

        /* All three data pieces are required to have existed
         * This is the build order for plagscan to work
         *
         * Username + password = plagscan account admin
         * plagscan account admin = generate api key + find out your client id
         * api key + client id = access token
         * access token + api url = plagscan data
         */
        if (strlen($plagscanApiKey) && strlen($plagscanClientId) && strlen($plagscanUsername)) {
            try {
                $this->setConnector(new Connector($this->getConfigLoader(), $plagscanClientId, $plagscanApiKey));
            }
            catch (\DomainException $e) {

                /* Could not spawn for some reason, but due to mapper usage
                 * we don't want to detonate - just quietly not use plagscan
                 */
            }
            catch (\RuntimeException $e) {

                /* Could not spawn because plagscan api is down
                 */
            }
        }
    }

    /**
     * If the Gateway spawned, plagscan is enabled for the $user specified
     *
     * Assuming the Gateway spawned:
     * - Students and parents can see plagscan results allowed via config
     * - Staff and Superusers can always see plagscan results
     *
     * @param User $user
     * @return bool
     */
    public function isEnabled(User $user)
    {
        /* Did the Gateway spawn properly
         * If so, there is a functioning username/apikey
         */
        if ($this->getConnector()) {

            /* Staffs and super can always see plagscan results
             */
            if ($user->getRole()->isStaff() || $user->isSuperuser()) {
                return true;
            }

            /* If the $user is a student or a parent, check a config var too
             */
            if (($user->getRole()->isStudent() || $user->getRole()->isParent()) && $this->getConfigLoader()->get('plagscan_student_visible')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Save a plagscan stub, so the ui can handle immediate feedback
     * rather than wait for the asynchronous stuff to do its thang
     *
     * @param SubmissionFile $file
     * @return array
     * @throws \Schoolbox\Mapper\Exception
     */
    public function saveNewPlagscanStub(SubmissionFile $file)
    {
        /* If gateway never spawned, plagscan not configured
         */
        if (!$this->getConnector()) {
            return [];
        }

        /* Invalid filetype
         */
        if (!$this->getValidFiles()->isPlagscannable($file->getBasename())) {
            return [];
        }

        /** @var Plagscan $plagscan */
        $plagscan = $this->createModel();
        $plagscan->setPlagscanId(null);
        $plagscan->setStatus(Plagscan::STATUS_QUEUED);
        $plagscan->setStudentSubmissionFileId($file->getId());
        $plagscan->setCreatedAt(\Alaress_Date_Format::now());
        $plagscan->setUpdatedAt(\Alaress_Date_Format::now());

        return parent::save($plagscan);
    }

    /**
     * Save a Plagscan payload into db
     *
     * @param SubmissionFile $file
     * @param $filename
     * @return array
     */
    public function saveNewPlagscan(SubmissionFile $file, $filename)
    {
        /* If gateway never spawned, plagscan not configured
         */
        if (!$this->getConnector()) {
            return [];
        }

        /* Invalid filetype
         */
        if (!$this->getValidFiles()->isPlagscannable($file->getBasename())) {
            return [];
        }

        /* Send it to plag
         */
        $plagscanId = $this->getConnector()->transmit($file, $filename);

        /* Plagscan death
         */
        if (empty($plagscanId)) {
            return [];
        }

        /* Update stubble record
         *
         * This should help prevent multiple-plagscan-per-file scenario, which
         * initiates dupe files in Inline Grader and Submission History
         */

        /** @var Plagscan $plagscan */
        try {
            $plagscan = $this->findOneByColumn('student_submission_file_id', $file->getId(), ['cache' => false]);
        }
        catch (MapperException $e) {

            /* No plagscan stub existed for this file
             * Spawn one and continue
             */
            $plagscan = $this->createModel();
            $plagscan->setStudentSubmissionFileId($file->getId());
            $plagscan->setCreatedAt(\Alaress_Date_Format::now());
        }

        $plagscan->setPlagscanId($plagscanId);
        $plagscan->setStatus(Plagscan::STATUS_QUEUED);
        $plagscan->setUpdatedAt(\Alaress_Date_Format::now());

        return parent::save($plagscan);
    }

    /**
     * Derive a plagscan filename that's used in Plagscan's systems
     * to identify files that people upload
     *
     * Current scheme is to use
     * <schoolbox user id>_<firstname>_<lastname>_<assessment id>
     *
     * @param SubmissionFile $submissionFile
     * @return null|string
     */
    public function plagscanFilename(SubmissionFile $submissionFile)
    {
        $query = "
            SELECT u.id, u.firstname, u.lastname, sb.assessment_id
            FROM student_submission_file sf
            INNER JOIN student_submission ss ON sf.student_submission_id = ss.id
            INNER JOIN submission_box sb on ss.box_id = sb.id
            INNER JOIN user u ON ss.owner = u.id
            WHERE sf.id = :submissionFileId
        ";

        $statement = $this->getDatabase()->prepare($query);
        $statement->execute([
            'submissionFileId' => $submissionFile->getId()
        ]);

        $row = $statement->fetch(\PDO::FETCH_ASSOC);

        if ($row) {
            return implode('_', array_values($row));
        }

        return null;
    }

    /**
     * Find previous incarnations of $studentSubmissionFileId
     * that haven't already been privatised
     *
     * This identifies assessment/owner ids and finds all the plagscan
     * instances relevant to anything authored by owner in assessment
     *
     * @param int $studentSubmissionFileId
     * @return Collection
     */
    public function findPreviousIncarnationsOf($studentSubmissionFileId)
    {
        $plagscanDocuments = new Collection();

        $query = "
            SELECT student_submission.`id`, `owner`, `box_id`
            FROM student_submission_file 
            INNER JOIN student_submission ON student_submission_file.`student_submission_id` = student_submission.`id`
            WHERE student_submission_file.`id` = :studentSubmissionFileId
        ";

        $statement = $this->getDatabase()->prepare($query);
        $statement->execute([
            'studentSubmissionFileId' => $studentSubmissionFileId
        ]);

        $ownerDetails = $statement->fetch(\PDO::FETCH_ASSOC);

        /* Owner and box_id exists
         */
        if ($ownerDetails) {
            $query = "
                SELECT {$this->getColumnString()}
                FROM {$this->getTable()}
                INNER JOIN student_submission_file ON plagscan_document.`student_submission_file_id` = student_submission_file.`id`
                INNER JOIN student_submission ON student_submission.`id` = student_submission_file.`student_submission_id`
                WHERE plagscan_document.`privatised_at` IS NULL
                AND student_submission.`owner` = :ownerId
                AND student_submission.`box_id` = :boxId
                AND student_submission.`id` < :submissionId
            ";

            $statement = $this->getDatabase()->prepare($query);
            $statement->execute([
                'ownerId' => $ownerDetails['owner'],
                'boxId' => $ownerDetails['box_id'],
                'submissionId' => $ownerDetails['id'],
            ]);

            /* De-dupe all the found plagscans
             *
             * Probably don't need to do this, but I want to be sure
             * given I have to curl this id to plagscan.com every time
             */
            while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
                $plagscanDocuments->setOffset($row['plagscan_id'], $this->hydrate($row));
            }
        }

        return $plagscanDocuments;
    }

    /**
     * Privatise a particular plagscan id
     * in both our db and in plagscan's
     *
     * @param Plagscan $plagscan
     *
     * @throws \RuntimeException
     */
    public function privatise(Plagscan $plagscan)
    {
        try {

            /* Privatise in plagscan.com then update our records too
             */
            $this->getConnector()->privatise($plagscan->getId());

            $plagscan->setPrivatisedAt(\Alaress_Date_Format::now());
            $this->save($plagscan);
        }
        catch (\RuntimeException $e) {

            /* Noop - could not privatise in plagscan
             *
             * Leave unchanged if plagscan refuses to do it on their end (jleckie, 6:43pm @ June 1)
             * This allows you to re-encounter documents being checked against older versions of themselves
             * but can't do much to stop it if plagscan screws us
             */
        }
    }

    /* Getters/Setters
     */

    /**
     * @return Connector
     */
    public function getConnector()
    {
        return $this->connector;
    }

    /**
     * @param Connector $connector
     * @return $this
     */
    private function setConnector($connector)
    {
        $this->connector = $connector;
        return $this;
    }

    /**
     * @return ValidFiles
     */
    private function getValidFiles()
    {
        return $this->validFiles;
    }

    /**
     * @param ValidFiles $validFiles
     * @return $this
     */
    private function setValidFiles($validFiles)
    {
        $this->validFiles = $validFiles;
        return $this;
    }
}