<?php

require_once('../../include/Bootstrap.php');


$classList = array(
            'name'  => 'Video Player',
            'module' => 'homepage',
            'component_category' => '5',
            'class' => 'Component_Homepage_VideoController',
            'description' => 'Upload and play videos directly in your homepage',
            'multiple' => 'true',
			'config' => array(
				'componentname' => array('default_value'=>'Video','label'=>'Heading'),
				'size' => array('default_value'=>10, 'label'=>'Limit'),
				'src' => array('default_value'=>'', 'label'=>'Source'),
				'video' => array('default_value'=>'', 'label'=>'Video'),
			),
            );


Component_DefinitionMapper::getInstance()->create($classList);