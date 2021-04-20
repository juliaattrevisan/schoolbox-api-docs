<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

/* Steve Notes: I think this is a dead file?
 */

if (empty($this)) {
    require_once __DIR__ . '/../../../../conf/core/require.php';
    $kernel = new \Schoolbox\Application\WebRequest\Kernel();
    SitePage::localRedir('/resources');
}

/** @var SitePage $this */

$this->setAjax();

$this->setFile(TEMPLATE_content, '/resources/filesystem/upload.html');

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

$this->injectGlobalVars();
$this->template->pparse('finaloutput', TEMPLATE_content);
