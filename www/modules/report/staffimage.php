<?php

require_once(dirname(__FILE__) . '/../../../include/Bootstrap.php');

$page = new SitePage();

$image = new Report_Staff_Image($_GET['StaffID']);

