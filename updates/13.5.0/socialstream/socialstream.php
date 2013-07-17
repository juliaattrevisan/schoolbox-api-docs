<?php
/**
 * ${NAME}
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

require_once('../../../include/Bootstrap.php');

$socialStream = array(
	'name'  => 'Social Stream',
	'module' => 'homepage',
	'component_category' => '2',
	'class' => 'Component_Homepage_SocialStreamController',
	'description' => 'A social stream.',
	'multiple' => 'false',
	'config' => array(
		'componentname' => array(
			'default_value' => 'My Social Stream',
			'label' => 'Stream Title',
		),
		'size' => array(
			'default_value' => '5',
			'label' => 'Size'
		),
		'src' => array(
			'default_value' => '',
			'label' => 'Source'
		),
	)
);

Component_DefinitionMapper::getInstance()->create($socialStream);