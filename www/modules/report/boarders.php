<?php

// Was file directly accessed (ie not a SitePage include)?
if(!isset($this)){
    require_once __DIR__ . '/../../../conf/core/require.php';
    $kernel = new \Schoolbox\Application\WebRequest\Kernel();
    SitePage::localRedir(\preg_replace('/.php$/', '', $_SERVER['SCRIPT_NAME']));
}
/** @var SitePage $this */
$parentReport = new Report_Boarder($this);
$parentReport->render();
