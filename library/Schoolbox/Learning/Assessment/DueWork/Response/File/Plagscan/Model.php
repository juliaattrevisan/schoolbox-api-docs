<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment\DueWork\Response\File\Plagscan;

use Alaress_Date_Format;
use Schoolbox\ISoftDeletable;
use Schoolbox\Mapper\Traits\Model\CreatedAtDate;
use Schoolbox\Mapper\Traits\Model\UpdatedAtDate;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use Schoolbox\Resource\Storage\IStorage;
use Schoolbox\Resource\File\StorableTrait;

/**
 * Class Model
 *
 * Plagscan document
 *
 * @package Schoolbox\Learning\Assessment\DueWork\Response\File\Plagscan
 */
class Model implements IStorage, ISoftDeletable
{
    use StorableTrait;
    use CreatedAtDate;
    use UpdatedAtDate;
    use DeletedAtDate;

    /**
     * Documents that are queued to be uploaded to plagscan servers
     */
    public const STATUS_QUEUED = 1;

    /**
     * Documents that have been uploaded to plagscan but have not yet finished being analysed and thus provided a result
     */
    public const STATUS_PROCESSING = 2;

    /**
     * Plagscan completed, with results
     */
    public const STATUS_COMPLETE = 3;

    /**
     * Documents that have returned errors from plagscan, for example deleted or cannot be analysed
     */
    public const STATUS_UNSCANNABLE = -1;

    /**
     * @var int Some id used by the third-party Plagscan
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
     * A url to the Mode 7 Interactive Report that was introduced in Plagscan v3.0
     *
     * @var string
     */
    protected $interactiveUrl;

    /**
     * @var int
     */
    protected $studentSubmissionFileId;

    /**
     * @var Alaress_Date_Format
     */
    protected $privatisedAt;

    /**
     * Figure out the url to the plagscan report
     *
     * @param string $requestScheme
     * @param string $host
     *
     * @return string
     */
    public function getUrl($requestScheme, $host): string
    {
        return sprintf(
            '%s://%s/submission/plagiarismReport/%s/#zx_h=672px',
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
    public function isPending(): bool
    {
        return $this->getStatus() === self::STATUS_QUEUED;
    }

    /**
     * Plagscan currently in progress
     *
     * @return bool
     */
    public function isProcessing(): bool
    {
        return $this->getStatus() === self::STATUS_PROCESSING;
    }

    /**
     * Plagscan completed
     *
     * @return bool
     */
    public function isComplete(): bool
    {
        return $this->getStatus() === self::STATUS_COMPLETE;
    }

    /**
     * This document is unplaggable
     *
     * Documents which can't be plagscanned won't have a record in the db
     * Because of hydration, it will be attached with an empty Plagscan/Model
     * instance - so check for legacy STATUS_UNSCANNABLE or null
     *
     * @return bool
     */
    public function isUnscannable(): bool
    {
        return $this->getStatus() === self::STATUS_UNSCANNABLE || $this->getStatus() === null;
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
     *
     * @return $this
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
     *
     * @return $this
     */
    public function setPlagscanId($plagscanId)
    {
        $this->plagscanId = $plagscanId;

        return $this;
    }

    /**
     * @return int|null
     */
    public function getStatus(): ?int
    {
        return $this->status;
    }

    /**
     * @param int $status
     *
     * @return $this
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
     *
     * @return self|$this
     */
    public function setScore($score)
    {
        $this->score = $score;

        return $this;
    }

    /**
     * @return string
     */
    public function getInteractiveUrl()
    {
        return $this->interactiveUrl;
    }

    /**
     * @param string $interactiveUrl
     *
     * @return self|$this
     */
    public function setInteractiveUrl($interactiveUrl)
    {
        $this->interactiveUrl = $interactiveUrl;

        return $this;
    }

    /**
     * @return int
     */
    public function getStudentSubmissionFileId()
    {
        return $this->studentSubmissionFileId;
    }

    /**
     * @param int $studentSubmissionFileId
     *
     * @return self|$this
     */
    public function setStudentSubmissionFileId($studentSubmissionFileId)
    {
        $this->studentSubmissionFileId = $studentSubmissionFileId;

        return $this;
    }

    /**
     * @return Alaress_Date_Format
     */
    public function getPrivatisedAt()
    {
        return $this->privatisedAt;
    }

    /**
     * @param Alaress_Date_Format $privatisedAt
     *
     * @return self|$this
     */
    public function setPrivatisedAt($privatisedAt)
    {
        $this->privatisedAt = $privatisedAt;

        return $this;
    }
}
