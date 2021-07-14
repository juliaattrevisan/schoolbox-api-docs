<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

use Schoolbox\Error\Mapper as ErrorMapper;

// Check if module is enabled
if (!Config::get('parent_directory_enabled')) {
    $this->pageNotFound();
}

/** @var \Schoolbox\User $currentUser */
$currentUser = $_SESSION['user'];
if (!$currentUser->getRole()->getViewParentDirectory()) {
    error(ErrorMapper::ERROR_PERM_PAGE_VIEW);
}

$searchName = '';
$searchBoardingStatus = '';
$searchForm = '';
$searchYear = '';

/* Search by year
 */
if (isset($_GET['searchYear'])) {
    $searchYear = $_GET['searchYear'];
} else {
    $searchYear = $this->arg[0];
}

/* Search by name
 */
if (isset($_GET['searchName'])) {
    $searchName = $_GET['searchName'];
}

/* Search by boarding status
 */
if (isset($_GET['searchBoardingStatus'])) {
    $searchBoardingStatus = $_GET['searchBoardingStatus'];
}

/* Search by form
 */
if (isset($_GET['searchForm'])) {
    $searchForm = $_GET['searchForm'];
}

$parentReport = new Report_Parent($this, $searchYear, $searchName, $searchBoardingStatus, $searchForm);
$parentReport->render();
