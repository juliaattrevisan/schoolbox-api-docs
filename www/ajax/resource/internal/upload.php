<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

if (empty($this)) {
    require_once __DIR__ . '/../../../../library/Bootstrap.php';
    SitePage::localRedir('/resources');
}

/** @var SitePage $this */

$this->setAjax();

$this->setFile(TEMPLATE_content, '/resources/internal/upload.html');

switch(strtolower($this->arg[0])) {
    case 'single':
        $maxFiles = '1';
        break;
    case 'multi':
        $maxFiles = 'null';
        break;
    default:
        error(2);
}

switch(count($this->arg)) {
    case 1:
        $allowedFiles = 'null';
        break;
    case 2:
        $allowedFiles = '"' . $this->arg[1] . '/*"';
        break;
    case 3:
        $allowedFiles = '"' . $this->arg[1] . '/' . $this->arg[2] . '"';
        break;
    default:
        error(2);
}

$this->setVar(array(
    'MAX_FILES'      => $maxFiles,
    'ACCEPTED_FILES' => $allowedFiles
));

$this->template->pparse('finaloutput', TEMPLATE_content);
