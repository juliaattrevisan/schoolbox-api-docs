<?php

/**
 * 
 * @author      Alex Tworowsky <alex.tworowsky@alaress.com.au>
 * @copyright   2011 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 * 
 */

require_once dirname(__FILE__) . '/../../library/dashboard/DashboardComponent.php';
require_once dirname(__FILE__) . '/../../library/dashboard/DashboardComponentInstance.php';
require_once dirname(__FILE__) . '/../../library/dashboard/DashboardComponentOptionDefault.php';
require_once dirname(__FILE__) . '/../../library/dashboard/DashboardComponentOption.php';

$component_model      = new DashboardComponent();
$option_default_model = new DashboardComponentOptionDefault();
$option_model         = new DashboardComponentOption();
$instance_model       = new DashboardComponentInstance($component_model, $option_default_model, $option_model, $this->template);

$this->setFile(TEMPLATE_content, 'dashboard/index.html');

/* Get component instances */
$group_id = $_SESSION['gid'];

$instance_arr = $instance_model->get_instances($group_id);

if ( is_array($instance_arr) ) {
    foreach ($instance_arr as $instance) {

        /* Execute and render */
        
        $template_file = 'dashboard/component/'.$instance->get_template().'.html';
                
        $template_target = 'component_position_'.$instance->get_position();
        $template_handle = 'component_'.$instance->get_instance_id();

        if ( $this->setFile($template_handle, $template_file) !== false ) {

            if ( $instance->execute() !== false && $instance->render($template_handle) !== false ) {
                $this->parse($template_target, $template_handle, true);
            }

        }
    }
}

?>