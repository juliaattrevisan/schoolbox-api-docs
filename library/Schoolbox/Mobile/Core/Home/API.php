<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Mobile\Core\Home;

use Schoolbox\Mobile\Generic;
use Response_HTTP;

class API extends Generic\API {

    // this method makes the mediator shut up about its 404
    public function Framework() { return new Response_HTTP(); }
}
