<?php

/**
 * 
 * @author      Alex Tworowsky <alex.tworowsky@alaress.com.au>
 * @copyright   2011 Alaress Pty Ltd
 * @link        https://schoolbox.com.au/
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
