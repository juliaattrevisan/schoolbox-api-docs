<?php
/************************************************************
 *                                                           *
 *         Copyright (c) 2013 Alaress Pty Ltd                *
 *                                                           *
 ************************************************************/
declare(strict_types=1);

use Aura\Router\Router;
use Schoolbox\Resource\Folder;

// Direct .php file access
/** @var SitePage $this */
if (!isset($this)) {
    // Assume no args
    header('Location: /subject/classList/');
    die();
}
$classCodeCsv = urldecode(implode('/', $this->arg));
$folders      = Folder\Mapper::getInstance()->findAllByCode($classCodeCsv);

// Ensure we didn't get 0 or multiple folders for these codes/s
if ($folders->count() !== 1) {
    $this->pageNotFound();
}

/** @var Folder\Model $firstFolder */
$firstFolder = $folders->first();

/** @var Router $router */
$router = $this->getDi()->get('router');

header("Location: {$router->generate('learning.getClassListPage', ['folderId' => $firstFolder->getId()])}");
die();
