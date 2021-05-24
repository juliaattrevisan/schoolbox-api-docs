<?php

/**
 * Mapping
 *
 * The ints used in getUrgentIndex() etc. don't necessarily correspond
 * to the consts defined in EventStatusMapper. These ints are used purely
 * within this class
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace EOTC\ListGroup;

class Mapping
{

    /**
     * Event List Group mapping
     *
     * @var array
     */
    private $mapping = array(
        'Urgent',
        'Awaiting Final Approval',
        'Confirm {:eotcRamsAbbreviation|p}',
        'Pending Initial Application Review',
        'Pending Details',
        'Approved',
        'Cancelled',
    );

    /**
     * Constructor
     */
    public function __construct()
    {
    }

    /**
     * The human-readable version of the name
     *
     * @param string $index
     * @return mixed
     */
    public function getName($index)
    {
        return $this->mapping[$index];
    }

    /**
     * Index of "Urgent" events
     *
     * @return int
     */
    public function getUrgentIndex()
    {
        return 0;
    }

    /**
     * Index of Pending Final Approval events
     *
     * @return int
     */
    public function getAwaitingFinalApprovalReviewIndex()
    {
        return 1;
    }

    /**
     * Index of Pending Confirm RAMs events
     *
     * @return int
     */
    public function getConfirmRAMsReviewIndex()
    {
        return 2;
    }

    /**
     * Index of Pending Final Review events
     *
     * @return int
     */
    public function getPendingInPrincipleReviewIndex()
    {
        return 3;
    }

    /**
     * Index of Pending Details events
     *
     * @return int
     */
    public function getPendingDetailsReviewIndex()
    {
        return 4;
    }

    /**
     * Index of Pending Initial Review events
     *
     * @return int
     */
    public function getApprovedIndex()
    {
        return 5;
    }

    /**
     * Index of Pending Initial Review events
     *
     * @return int
     */
    public function getRejectedIndex()
    {
        return 6;
    }

    /* Getters/Setters
     */

    /**
     * Get mapping
     *
     * @return array
     */
    public function getMapping()
    {
        return $this->mapping;
    }
}
