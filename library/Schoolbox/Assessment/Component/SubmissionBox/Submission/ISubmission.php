<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Assessment\Component\SubmissionBox\Submission;


use Alaress_Date_Format;
use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\User;

interface ISubmission
{

    /**
     * @return int
     */
    public function getId();

    /**
     * @return Alaress_Date_Format
     */
    public function getCreatedAt();

    /**
     * @return SubmissionBox
     */
    public function getSubmissionBox();

    /**
     * @return User
     */
    public function getOwner();

    /**
     * @return bool
     */
    public function isMarked();

    /**
     * @return bool
     */
    public function isLate();

} 