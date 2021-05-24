<?php

/**
 * Eportfolio_Showcase
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Eportfolio;

use Schoolbox\ISoftDeletable;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;

class Showcase implements ISoftDeletable
{
    use DeletedAtDate;

    /**
     * The showcase item id
     *
     * @var int
     */
    private $id;

    /**
     * The showcased submission box
     *
     * @var \Schoolbox\Assessment\Component\SubmissionBox
     */
    private $submissionBox;

    /**
     * The showcasing user
     *
     * @var int
     */
    private $uid;

    /**
     * When the Showcased Work item was created
     *
     * @var \Alaress_Date_Format
     */
    private $created_at;

    /**
     * When the Showcased Work item was updated
     *
     * @var \Alaress_Date_Format
     */
    private $updated_at;

    /* Getters/Setters
     */

    /**
     * Set id
     *
     * @param int $id
     */
    public function setId($id)
    {
        $this->id = $id;
    }

    /**
     * Get id
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set submission box id
     *
     * @param \Schoolbox\Assessment\Component\SubmissionBox $submissionBox
     */
    public function setSubmissionBox($submissionBox)
    {
        $this->submissionBox = $submissionBox;
    }

    /**
     * Get submission box
     *
     * @return \Schoolbox\Assessment\Component\SubmissionBox
     */
    public function getSubmissionBox()
    {
        return $this->submissionBox;
    }

    /**
     * Set uid
     *
     * @param int $uid
     */
    public function setUid($uid)
    {
        $this->uid = $uid;
    }

    /**
     * Get uid
     *
     * @return int
     */
    public function getUid()
    {
        return $this->uid;
    }

    /**
     * Set created at
     *
     * @param \Alaress_Date_Format $created_at
     */
    public function setCreatedAt($created_at)
    {
        $this->created_at = $created_at;
    }

    /**
     * Get created at
     *
     * @return \Alaress_Date_Format
     */
    public function getCreatedAt()
    {
        return $this->created_at;
    }

    /**
     * Set updated at
     *
     * @param \Alaress_Date_Format $updated_at
     */
    public function setUpdatedAt($updated_at)
    {
        $this->updated_at = $updated_at;
    }

    /**
     * Get updated at
     *
     * @return \Alaress_Date_Format
     */
    public function getUpdatedAt()
    {
        return $this->updated_at;
    }
}
