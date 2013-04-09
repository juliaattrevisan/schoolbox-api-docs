<?php

require_once('../../include/Bootstrap.php');


$classList = array(
            'name'  => 'Video Player',
            'module' => 'homepage',
            'component_category' => '5',
            'class' => 'Component_Homepage_VideoController',
            'description' => 'Upload and play videos directly in your homepage',
            'multiple' => 'true',
            'config' => array(),
            );


Component_DefinitionMapper::getInstance()->create($classList);