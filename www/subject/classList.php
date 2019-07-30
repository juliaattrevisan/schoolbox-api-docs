<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

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

try {
    /** @var Router $router */
    $router         = $this->getDi()->get('router');
    $classCodeCsv   = urldecode(implode('/', $this->arg));
    $folder         = Folder\Mapper::getInstance()->findByCode($classCodeCsv);

    // redirect to $folder->getId()
    header("Location: {$router->generate('learning.getClassListPage', ['folderId' => $folder->getId()])}");
    die();
} catch (\Schoolbox\Mapper\Exception $exception) {
    $this->pageNotFound(); // no models matched
}
