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
$dashboardFactory = new Component_Dashboard_DashboardFactory($this->template, $groupID);

/* Get Component Instances */
$componentArray = $dashboardFactory->buildComponentList();

if ( is_array($componentArray) ) {
    foreach ($componentArray as $component) {

        $template_file = 'dashboard/component/'.$instance->template().'.html';

        $template_target = 'component_position_'.$instance->get_position();
        $template_handle = 'component_'.$instance->get_instance_id();

        if ( $this->setFile($template_handle, $template_file) !== false ) {

            if ( $instance->execute() !== false && $instance->render($template_handle) !== false ) {
                $this->parse($template_target, $template_handle, true);
            }

        }
    }
}