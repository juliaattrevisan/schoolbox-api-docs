<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class View_Factory {

    /**
     * Function to determine which class should be loaded for the rendering of data to the client.
     * Polymorphic code would be ideal in this instance, but in the case of only 3 types of return,
     * a switch is more than sufficient.
     *
     * @static
     * @param $viewName
     * @return View_Interface|View_Site|View_HTML|View_JTML|View_JSON|View_File
     */
    public static function build($viewName) {
        // this will be replaced with dynamic code, but later
        switch(strtolower($viewName)) {

            // will implement csv and vcal templating for certain pages in due course
            case 'csv':
            case 'vcal':
            default:
                return new View_Site();

            case 'html':
                return new View_HTML();

            // jtml is a bridge between html and json: it's a json response with html rendered within its articles
            case 'jtml':
                return new View_JTML();

            case 'json':
                return new View_JSON();

            case 'file':
                return new View_File();
        }
    }
}
