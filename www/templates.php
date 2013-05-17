<?php
/**
 * ${NAME}
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
require_once('../include/Bootstrap.php');

$templateMapper = new \Eportfolio\TemplateMapper();

$template = new \Eportfolio\Template();
$template->setName('Mortimer');
$template->setLeftColumnWidth('17.4');
$template->setRightColumnWidth('80');

$templateMapper->save($template);