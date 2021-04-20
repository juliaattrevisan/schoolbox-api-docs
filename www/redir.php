<?php

use Schoolbox\Application\WebRequest\Kernel;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Resource\Link;
use Schoolbox\Resource\LinkMapper;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;

require_once __DIR__ . '/../conf/core/require.php';
$kernel = new Kernel();
/** @var SitePage $page */
$page   = $kernel->get('sitePage');
$page->initSession();
$url    = '';

$link       = false;
$linkMapper = new LinkMapper();

if ($_GET['url']) {
    try {
        $url = base64_decode($_GET['url']);

        $link = $linkMapper->findOneByColumn('url', $url);
    } catch (\Exception $e) {
        // let it through
    }
} elseif (is_numeric($_GET['id'])) {
    try {
        $access = Access::getInstance();
        /** @var Link $link */
        $link           = $linkMapper->findById($_GET['id']);
        $requiredAccess = $link->isHidden() ? UserAccess::ACCESS_WRITE : UserAccess::ACCESS_READ;

        if ($access->getFolderAccess($_SESSION['user'], $link->getFolder()->getId()) < $requiredAccess) {
            error(ErrorMapper::ERROR_RESOURCE_NOT_FOUND);
        }
        $url = $link->getUrl();
    } catch (\Exception $e) {
        error(ErrorMapper::ERROR_RESOURCE_NOT_FOUND);
    }
}

/* Log it
 */
if ($link instanceof Link) {
    $linkMapper->logAction($_SESSION['user'], 'access', $link);
}

if (!empty($url)) {
    $translator = new Schoolbox\Cyberhound\Url\Translator(
        $_SESSION['ip'],
        Config::get('cyberhound_api_url'),
        Config::get('cyberhound_api_shared_key')
    );
    header('Location: ' . $translator->translate($url));
    exit;
} else {
    error(ErrorMapper::ERROR_INFORMATION_MISSING);
}
