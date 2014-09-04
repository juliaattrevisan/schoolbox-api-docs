<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

if (empty($this)) {
    require_once __DIR__ . '/../../../include/Bootstrap.php';
    // nope. go away
    SitePage::localRedir('/resources/external/google');
}

/** @var SitePage $this */

$this->setAjax(true);

$this->setFile(TEMPLATE_content, 'resources/external/google.html');

$scopes   = array('https://www.googleapis.com/auth/drive.readonly');
$views    = array('google.picker.ViewId.DOCS');
$features = array('google.picker.Feature.NAV_HIDDEN', 'google.picker.Feature.MINE_ONLY');

if (in_array('multi', $this->arg)) {
    $features[] = 'google.picker.Feature.MULTISELECT_ENABLED';
}

$this->setVar(array(
    'DEV_KEY'   => 'AIzaSyA3cpR5FFE_TN3j-gT7txu4EKpDEiXB3J0', // TODO replace dev credentials
    'CLIENT_ID' => '430767258977-8gphor0062gte810jf8b40okpkprbmfs.apps.googleusercontent.com', // TODO replace dev credentials
    'SCOPES'    => implode("','", $scopes),
    'VIEWS'     => implode(',', $views),
    'FEATURES'  => implode(',', $features),
));

$this->template->pparse('finaloutput', TEMPLATE_content);