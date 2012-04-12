<?php
require_once('../include/Bootstrap.php');

$tree = new Tree_Category();
$tree->grow(0);
//print_r($tree->getTree());

//print_r($tree->getJsAncestorNodesList(74));
//print_r($tree->getAncestorNodesList(74));