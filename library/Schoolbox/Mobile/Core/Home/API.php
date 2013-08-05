<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Core_Home_API extends Generic_API {

    // this method makes the mediator shut up about its 404
    public function Framework() { return new Response_HTTP(); }
}
