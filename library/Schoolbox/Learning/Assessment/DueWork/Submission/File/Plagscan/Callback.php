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

use \Schoolbox\Learning\Assessment\DueWork\Submission\File\Plagscan\Model as Plagscan;
use \Schoolbox\Learning\Assessment\DueWork\Submission\File\Plagscan\Mapper as PlagscanMapper;
use \Schoolbox\Learning\Assessment\DueWork\Submission\File\Plagscan\Gateway as PlagscanGateway;

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
     * @param $documentId
     * @return bool
     */
    public function notifyComplete($documentId)
    {
        try {
            /* @var $document Plagscan */
            $document = $this->getMapper()->findOneByColumn('plagscan_id', $documentId);

            if (!($document instanceof Plagscan)) {
                return false;
            }

            $plagscanStat = $this->getMapper()->getGateway()->retrieve($documentId, PlagscanGateway::MODE_RETRIEVE_STATISTICS_ONLY);
            $plagscanHtml = $this->getMapper()->getGateway()->retrieve($documentId, PlagscanGateway::MODE_RETRIEVE_HTML_INTERACTIVE);

            $document->setScore($plagscanStat->LEVEL / 10);
            $document->setStatus(Plagscan::STATUS_COMPLETE);
            $document->setHtml($plagscanHtml);

            $this->getMapper()->save($document);
        } catch (\Schoolbox\Mapper\Exception $e) {
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