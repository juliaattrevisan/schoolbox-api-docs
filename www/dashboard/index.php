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

$component_model = new DashboardComponent();
$option_default_model = new DashboardComponentOptionDefault();
$option_model = new DashboardComponentOption();
$instance_model = new DashboardComponentInstance($component_model, $option_default_model, $option_model, $this->template);

$instance_arr = $instance_model->get_instances(0);

var_dump($instance_arr);

$this->setFile(TEMPLATE_content, 'dashboard/index.html');

?>