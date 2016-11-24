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
use Schoolbox\Fixtures\Coach;
use Schoolbox\Fixtures\Position\Mapper as PositionMapper;

if(!isset($this)) {
    require_once(dirname(__FILE__) . '/../../library/Bootstrap.php');
    error(2);
}
/** @var SitePage $this */
$this->setAjax();

$di = $this->getDi();

/* Note: Insert new tag types here. */
$registry = [
    'pastoral'       => $di->lazyNew(PastoralMapper::class),
    'sport_position' => $di->lazyNew(PositionMapper::class),
    'sport_coach'    => $di->lazyNew(
        Coach\TagCloud::class,
        [
            'mapper'     => $di->lazy([Coach\Mapper::class, 'getInstance']),
            'userMapper' => $di->lazy([\Schoolbox\UserMapper::class, 'getInstance']),
        ]
    ),
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

$tagsArray = [];

if ($errors) {
    header(':', true, 400);
} else {
    /** @var \Schoolbox\Mapper\UsableAsTagSource $mapper */
    $mapper = $registry[$this->arg[0]]();
    if ($mapper instanceof \Schoolbox\Mapper\UsableAsTagSource) {
        $tagsArray = $mapper->findForTag($_SESSION['user'], $_GET['query']);
    } else {
        header(':', true, 500);
    }
}

header('Content-type: application/json');
echo json_encode($tagsArray);