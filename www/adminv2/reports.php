<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Schoolbox\Admin\Navigation\Subnav;

$userHandler = new User();

if (!$_SESSION['user']->isSuperuser()) {
    error(19);
}
/** @var SitePage $this */
$this->setFile(TEMPLATE_content, 'adminv2/reports.html');
$this->setBlock(TEMPLATE_content, 'search_options', 'search_options_block');
$this->setBlock(TEMPLATE_content, 'search_results', 'search_results_block');
$this->setBlock(TEMPLATE_content, 'previous_button', 'previous_button_block');
$this->setBlock(TEMPLATE_content, 'first_page', 'first_page_block');
$this->setBlock(TEMPLATE_content, 'last_page', 'last_page_block');
$this->setBlock(TEMPLATE_content, 'page_number', 'page_number_block');
$this->setBlock(TEMPLATE_content, 'next_button', 'next_button_block');
$this->setBlock(TEMPLATE_content, 'filter', 'filter_block');
$this->setBlock(TEMPLATE_content, 'download_report', 'download_report_block');
$this->setVar('filter_block', '');
$this->setTitle('Admin - Reports');

if (count($this->arg) < 2) {
    error(2);
}

// url expected to be in the format:
//   /adminv2/reports/reportType/reportVar/[sortBy/[sortDir/]][page|export]
$reportType  = $this->arg[0] ?? '';
$reportVar   = urldecode($this->arg[1] ?? '');
$exportToCSV = false;

// default sorting
$orderBy  = '';
$orderDir = '';
if (isset($this->arg[2]) && $this->arg[2] == "exportToCSV") {
    $page        = 0; //export, so no pagination
    $exportToCSV = true;
} elseif (isset($this->arg[2]) && is_numeric($this->arg[2])) {
    // page number
    $page    = $this->arg[2];
} else {
    // sortBy
    $page    = 1;
    $orderBy = $this->arg[2] ?? '';

    // if direction is unspecified, leave it to the report itself to decide its preference
    switch (strtolower($this->arg[3] ?? '')) {
        case 'desc':
            $orderDir = "DESC";
            break;
        case 'asc':
            $orderDir = "ASC";
            break;
        default:
            break;
    }

    if (isset($this->arg[4]) && $this->arg[4] == "exportToCSV") {
        $page        = 0; //export, so no pagination
        $exportToCSV = true;
    } elseif (isset($this->arg[4]) && is_numeric($this->arg[4])) {
        // page number
        $page = $this->arg[4];
    }
}

// include the admin navigation
(new Subnav($this->getDi()->get('config')))
    ->generatePhplib($this, "/adminv2/reports/{$reportType}/all")
    ->generateBreadcrumbsHtml($this, "/adminv2/reports/{$reportType}/all");

/* Don't worry about bounds checking here - the implementation takes care of it */

// setup pagination vars from args
if (!$exportToCSV) {
    $limit  = Config::get('search_type_limit') * 2;
    $offset = ($page - 1) * $limit;
} else {
    $limit = $offset = 0;
}

$report = Report_Factory::build($reportType, $this);

if (!$report) {
    error(19);
}

$report->setIndexID($reportVar);

$slugDir = $orderDir ? $orderDir : 'ASC';

$pageSlug = strtolower("$reportType/$reportVar/" . ($orderBy ? "$orderBy/$slugDir/" : ''));
$this->setVar('REPORT_FILTER', $pageSlug);
$this->setVar('RETURN', $reportType);

// if there are results
if (($totalRows = $report->populateDataset($offset, $limit, $orderBy, $orderDir)) > 0) {
    // display the download button
    $this->parse('download_report_block', 'download_report', false);

    // handle group export
    if ($exportToCSV) {
        // export to csv
        $report->export();
    } else {
        // render the page

        // iterate across the headers specified and set visual sorting
        $headers = array_keys($report->getFields());

        foreach ($headers as $header) {
            $ref = strtoupper($header);

            if ($header == $orderBy) {
                $this->setVar("SORT_{$ref}", 'orderby');

                if ($orderDir == "DESC") {
                    $this->setVar("SORT_{$ref}_DIR", "asc");
                    $this->setVar('DIR', '\0025BE');
                } else {
                    $this->setVar("SORT_{$ref}_DIR", "desc");
                    $this->setVar('DIR', '\0025B4');
                }
            } else {
                $this->setVar("SORT_{$ref}", '');
                $this->setVar("SORT_{$ref}_DIR", 'asc');
            }
        }

        // figure out which pagination buttons should be displayed
        $pageCount = ceil($totalRows / $limit);

        if ($page < 2) {
            $this->setVar('previous_button_block', '');
        } else {
            $this->parse('previous_button_block', 'previous_button');
        }

        if ($page >= $pageCount) {
            $this->setVar('next_button_block', '');
        } else {
            $this->parse('next_button_block', 'next_button');
        }


        if ($pageCount > 1) {
            // show this many pages either side of the currently selected page
            $eitherSide = 5;

            $start = ($page - $eitherSide > 1) ? $page - $eitherSide : 1;
            $end   = ($page + $eitherSide < $pageCount) ? $page + $eitherSide : $pageCount;

            // if needed, add the start button
            if ($start != 1) {
                $this->setVar('PAGE_INDEX', 1);
                $this->setVar('PAGE_INDEX_REF', $pageSlug . '1');
                $this->parse('first_page_block', 'first_page', true);
            } else {
                $this->setVar('first_page_block', '');
            }

            // if needed, add the end button
            if ($end != $pageCount) {
                $this->setVar('PAGE_INDEX', $pageCount);
                $this->setVar('PAGE_INDEX_REF', $pageSlug . $pageCount);
                $this->parse('last_page_block', 'last_page', true);
            } else {
                $this->setVar('last_page_block', '');
            }

            for ($j = $start; $j <= $end; $j++) {
                if ($page == $j) {
                    $this->setVar('CURRENT', ' current');
                } else {
                    $this->setVar('CURRENT', '');
                }

                $this->setVar('PAGE_INDEX', $j);
                $this->setVar('PAGE_INDEX_REF', $pageSlug . $j);

                $this->parse('page_number_block', 'page_number', true);
            }
        } else {
            $this->setVar('page_number_block', '');
            $this->setVar('last_page_block', '');
            $this->setVar('first_page_block', '');
        }
        $outputPagination = [
            'PREVIOUS_PAGE' => $pageSlug . ($page - 1),
            'NEXT_PAGE'     => $pageSlug . ($page + 1),
            'LAST_PAGE'     => $pageSlug . $pageCount,
        ];

        $this->setVar($outputPagination);

        $report->render();
    }
} else {
    // else there are no results
    $output = [
        'download_report_block' => '',
        'previous_button_block' => '',
        'page_number_block'     => '',
        'next_button_block'     => '',
        'first_page_block'      => '',
        'last_page_block'       => '',
        'REPORT_TITLE'          => $report->getReportTitle(),
        'COUNTABLE_COUNT'       => '0',
        'COUNTABLE'             => 'results',
        'RESULT'                => '',
    ];

    $this->setVar($output);

    // if there is a filter block, output it despite having no results
    $report->displayFilterBlock();
}
