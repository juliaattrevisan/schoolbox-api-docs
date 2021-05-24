<?php

require_once __DIR__ . '/../../conf/core/require.php';
$kernel = new \Schoolbox\Application\WebRequest\Kernel();

/** @var SitePage $page */
$page = $kernel->get('sitePage');
$page->initSession();

$output = array();
$allowFilter = array('image', 'video');

if (is_numeric($_POST['categoryID'])) {
    $fileHandler = new MyFile();
    $allowedFiles = new AllowedFile();

    $config['filter'] = in_array($_POST['filter'], $allowFilter) ?  $_POST['filter'] : false;
    $config['published'] = true;

    $fileList = $fileHandler->getAllInFolder($_POST['categoryID'], $config);


    if (!is_array($fileList)) {
        $output = array();
    } else {
        $userHandler = new User();

        foreach ($fileList as $file) {
            $fileTime = new Alaress_Date_Format($file['modified'], 'Y-m-d H:i:s');
            $file['user'] = $userHandler->formatName($file);
            $file['relative'] = $fileTime->getRelativeTime();
            $file['mimeClass'] = !$file['iconFile'] ? $allowedFiles->getClass($file["iconFile"]) : "textfile";
            $file['description'] = TextFormatter::cutString($file['description'], 32);
            $output[] = $file;
        }
    }

    $_SESSION['resourcesID'] = $_POST['categoryID'];
}

header("Content-type: application/json");
print json_encode($output);
