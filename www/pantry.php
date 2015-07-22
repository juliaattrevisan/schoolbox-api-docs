<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
use Schoolbox\Resource\Attachment\Zone;

/* @var SitePage $this */

$tpl = isset($this->arg[0]) ? $this->arg[0] : 'index';

$this->setFile(TEMPLATE_content, "pantry/$tpl.html");
