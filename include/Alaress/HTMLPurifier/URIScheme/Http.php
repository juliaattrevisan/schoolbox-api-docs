<?php

/**
 * 
 * @author      Alex Tworowsky <alex.tworowsky@alaress.com.au>
 * @copyright   2011 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 * 
 */
 
class Alaress_HTMLPurifier_URIScheme_Http extends HTMLPurifier_URIScheme {
    public $default_port = 80;
    public $browsable = true;
    public $hierarchical = true;

    public function doValidate(&$uri, $config, $context) {
        return true;
    }

}