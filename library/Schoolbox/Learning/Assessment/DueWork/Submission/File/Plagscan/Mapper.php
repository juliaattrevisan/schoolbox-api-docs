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

use Schoolbox\Learning\Assessment\DueWork\Submission\File\Plagscan\Model as Plagscan;
use Schoolbox\Learning\Assessment\DueWork\Submission\File\Model as SubmissionFile;
use Schoolbox\User;

class Mapper extends \Schoolbox\Mapper
{

    /**
     * An instance of the Plagscan Gateway
     * which is where files end up
     * 
     * @var Gateway
     */
    private $gateway;

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
        $plagscanApiKey   = $this->getConfigLoader()->get('plagscan_api_key');
        $plagscanUsername = $this->getConfigLoader()->get('plagscan_username');

        /* If plagscan credentials exist, set up all the other stuff
         */
        if (strlen($plagscanApiKey) && strlen($plagscanUsername)) {
            $this->setGateway(new Gateway($plagscanUsername, $plagscanApiKey));
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
     * Save a Plagscan payload into db
     *
     * @param SubmissionFile $file
     * @return array
     */
    public function saveNewPlagscan(SubmissionFile $file)
    {
        /* No existing Plagscan found for $dirtyDocument
         * Spawn new and ship it to Plagscan Gateway
         */
        if (!$this->getGateway()) {
            return [];
        }

        $documentId = $this->getGateway()->submit($file);

        if (empty($documentId)) {
            return [];
        }

        $this->getGateway()->check($documentId);

        /** @var Plagscan $plagscan */
        $plagscan = $this->createModel();
        $plagscan->setPlagscanId($documentId);
        $plagscan->setStatus(Plagscan::STATUS_QUEUED);
        $plagscan->setStudentSubmissionFileId($file->getId());
        $plagscan->setCreatedAt(\Alaress_Date_Format::now());
        $plagscan->setUpdatedAt(\Alaress_Date_Format::now());

        return parent::save($plagscan);
    }

    /* Getters/Setters
     */

    /**
     * @return Gateway
     */
    public function getGateway()
    {
        return $this->gateway;
    }

    /**
     * @param Gateway $gateway
     * @return $this
     */
    private function setGateway($gateway)
    {
        $this->gateway = $gateway;
        return $this;
    }
}