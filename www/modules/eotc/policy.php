<?php
/**
 * ${NAME}
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

$configMapper = new \EOTC\ConfigMapper();
$config = $configMapper->findByColumn('name', 'policy')->first();

$this->setFile(TEMPLATE_content, 'modules/eotc/policy.html');

$this->setVar(array(
	'EOTC_POLICY' => $config->getValue()
));