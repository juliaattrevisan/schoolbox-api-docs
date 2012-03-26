<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

abstract class Marksheet_Abstract {

    protected $id;
    protected $name;
    protected $slug;
    protected $comment;
    protected $subHandler;
    protected $subReturnHandler;

    public function __construct($mark_type) {
        $this->id = $mark_type['mark_type_id'];
        $this->setName($mark_type['hr_name']);
        $this->setSlug($mark_type['slug']);
        $this->setComment($mark_type['comment']);
        $this->setSubmissionHandler(new Submission());
        $this->setSubmissionReturnHandler(new SubmissionReturn());
    }

    /* Getters ans setters */

    public function getID() {
        return $this->id;
    }

    public function setName($name) {
        $this->name = $name;
    }

    public function getName() {
        return $this->name;
    }

    public function setSlug($slug) {
        $this->slug = $slug;
    }

    public function getSlug() {
        return $this->slug;
    }

    public function setComment($comment) {
        $this->comment = $comment;
    }

    public function getComment() {
        return $this->comment;
    }

    public function setSubmissionHandler($handler) {
        $this->subHandler = $handler;
    }

    /**
     * @return Submission
     */
    public function getSubmissionHandler() {
        return $this->subHandler;
    }

    public function setSubmissionReturnHandler($handler) {
        $this->subReturnHandler = $handler;
    }

    /**
     * @return SubmissionReturn
     */
    public function getSubmissionReturnHandler() {
        return $this->subReturnHandler;
    }

    function clean_num($value, $decimals){
        if($value == null)
            return "";

        if($decimals < 1) {
            $num = number_format($value, 1, '.', '');
            return substr($num, 0, strpos($num, '.'));
        }
        return trim(trim(number_format($value, $decimals, '.', ''),'0'),'.');
    }

    /* Abstract functions for extending classes to implement */

    /**
     * @abstract
     * Take a UI-formatted mark, normalise it, and insert it into the database
     */
    public abstract function normaliseMark($mark, $subbox);

    /**
     * @abstract
     * Extract a normalised mark from the database, and format it for the UI
     */
    public abstract function retrieveMark($user_id, $box_id, $asText = false);

    /**
     * @abstract
     * @param $submissionID
     * Extract a normalised mark from the databse, and format it for the UI
     */
    public abstract function retrieveMarkBySubmissionID($submissionID, $asText = false);

    /**
     * @abstract
     * @param $rawMark
     * Convert an already-retrieved mark for UI
     */
    public abstract function convertRawMark($rawMark, $maxMark = 0, $asText = false);
}