<?php
/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

/** @var SitePage $this */

// Workaround for missing home.png and folder.png
$image = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
switch ($image) {
    case '/images/mime/folder.png':
    case '/images/mime/home.png':
        $this->localRedir('/images/mime/application-document.svg');
        break;
    default:
        $this->pageNotFound();
}
