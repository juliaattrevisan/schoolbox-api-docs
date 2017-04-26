<?php
/**
 * Callback
 *
 * This is summoned when Plagscan finishes scanning an uploaded document.
 * Plagscan requests one of -our- urls when it's finished doing its stuff
 *
 * This url is normally www/service/plagscan.php
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Learning\Assessment\DueWork\Submission\File\Plagscan;

use Schoolbox\Learning\Assessment\DueWork\Submission\File\Plagscan\Model as Plagscan;
use Schoolbox\Learning\Assessment\DueWork\Submission\File\Plagscan\Mapper as PlagscanMapper;
use \Schoolbox\Learning\Assessment\DueWork\Submission\File\Model as SubmissionFile;
use Schoolbox\Assessment\Component\SubmissionBox\Submission\SingleFile\Model as StudentSubmissionFile;
use Schoolbox\Assessment\Component\SubmissionBox\Submission\SingleFile\Mapper as StudentSubmissionFileMapper;
use Schoolbox\Mapper\Exception as MapperException;

class Callback
{
    /**
     * @var Mapper
     */
    private $mapper;

    /**
     * Callback constructor.
     * @throws \DomainException
     */
    public function __construct()
    {
        $this->setMapper(PlagscanMapper::getInstance());
    }

    /**
     * Executed when callback from PlagScan is triggered, and requests the report from PlagScan
     * Performs the same kind of stuff as scan.php
     *
     * @param $plagscanId
     * @return bool
     */
    public function notifyComplete($plagscanId)
    {
        /* Plagscan not configured - nothing to do
         */
        if (!$this->getMapper()->getConnector()) {
            return false;
        }

        try {
            /* @var $document Plagscan */
            $currentDocument = $this->getMapper()->findOneByColumn('plagscan_id', $plagscanId);

            /** @var StudentSubmissionFile $studentSubmissionFile */
            $studentSubmissionFile = StudentSubmissionFileMapper::getInstance()->findById($currentDocument->getStudentSubmissionFileId());

            /* Convert to new format
             */
            $submissionFile = new SubmissionFile();
            $submissionFile->setStorage($studentSubmissionFile->getStorage());

            /* Make a somewhat infamous assumption that plagscan completed its stuff
             * which is the only reason we'd be running this Callback
             */
            $interactiveReportUrl = $this->getMapper()->getConnector()->fetchInteractiveReportUrl($currentDocument->getPlagscanId());
            $reportContents = $this->getMapper()->getConnector()->fetchReport($currentDocument->getPlagscanId());
            $score = $this->getMapper()->getConnector()->fetchScore($currentDocument->getPlagscanId());

            /* Update the db with the report contents for this document
             */
            $currentDocument->setPlagscanId($currentDocument->getPlagscanId());
            $currentDocument->setStatus(Plagscan::STATUS_COMPLETE);
            $currentDocument->setScore($score);
            $currentDocument->setHtml($reportContents);
            $currentDocument->setInteractiveUrl($interactiveReportUrl);
            $currentDocument->setUpdatedAt(\Alaress_Date_Format::now());
            $this->getMapper()->save($currentDocument);
        }
        catch (\DomainException $e) {

            /* Some kind of plagscan error occurred - perhaps log it?
             * noop
             */
            return false;
        }
        catch (MapperException $e) {

            /* Plagscan record not found - perhaps log it?
             * noop
             */
            return false;
        }

        return true;
    }

    /* Getters/Setters
     */

    /**
     * @return Mapper
     */
    private function getMapper()
    {
        return $this->mapper;
    }

    /**
     * @param Mapper $mapper
     * @return $this
     */
    private function setMapper($mapper)
    {
        $this->mapper = $mapper;
        return $this;
    }
}