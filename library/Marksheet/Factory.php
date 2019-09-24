<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Schoolbox\Assessment\Component\SubmissionBox;

class Marksheet_Factory
{

    private static $marktypes = array('free','letter','none','percent','raw','rubric');

    /**
     * @static
     *
     * @todo remove $extraParams as it's not used now $submissionBox is passed.
     *
     * @param string        $slug          Used to generate the class name
     * @param SubmissionBox $submissionBox Used by convertRawMark() to convert a raw mark
     *                                     into the appropriate format.
     * @param array         $extraParams   Any extra params to pass to the constructor
     *
     * @return Marksheet_Abstract
     */
    public static function build($slug, SubmissionBox $submissionBox, $extraParams = array())
    {
        $className = "Marksheet_" . ucwords(strtolower($slug));

        $markTypeMapper = SubmissionBox\MarkTypeMapper::getInstance();
        $markType       = $markTypeMapper->findByColumn('slug', $slug)->first();

        if (class_exists($className) && in_array(strtolower($slug), self::$marktypes)) {
            return new $className($markType, $submissionBox, $extraParams);
        } else {
            // if something went wrong, assume free for legacy
            return new Marksheet_Free($markType, $submissionBox, $extraParams);
        }
    }
}
