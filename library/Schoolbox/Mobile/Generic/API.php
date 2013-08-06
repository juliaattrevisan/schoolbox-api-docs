<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Mobile\Generic;

abstract class API {

    protected $responseCode;

    public function __construct($responseCode) {
        $this->responseCode = $responseCode;
    }

}
