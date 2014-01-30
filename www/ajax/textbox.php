<?php
require_once dirname(__FILE__) . '/../../include/Bootstrap.php';

$textBoxHandler = new TextBox();
$page = new SitePage;


$searchList = $textBoxHandler->search($_GET['query']);

$return = array();
if (is_array($searchList)) {
    foreach ($searchList as $textbox) {
        $return[] = array(
            'id'   => $textbox['textBoxID'],
            'name' => $textbox['cname'] . ': ' . $textbox['heading'],
        );
    }

}

header('Content-type: application/json');
echo json_encode($return);
