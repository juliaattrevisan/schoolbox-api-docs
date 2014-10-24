<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Marksheet_Factory {

    private static $marktypes = array('free','letter','none','percent','raw','rubric');

    /**
     * @static
     * @param $slug
     * @return Marksheet_Abstract|null
     */
    public static function build($slug) {
        $className = "Marksheet_" . ucwords(strtolower($slug));

        $subHandler = new Submission();
        $markType = $subHandler->getMarkTypeBySlug($slug);

        if(class_exists($className) && in_array(strtolower($slug), self::$marktypes)) {
            return new $className($markType);
        }
        else {
            // if something went wrong, assume free for legacy
            return new Marksheet_Free($markType);
        }
    }
}
