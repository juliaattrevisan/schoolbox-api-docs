<?php
/**
 * @author      Damien Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 *
 * System-wide Selectize tag data-generator.
 * @link       http://selectize.github.io/selectize.js/
 */

use Schoolbox\Pastoral\Tags\Mapper as PastoralMapper;
use Schoolbox\SportsFixtures\Position\Mapper as PositionMapper;

if(!isset($this)) {
    require_once(dirname(__FILE__) . '/../../library/Bootstrap.php');
    error(2);
}
/** @var SitePage $this */
$this->setAjax();

$di = $this->getDi();

/* Note: Insert new tag types here. */
$registry = [
    'pastoral' => $di->lazyNew(PastoralMapper::class),
];

// Validate params
$errors = [];
if(empty($_GET['query'])){
    $errors[] = 'Query fragment not supplied';
}
if(empty($this->arg[0])){
    $errors[] = 'Tag type not supplied';
}
if(empty($registry[$this->arg[0]])){
    $errors[] = 'Tag type not in registry';
}
if($errors){
    error(2,implode("\n", $errors));
}

$mapper = $registry[$this->arg[0]]();
$tags   = $mapper->findDistinct('name');
$query  = strtolower($_GET['query']);

$tagsArray = [];
foreach ($tags as $tag) {
    if (strpos(strtolower($tag), $query) !== false) {
        $tagsArray[] = ['id' => $tag, 'name' => $tag];
    }
}

header('Content-type: application/json');
echo json_encode($tagsArray);




