<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Marksheet_Factory {

    /**
     * @static
     * @param $slug
     * @return Marksheet_Abstract|null
     */
    public static function build($slug) {
        $className = "Marksheet_" . ucwords(strtolower($slug));

        $subHandler = new Submission();
        $markType = $subHandler->getMarkTypeBySlug($slug);

        if(class_exists($className)) {
            return new $className($markType);
        } else {
            // if something went wrong, assume free for legacy
            return new Marksheet_Free($markType);
        }
    }

    public static function buildByID($id) {
        // if there's a problem, default to free for legacy
        if(!is_numeric($id))
            return Marksheet_Factory::build('percent');
        $subHandler = new Submission();
        $marktype = $subHandler->getMarkTypeByID($id);

        return Marksheet_Factory::build($marktype['slug']);
    }
}
