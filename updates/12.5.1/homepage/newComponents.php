<?php

require_once('../../../include/Bootstrap.php');


$classList = array(
            'name'  => 'Class List',
            'module' => 'homepage',
            'component_category' => '3',
            'class' => 'Component_Homepage_ClassListController',
            'description' => 'Show your students portraits on your class homepage',
            'multiple' => 'false',
            'config' => array(
                'displayPortraits' => array(
                    'default_value' => 'grid',
                    'label' => 'Layout Style'
                ),
            )
);


Component_DefinitionMapper::getInstance()->create($classList);