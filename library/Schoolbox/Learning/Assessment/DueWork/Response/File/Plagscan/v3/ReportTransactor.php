<?php
/**
 * Plagscan Report in OTFS
 *
 * This abstracts away all the filename generation stuff you need to do, in
 * order to generate a storage file containing the plagscan report's html
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
namespace Schoolbox\Learning\Assessment\DueWork\Response\File\Plagscan\v3;

use Schoolbox\File\Exception as StorageException;
use Schoolbox\Resource\Storage\Mapper as StorageMapper;
use Schoolbox\Resource\Storage\Model as StorageModel;


class ReportTransactor
{
    /**
     * The plagscan id to which this report belongs
     * @var int
     */
    private $plagscanId;

    /**
     * Report's html contents
     *
     * @var string
     */
    private $reportContents;


    /**
     * Report constructor.
     * @param $plagscanId
     * @param $reportContents
     */
    public function __construct($plagscanId, $reportContents)
    {
        $this->setPlagscanId($plagscanId);
        $this->setReportContents($reportContents);
    }


    /**
     * Save the report into the brownhole
     *
     * @return StorageModel
     * @throws StorageException
     */
    public function save()
    {
        $filename = $this->generateFilename($this->getPlagscanId());

        return StorageMapper::getInstance()->acceptStream($filename, $this->getReportContents());
    }


    /**
     * Generate a filename for the file that contains all the report html
     *
     * @param int $plagscanId
     * @return string
     */
    private function generateFilename($plagscanId)
    {
        return "plagscan-{$plagscanId}.html";
    }


    /**
     * @return int
     */
    private function getPlagscanId()
    {
        return $this->plagscanId;
    }


    /**
     * @param int $plagscanId
     * @return $this
     */
    private function setPlagscanId($plagscanId)
    {
        $this->plagscanId = $plagscanId;
        return $this;
    }


    /**
     * @return string
     */
    private function getReportContents()
    {
        return $this->reportContents;
    }


    /**
     * @param string $reportContents
     * @return $this
     */
    private function setReportContents($reportContents)
    {
        $this->reportContents = $reportContents;
        return $this;
    }
}