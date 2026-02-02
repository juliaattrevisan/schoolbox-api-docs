<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
interface Report_IReport
{
    public function setIndexID($groupID);
    public function getIndexID();
    public function getReportTitle();
    public function setFields($cols);
    public function addFields($cols);
    public function getFields();
    public function getDefaultOrderBy();
    public function getSortableFields();
    public function populateDataset($offset, $limit, $orderBy = "", $orderDir = "ASC");
    public function displayFilterBlock();
    public function export();

// TODO: refactor Report_Abstract to implement IReport so these can be included in interface
//    function setup();
//    function render();
}
