<?php
require_once dirname(__FILE__) . '/../../include/Bootstrap.php';

$textBoxHandler = new TextBox();
$page = new SitePage;


$searchList = $textBoxHandler->search($_GET['query']);

if (is_array($searchList)) {
    foreach ($searchList as $textbox) {
        $suggestions[] = $textbox['cname'] . ': ' . $textbox['heading'];
        $data[] = $textbox['textBoxID'];
    }

}

$result = array(
    'query' => $_GET['query'],
    'suggestions' => $suggestions,
    'data' => $data,
);

header('Content-type: application/json');
echo json_encode($result);