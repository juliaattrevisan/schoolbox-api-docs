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
$this->setBlock(TEMPLATE_content, 'filter', 'filter_block');
$this->setVar('filter_block', '');

$this->setTitle('Admin - Reports');

if(count($this->arg) < 2) {
    error(19);
}

// url expected to be in the format:
//   /adminv2/reports/reportType/reportVar/[sortBy/[sortDir/]][page|export]
$reportType = $this->arg[0];
$reportVar = $this->arg[1];
$exportToCSV = false;

// default sorting
if($this->arg[2] == "exportToCSV") {
    $page = 0; //export, so no pagination
    $orderBy = "";
    $orderDir = "ASC";
    $exportToCSV = true;
}
// page number
elseif(is_numeric($this->arg[2])) {
    $page = $this->arg[2];
    $orderBy = "";
    $orderDir = "ASC";
}
// sortBy
else {
    $page = 1;
    $orderBy = $this->arg[2];
    if(strtolower($this->arg[3]) == "desc") {
        $orderDir = "DESC";
    } else
        $orderDir = "ASC";
        if($this->arg[4] == "exportToCSV") {
            $page = 0; //export, so no pagination
            $exportToCSV = true;
        }
        // page number
        elseif(is_numeric($this->arg[4])) {
            $page = $this->arg[4];
        }
}

/* Don't worry about bounds checking here - the implementation takes care of it */

// setup pagination vars from args
$limit = Cfg::get('search_type_limit') * 2;
$offset = ($page - 1) * $limit;

$report = Report_Factory::build($reportType, $this);

if(!$report)
    error(19);

$report->setIndexID($reportVar);

if(!($totalRows = $report->populateDataset($offset, $limit, $orderBy, $orderDir))) {
    error(19);
}

// handle group export
if($exportToCSV) {
    // export to csv
    $report->export(new Report_Exporter($report->getReportTitle()), 'CSV');
} else {
    // render the page

    // iterate across the headers specified and set visual sorting
    $headers = array_keys($report->getFields());

    foreach($headers as $header) {
        $ref = strtoupper($header);

        if($header == $orderBy) {
            $this->setVar("SORT_{$ref}", 'orderby');

            if($orderDir == "ASC") {
                $this->setVar("SORT_{$ref}_DIR", "desc");
                $this->setVar('DIR', '\0025B4');
            } else {
                $this->setVar("SORT_{$ref}_DIR", "asc");
                $this->setVar('DIR', '\0025BE');
            }

        } else {
            $this->setVar("SORT_{$ref}", '');
            $this->setVar("SORT_{$ref}_DIR", 'asc');
        }


    }




    // figure out which pagination buttons should be displayed
    $pageCount = ceil($totalRows / $limit);

    if($page < 2)
        $this->setVar('previous_button_block', '');
    else
        $this->parse('previous_button_block', 'previous_button');

    if($page >= $pageCount)
        $this->setVar('next_button_block', '');
    else
        $this->parse('next_button_block', 'next_button');

    $pageSlug = strtolower("$reportType/$reportVar/" . ( $orderBy ? "$orderBy/$orderDir/" : '' ) );

    if($pageCount > 1) {
        for($j = 1; $j <= $pageCount; $j++) {
            if($page == $j)
                $this->setVar('CURRENT', ' current');
            else
                $this->setVar('CURRENT', '');

            $this->setVar('PAGE_INDEX', $j);
            $this->setVar('PAGE_INDEX_REF', $pageSlug . $j);

            $this->parse('page_number_block','page_number', true);
        }
    }
    else
        $this->setVar('page_number_block', '');

    $outputPagination = array(
        'PREVIOUS_PAGE' => $pageSlug . ($page - 1),
        'NEXT_PAGE'		=> $pageSlug . ($page + 1),
        'LAST_PAGE'		=> $pageSlug . $pageCount,
    );

    $this->setVar($outputPagination);

    $this->setVar('REPORT_FILTER', $pageSlug );

    $report->render();
}

