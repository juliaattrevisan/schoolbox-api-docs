<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

// expecting POST to contain the identifier for the handle
//if($_REQUEST['templatehandle']) {
    $this->setFile(TEMPLATE_content, 'resource_browser.html');
    $rb = new Resource_Browser($this);
    $rb->render($_REQUEST['templatehandle']);
    $this->setAjax();
    $this->template->pparse('finaloutput', TEMPLATE_content);
/*
}

// templatehandle not set

else {
    // die quietly
    echo "no handle";
}*/
?>