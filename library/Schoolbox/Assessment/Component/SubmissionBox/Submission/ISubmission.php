<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Assessment\Component\SubmissionBox\Submission;


use Schoolbox\Assessment\Component\SubmissionBox;

interface ISubmission
{

    /**
     * @return SubmissionBox
     */
    public function getSubmissionBox();

    /**
     * @return bool
     */
    public function isMarked();

    /**
     * @return bool
     */
    public function isLate();

} 