<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

require_once SITE_root . '/library/group.php';
require_once SITE_root . '/library/user.php';

$groupHandler = new Group();
$userHandler = new User();

if (!$groupHandler->isSuperuser()) {
    error(19);
}

$this->setFile(TEMPLATE_content, 'adminv2/reports.html');
$this->setBlock(TEMPLATE_content, 'search_options', 'search_options_block');
$this->setBlock(TEMPLATE_content, 'search_results', 'search_results_block');
/*$this->setBlock(TEMPLATE_content, 'filter', 'filter_block');
$this->setVar('filter_block', '');*/

$this->setTitle('Admin - Reports');

if(count($this->arg) < 2) {
    error(19);
}

switch($this->arg[0]) {
    case 'group':

        if(!is_numeric($this->arg[1])) {
            error(19);
        }

        $groupReport = Report_Factory::build('group', $this);
        $groupReport->setGroupID($this->arg[1]);

        // handle group export
        if($this->arg[2] == 'exportToCSV') {
            // export to csv
            $groupReport->export(new Report_Exporter($groupReport->getGroupName()), 'CSV');
        } else {
            // render the page
            $groupReport->render();
        }

        break;

    case 'activity':

        break;

    default:
        break;
}

$exportFilter = $this->arg[0] . "/" . $this->arg[1];


$this->setVar('REPORT_FILTER', $exportFilter);


