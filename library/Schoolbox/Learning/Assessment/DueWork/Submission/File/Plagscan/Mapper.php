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

use Schoolbox\Learning\Assessment\DueWork\Submission\File\Plagscan\v3\WarpGate;
use Schoolbox\Learning\Assessment\DueWork\Submission\File\Plagscan\v3\AccessTokenGenerator;
use Schoolbox\Learning\Assessment\DueWork\Submission\File\Plagscan\Model as Plagscan;
use Schoolbox\Learning\Assessment\DueWork\Submission\File\Model as SubmissionFile;
use Schoolbox\User;

class Mapper extends \Schoolbox\Mapper
{

    /**
     * An instance of the Plagscan Gateway
     * which is where files end up
     * 
     * @var WarpGate
     */
    private $gateway;

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
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('deleted', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE, 'deletedAt');
    }

    /**
     * Hook method
     */
    protected function setup()
    {
        $this->setValidFiles(new ValidFiles());

        $plagscanApiKey   = $this->getConfigLoader()->get('plagscan_api_key');
        $plagscanUsername = $this->getConfigLoader()->get('plagscan_username');
        $plagscanClientId = $this->getConfigLoader()->get('plagscan_client_id');

        /* Set up access token
         */
        $accessToken = (new AccessTokenGenerator($plagscanClientId, $plagscanApiKey))->generate();

        /* If plagscan credentials exist, set up all the other stuff
         */
        if (strlen($plagscanApiKey) && strlen($plagscanUsername)) {
            $this->setGateway(new WarpGate($this->getConfigLoader(), $accessToken));
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
        if ($this->getGateway()) {

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
        if (!$this->getGateway()) {
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
        if (!$this->getGateway()) {
            return [];
        }

        /* Invalid filetype
         */
        if (!$this->getValidFiles()->isPlagscannable($file->getBasename())) {
            return [];
        }

        /* Send it to plag
         */
        $documentId = $this->getGateway()->submit($file);

        /* Plagscan death
         */
        if (empty($documentId)) {
            return [];
        }

        /* Otherwise, proceed normally
         */
        $this->getGateway()->check($documentId);

        /* plagscan returned adocumentId that isn't useful?
         */
        if (!$documentId) {
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
        catch (\Schoolbox\Mapper\Exception $e) {

            /* No plagscan stub existed for this file
             * Spawn one and continue
             */
            $plagscan = $this->createModel();
            $plagscan->setStudentSubmissionFileId($file->getId());
            $plagscan->setCreatedAt(\Alaress_Date_Format::now());
        }

        $plagscan->setPlagscanId($documentId);
        $plagscan->setStatus(Plagscan::STATUS_QUEUED);
        $plagscan->setUpdatedAt(\Alaress_Date_Format::now());

        return parent::save($plagscan);
    }

    /* Getters/Setters
     */

    /**
     * @return WarpGate
     */
    public function getGateway()
    {
        return $this->gateway;
    }

    /**
     * @param WarpGate $gateway
     * @return $this
     */
    private function setGateway($gateway)
    {
        $this->gateway = $gateway;
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