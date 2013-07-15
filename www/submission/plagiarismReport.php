<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

if(!isset($this)) {
    require_once dirname(__FILE__) . '/../../include/Bootstrap.php';
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

$pdm = Plagscan_DocumentMapper::getInstance();
$document = $pdm->findByColumn('plagscan_id', $this->arg[0])->first();


// remove all '<a...>...highlight matches in source...</a>' instances from source
$regex[] = "#<a[^>]*>((?:(?!</a>)[\s\S])*)highlight matches in source((?:(?!</a>)[\s\S])*)</a>#is";

// remove any href='...plagscan.com/highlight?...' from source
$regex[] = '$http://www.plagscan.com/highlight\?[^#\'"]*#jump$is';

$html = $document->html;

foreach($regex as $r) {
    $html = preg_replace($r, '', $html);
}

die($html);
