<?php

/**
 * EOTC Policy
 *
 * A giant wall of text
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

new \EOTC\Authenticator($this);

$configMapper = \EOTC\ConfigMapper::getInstance();
$config = $configMapper->findByColumn('name', 'policy')->first();

$this->setFile(TEMPLATE_content, 'modules/eotc/policy.html');

$this->setVar(array(
    'EOTC_POLICY' => $config->getValue()
));
