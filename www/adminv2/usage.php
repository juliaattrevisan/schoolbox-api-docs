<?php
/**
 * ${NAME}
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

if (!$_SESSION['user']->isSuperuser()) {
	error(19);
}


$this->setFile(TEMPLATE_content, 'adminv2/usage.html');
