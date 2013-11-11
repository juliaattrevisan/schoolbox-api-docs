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
	'description' => 'Allow comments, links, images, video and files to be posted in a collaborative stream.',
	'multiple' => 'true',
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
		'collaborate' => array(
			'default_value' => '1',
			'label' => 'Collaborate'
		),
		'commentsAllowed' => array(
			'default_value' => '1',
			'label' => 'Comments'
		),
	)
);

Component_DefinitionMapper::getInstance()->create($socialStream);