<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
interface Report_IReport {
    function setIndexID($groupID);
    function getIndexID();
    function getReportTitle();
    function setFields($cols);
    function addFields($cols);
    function getFields();
    function getDefaultOrderBy();
    function getSortableFields();
    function populateDataset($offset, $limit, $orderBy = "", $orderDir = "ASC");
    function displayFilterBlock();
    function export();

// TODO: refactor Report_Abstract to implement IReport so these can be included in interface
//    function setup();
//    function render();
}
