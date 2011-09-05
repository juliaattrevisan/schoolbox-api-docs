<?php

/**
 * 
 * @author      Alex Tworowsky <alex.tworowsky@alaress.com.au>
 * @copyright   2011 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 * 
 */

$this->setFile(TEMPLATE_content, 'dashboard/index.html');

/* Instance factory */
$groupID = $_SESSION['gid'];

$componentFactory = new Component_Dashboard_Factory(new User, $this->template);
$componentCollection = $componentFactory->buildComponentList($groupID);

foreach ($componentCollection as $component) {

    /* Break apart class name by underscores, get only last segment, remove last 10 characters then send it to lowercase */
    $templateName = ucfirst( substr( array_pop( explode('_', $component->definition->class) ), 0, -10 ) );

    $templateFile = 'component/dashboard/'.$templateName.'.html';

    $templateTarget = 'component_position_'.$component->position;
    $templateHandle = 'component_'.$component->id;

    if ( $component->execute() !== false ) {

        if ( $this->setFile($templateHandle, $templateFile) !== false ) {

            if ( $component->render($templateHandle) !== false ) {
                $this->parse($templateTarget, $templateHandle, true);
            }
        }

    }
}