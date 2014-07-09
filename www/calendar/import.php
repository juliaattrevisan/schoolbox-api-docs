<?php
/**
 * ${NAME}
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
require_once dirname(__FILE__) . '/../../include/Bootstrap.php';

$this->setFile(TEMPLATE_content, 'calendar/import.html');

$this->setVar(array(
	'HOMEPAGE_ID' => $this->arg[0]
));