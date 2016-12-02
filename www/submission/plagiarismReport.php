<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
use \Schoolbox\Learning\Assessment\DueWork\Submission\File\Plagscan\Model as Plagscan;
use \Schoolbox\Learning\Assessment\DueWork\Submission\File\Plagscan\Mapper as PlagscanMapper;

if(!isset($this)) {
    require_once dirname(__FILE__) . '/../../library/Bootstrap.php';
    error(2); // not enough info
}

if(!isset($this->arg[0]) || !is_numeric($this->arg[0])) {
    error(2);
}

// security check
$groupHandler = new Group();
if(!($groupHandler->isStaff() || $groupHandler->isSuperuser())) {
    error(19); // no permission to view this page
}

try {
    $plagscanMapper = PlagscanMapper::getInstance();
}
catch (\DomainException $e) {

    /* Plagscan not configured - nothing to do
     */
    exit();
}

/* @var Plagscan $document */
try {
    $document = $plagscanMapper->findOneByColumn('plagscan_id', $this->arg[0]);
}
catch (\Schoolbox\Mapper\Exception $e) {

    /* Document not found
     */
    exit();
}

// Use below instead to retrieve Plagscan results in real time
//$gateway = new Plagscan_Gateway(
//    Config::get('plagscan_username'),
//    Config::get('plagscan_api_key')
//);
//echo $gateway->retrieve($document->getPlagscanId(), Plagscan_Gateway::MODE_RETRIEVE_HTML_INTERACTIVE);

echo $document->getHtml();
exit();
