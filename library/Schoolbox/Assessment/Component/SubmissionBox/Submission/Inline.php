<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Assessment\Component\SubmissionBox\Submission;


class Inline extends AbstractSubmission
{
    /** @var bool */
    protected $draft;

    /** @var string */
    protected $body;


    /**
     * @return string
     */
    public function getBody()
    {
        return $this->body;
    }


    /**
     * @param string $body
     * @return static
     */
    public function setBody($body)
    {
        $this->body = $body;
        return $this;
    }


    /**
     * @return boolean
     */
    public function isDraft()
    {
        return $this->draft;
    }


    /**
     * @param boolean $draft
     * @return static
     */
    public function setDraft($draft)
    {
        $this->draft = $draft;
        return $this;
    }

}