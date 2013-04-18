<?php
/**
 * Creates new wolfram alpha component for adding widgets
 *
 * @author      James Leckie <james.leckie@alaress.com.au>
 * @copyright   2013 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 */

require_once('../../../include/Bootstrap.php');


$classList = array(
	'name'  => 'Wolfram|Alpha',
	'module' => 'homepage',
	'component_category' => '5',
	'class' => 'Component_Homepage_WolframController',
	'description' => 'Embed <a href="http://www.wolframalpha.com/widgets/" target="_blank">Wolfram Alpha widgets</a> directly into your page',
	'multiple' => 'true',
	'config' => array(
		'url' => array('label' => 'URL or Embed Code',
						'default_value'=>''),
	),
);


Component_DefinitionMapper::getInstance()->create($classList);