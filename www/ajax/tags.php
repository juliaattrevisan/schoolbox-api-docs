<?php
/**
 * @author      Damien Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 *
 * System-wide Selectize tag data-generator.
 * @link       http://selectize.github.io/selectize.js/
 */

use Schoolbox\Pastoral\Tag\Mapper as PastoralTagMapper;
use Schoolbox\Fixtures\Coach;
use Schoolbox\Fixtures\Position\Mapper as PositionMapper;
use Schoolbox\UserMapper;

if(!isset($this)) {
    require_once __DIR__ . '/../../conf/core/require.php';
    $kernel = new \Schoolbox\Application\WebRequest\Kernel();
    error(2);
}
/** @var SitePage $this */
$this->setAjax();

$di = $this->getDi();

/* Note: Insert new tag types here. */
$registry = [
    'pastoral'       => $di->lazyNew(PastoralTagMapper::class),
    'sport_position' => $di->lazyNew(PositionMapper::class),
    'sport_coach'    => $di->lazyNew(Coach\TagCloud::class, [
        'mapper'     => $di->lazy([Coach\Mapper::class, 'getInstance']),
        'userMapper' => $di->lazy([UserMapper::class, 'getInstance']),
    ]),
];

// Validate params
$errors = [];

if(empty($this->arg[0])){
    $errors[] = 'Tag type not supplied';
}
if(empty($registry[$this->arg[0]])){
    $errors[] = 'Tag type not in registry';
}

$tagsArray = [];

if ($errors) {
    http_response_code(400);
} else {
    /** @var \Schoolbox\Mapper\UsableAsTagSource $mapper */
    $mapper = $registry[$this->arg[0]]();
    if ($mapper instanceof \Schoolbox\Mapper\UsableAsTagSource) {

        /* Optionally provide a column name by which to sort
         */
        $sort = $_GET['sort'] ?? null;
        $query = (string)$_GET['query'];

        $tagsArray = $mapper->findForTag($_SESSION['user'], $query, $sort);
    } else {
        http_response_code(500);
    }
}

header('Content-type: application/json');
echo json_encode($tagsArray);
