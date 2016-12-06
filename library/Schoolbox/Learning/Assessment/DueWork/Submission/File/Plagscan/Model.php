<?php
/**
 * Plagscan
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Learning\Assessment\DueWork\Submission\File\Plagscan;

use Schoolbox\Mapper\Traits\Model\CreatedAtDate;
use Schoolbox\Mapper\Traits\Model\UpdatedAtDate;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;

class Model
{
    use CreatedAtDate;
    use UpdatedAtDate;
    use DeletedAtDate;

    /**
     * Plagscan queued for processing
     */
    const STATUS_QUEUED = 1;

    /**
     * Plagscan currently in progress
     */
    const STATUS_PROCESSING = 2;

    /**
     * Plagscan completed, with results
     */
    const STATUS_COMPLETE = 3;

    /**
     * Status of the document, if it's returned as unscannable?
     */
    const STATUS_UNSCANNABLE = -1;

    /**
     * @var int
     */
    protected $id;

    /**
     * Some id used by the third-party Plagscan
     *
     * @var int
     */
    protected $plagscanId;

    /**
     * @var int
     */
    protected $status;

    /**
     * @var float
     */
    protected $score;

    /**
     * @var string
     */
    protected $html;

    /**
     * @var int
     */
    protected $studentSubmissionFileId;

    /**
     * Figure out the url to the plagscan report
     *
     * @param $requestScheme
     * @param $host
     * @return string
     */
    public function getUrl($requestScheme, $host)
    {
        return sprintf(
            "%s://%s/submission/plagiarismReport/%s/#zx_h=672px",
            $requestScheme,
            $host,
            $this->getPlagscanId()
        );
    }

    /**
     * Plagscan queued for processing
     *
     * @return bool
     */
    public function isPending()
    {
        return $this->getStatus() == self::STATUS_QUEUED;
    }

    /**
     * Plagscan currently in progress
     *
     * @return bool
     */
    public function isProcessing()
    {
        return $this->getStatus() == self::STATUS_PROCESSING;
    }

    /**
     * Plagscan completed
     *
     * @return bool
     */
    public function isComplete()
    {
        return $this->getStatus() == self::STATUS_COMPLETE;
    }

    /**
     * This document is unplaggable
     *
     * @return bool
     */
    public function isUnscannable()
    {
        return $this->getStatus() == self::STATUS_UNSCANNABLE;
    }

    /* Getters/Setters
     */

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param int $id
     * @return self
     */
    public function setId($id)
    {
        $this->id = $id;
        return $this;
    }

    /**
     * @return int
     */
    public function getPlagscanId()
    {
        return $this->plagscanId;
    }

    /**
     * @param int $plagscanId
     * @return $this
     */
    public function setPlagscanId($plagscanId)
    {
        $this->plagscanId = $plagscanId;
        return $this;
    }

    /**
     * @return int
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * @param int $status
     * @return self
     */
    public function setStatus($status)
    {
        $this->status = $status;
        return $this;
    }

    /**
     * @return float
     */
    public function getScore()
    {
        return $this->score;
    }

    /**
     * @param float $score
     * @return self
     */
    public function setScore($score)
    {
        $this->score = $score;
        return $this;
    }

    /**
     * @return string
     */
    public function getHtml()
    {
        return $this->html;
    }

    /**
     * @param string $html
     * @return self
     */
    public function setHtml($html)
    {
        $this->html = $html;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getStudentSubmissionFileId()
    {
        return $this->studentSubmissionFileId;
    }

    /**
     * @param mixed $studentSubmissionFileId
     * @return $this
     */
    public function setStudentSubmissionFileId($studentSubmissionFileId)
    {
        $this->studentSubmissionFileId = $studentSubmissionFileId;
        return $this;
    }
}