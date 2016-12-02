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

use \Schoolbox\Learning\Assessment\DueWork\Submission\File\Plagscan\Model as Plagscan;
use \Schoolbox\Learning\Assessment\DueWork\Submission\File\Model as SubmissionFile;

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
        $this->uses('deleted', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE, 'deletedAt');
    }

    /**
     * Hook method
     */
    protected function setup()
    {
        parent::setup();

        $plagscanApiKey   = $this->getConfigLoader()->get('plagscan_api_key');
        $plagscanUsername = $this->getConfigLoader()->get('plagscan_username');

        /* If plagscan credentials exist, set up all the other stuff
         */
        if (strlen($plagscanApiKey) && strlen($plagscanUsername)) {
            $this->gateway = new Gateway($plagscanUsername, $plagscanApiKey);
        }
        else {

            /* Otherwise die magnificently
             */
            throw new \DomainException('Plagscan credentials are not specified.');
        }
    }

    /**
     * Save a Plagscan payload into db
     *
     * @param SubmissionFile $file
     * @return array|null
     */
    public function saveNewPlagscan(SubmissionFile $file)
    {
        /* No existing Plagscan found for $dirtyDocument
         * Spawn new and ship it to Plagscan Gateway
         */
        $documentId = $this->gateway->submit($file);

        if (!empty($documentId)) {
            $this->gateway->check($documentId);

            /** @var Plagscan $plagscan */
            $plagscan = $this->createModel();
            $plagscan->setPlagscanId($documentId);
            $plagscan->setStatus(Plagscan::STATUS_QUEUED);
            $plagscan->setStudentSubmissionFileId($file->getId());

            return parent::save($plagscan);
        }

        return null;
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
     */
    private function setGateway($gateway)
    {
        $this->gateway = $gateway;
    }
}