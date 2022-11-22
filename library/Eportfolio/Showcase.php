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
use Schoolbox\Mapper\Traits\Model\CreatedAtDate;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use Schoolbox\Mapper\Traits\Model\UpdatedAtDate;

class Showcase implements ISoftDeletable
{
    use CreatedAtDate;
    use UpdatedAtDate;
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
}
