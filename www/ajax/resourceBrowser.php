<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

$this->setFile(TEMPLATE_content, 'resource_browser.html');
$rb = new Resource_Browser($this);
$rb->render(TEMPLATE_content, $_REQUEST['destination']);
$this->setAjax();
$this->template->pparse('finaloutput', TEMPLATE_content);

?>