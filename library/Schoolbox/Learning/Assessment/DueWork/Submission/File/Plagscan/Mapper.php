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

use Schoolbox\Learning\Assessment\DueWork\Submission\File\Plagscan\v3\Connector;
use Schoolbox\Learning\Assessment\DueWork\Submission\File\Plagscan\Model as Plagscan;
use Schoolbox\Learning\Assessment\DueWork\Submission\File\Model as SubmissionFile;
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
     * @return array
     */
    public function saveNewPlagscan(SubmissionFile $file)
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
        $plagscanId = $this->getConnector()->transmit($file);

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