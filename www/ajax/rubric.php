<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

if(!isset($this)) {
    require_once('../../library/Bootstrap.php');
    error(2);
}

if(count($this->arg) < 2) {
    error(2);
}

$rb = new DueWork_Rubric_Lightbox($this);
$rb->render(TEMPLATE_content, $this->arg[0], $this->arg[1]);
$this->setAjax();
$this->template->pparse('finaloutput', TEMPLATE_content);
