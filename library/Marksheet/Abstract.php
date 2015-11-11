<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

abstract class Marksheet_Abstract
{

    protected $id;
    protected $name;
    protected $slug;
    protected $comment;
    protected $subHandler;
    protected $subReturnHandler;

    /**
     * The number of action buttons this mark type has in the view submission
     * and the inline grader UI.
     *
     * @var int
     */
    protected $actionButtonCount = 0;

    /**
     * @var \Schoolbox\Assessment\Component\SubmissionBox
     */
    protected $submissionBox;

    /**
     * Assorted extra params that this marksheet could use.
     *
     * @var array
     */
    protected $extraParams;

    public function __construct($mark_type, $submissionBox, $extraParams)
    {
        $this->id = $mark_type['mark_type_id'];
        $this->setName($mark_type['hr_name']);
        $this->setSlug($mark_type['slug']);
        $this->setComment($mark_type['comment']);
        $this->setSubmissionHandler(new Submission());
        $this->setSubmissionReturnHandler(new SubmissionReturn());
        $this->setExtraParams($extraParams);
        $this->setSubmissionBox($submissionBox);
    }

    /* Getters ans setters */

    public function getID()
    {
        return $this->id;
    }

    public function setName($name)
    {
        $this->name = $name;
    }

    public function getName()
    {
        return $this->name;
    }

    public function setSlug($slug)
    {
        $this->slug = $slug;
    }

    public function getSlug()
    {
        return $this->slug;
    }

    public function setComment($comment)
    {
        $this->comment = $comment;
    }

    public function getComment()
    {
        return $this->comment;
    }

    public function setSubmissionHandler($handler)
    {
        $this->subHandler = $handler;
    }

    /**
     * @return Submission
     */
    public function getSubmissionHandler()
    {
        return $this->subHandler;
    }

    public function setSubmissionReturnHandler($handler)
    {
        $this->subReturnHandler = $handler;
    }

    /**
     * @return SubmissionReturn
     */
    public function getSubmissionReturnHandler()
    {
        return $this->subReturnHandler;
    }

    /**
     * @return \Schoolbox\Assessment\Component\SubmissionBox
     */
    public function getSubmissionBox()
    {
        return $this->submissionBox;
    }

    /**
     * @param \Schoolbox\Assessment\Component\SubmissionBox $submissionBox
     *
     * @return $this
     */
    public function setSubmissionBox($submissionBox)
    {
        $this->submissionBox = $submissionBox;
        return $this;
    }

    /**
     * @return array
     */
    public function getExtraParams()
    {
        return $this->extraParams;
    }

    /**
     * @param  array $extraParams
     * @return $this
     */
    public function setExtraParams($extraParams)
    {
        $this->extraParams = $extraParams;
        return $this;
    }

    /**
     * @return int
     */
    public function getActionButtonCount()
    {
        return $this->actionButtonCount;
    }

    /**
     * Handle the differences between 0, 0.0, '', null, and false
     *
     * @param $value
     * @param $decimals
     * @param bool $blankZero
     * @return int|string
     */
    protected function cleanNumber($value, $decimals, $blankZero = true)
    {

        // handle null value and blank string, distinct from zero
        if ($value === null || $value === '') {
            return '';
        } // handle zero
        elseif ($value === 0) {
            return $blankZero ? '' : 0;
        }

        if ($decimals < 1) {
            $num = number_format($value, 1, '.', '');
            return substr($num, 0, strpos($num, '.'));
        }
        return trim(trim(number_format($value, $decimals, '.', ''), '0'), '.');
    }

    /* Abstract functions for extending classes to implement */

    /**
    * Take a UI-formatted mark, normalise it, and insert it into the database
    *
    * @abstract
    * @param $mark
    * @return mixed
    */
    abstract public function normaliseMark($mark);

    /**
     * Extract a normalised mark from the database, and format it for the UI
     *
     * @abstract
     *
     * @param $user_id
     * @param bool $asText
     * @param bool $blankZero
     * @return mixed
     */
    abstract public function retrieveMark($user_id, $asText = false, $blankZero = true);

    /**
     * Format a pre-extracted value for the UI
     *
     * @abstract
     * @param $rawMark
     * @param int $maxMark
     * @param bool $asText
     * @param bool $blankZero
     */
    abstract public function convertRawMark($rawMark, $maxMark = 0, $asText = false, $blankZero = true);

    /**
     * Use the sitepage instance passed to output the user interface elements for this marktype.
     * I think I decided against doing this in the first place (and using a switch instead) to aid Separation of Concerns.
     *
     * @abstract
     * @param SitePage $page
     * @param array $mark
     */
    abstract public function outputUI(SitePage $page, $mark);
}
