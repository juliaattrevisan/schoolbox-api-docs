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

        if(class_exists($className)) {
            $subHandler = new Submission();
            $markType = $subHandler->getMarkTypeBySlug($slug);
            return new $className($markType);
        } else {
            return null;
        }
    }

    public static function buildByID($id) {
        // if there's a problem, default to percentage
        if(!is_numeric($id))
            return Marksheet_Factory::build('percent');
        $subHandler = new Submission();
        $marktype = $subHandler->getMarkTypeByID($id);

        return Marksheet_Factory::build($marktype['slug']);
    }
}
