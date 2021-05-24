<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment\DueWork\Response\File\Plagscan;

use Schoolbox\Mapper\Exception as MapperException;

/**
 * Class Callback
 *
 * This is summoned when Plagscan finishes scanning an uploaded document.
 * Plagscan requests one of -our- urls when it's finished doing its stuff
 *
 * This url is normally www/service/plagscan.php
 *
 * @package Schoolbox\Learning\Assessment\DueWork\Response\File\Plagscan
 */
class Callback
{

    /**
     * @var Mapper
     */
    private $mapper;


    /**
     * Callback constructor.
     */
    public function __construct()
    {
        $this->mapper = Mapper::getInstance();
    }


    /**
     * Executed when callback from PlagScan is triggered, and requests the report from PlagScan
     * Performs the same kind of stuff as scan.php
     *
     * @param int $plagscanId
     * @return bool
     * @throws \InvalidArgumentException
     * @throws \PDOException
     * @throws \RuntimeException
     * @throws \UnexpectedValueException
     */
    public function notifyComplete($plagscanId)
    {
        /* Plagscan not configured - nothing to do
         */
        if (!$this->mapper->getConnector()) {
            return false;
        }

        try {
            $currentDocument = $this->mapper->findOneByColumn('plagscan_id', $plagscanId);

            /* Make a somewhat infamous assumption that plagscan completed its stuff
             * which is the only reason we'd be running this Callback
             */
            $interactiveReportUrl = $this->mapper->getConnector()->fetchInteractiveReportUrl($currentDocument->getPlagscanId());
            $score = $this->mapper->getConnector()->fetchScore($currentDocument->getPlagscanId());

            /* Update the db with the report contents for this document
             */
            $currentDocument
                ->setPlagscanId($currentDocument->getPlagscanId())
                ->setStatus(Model::STATUS_COMPLETE)
                ->setScore($score)
                ->setInteractiveUrl($interactiveReportUrl)
                ->setUpdatedAt(\Alaress_Date_Format::now());

            $this->mapper->save($currentDocument);
        } catch (\DomainException $e) {
            /* Some kind of plagscan error occurred - perhaps log it?
             * noop
             */
            return false;
        } catch (MapperException $e) {
            /* Plagscan record not found - perhaps log it?
             * noop
             */
            return false;
        }

        return true;
    }
}
