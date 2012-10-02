<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

if(!isset($this)) {
    require_once('../include/Bootstrap.php');
    SitePage::localRedir('/iframe');
}

$this->setFile(TEMPLATE_content, 'iframe.html');