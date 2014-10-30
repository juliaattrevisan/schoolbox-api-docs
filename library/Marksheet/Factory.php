<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Marksheet_Factory
{

    private static $marktypes = array('free','letter','none','percent','raw','rubric');

    /**
     * @static
     * @param string $slug        Used to generate the class name
     * @param array  $extraParams Any extra params to pass to the constructor
     * @return Marksheet_Abstract|null
     */
    public static function build($slug, $extraParams = array())
    {
        $className = "Marksheet_" . ucwords(strtolower($slug));

        $subHandler = new Submission();
        $markType = $subHandler->getMarkTypeBySlug($slug);

        if (class_exists($className) && in_array(strtolower($slug), self::$marktypes)) {
            return new $className($markType, $extraParams);
        } else {
            // if something went wrong, assume free for legacy
            return new Marksheet_Free($markType, $extraParams);
        }
    }
}
