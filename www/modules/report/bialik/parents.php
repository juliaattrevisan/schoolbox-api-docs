<?php

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

$parentReport = new Report_Bialik_Parent($this, $searchYear, $searchName, $searchBoardingStatus, $searchForm);
$parentReport->render();
