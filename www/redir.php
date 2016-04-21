<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/Bootstrap.php';
$page = new SitePage;
$url  = '';

$link       = false;
$linkMapper = new \Schoolbox\Resource\LinkMapper();

if ($_GET['url']) {
    try {
        $url = base64_decode($_GET['url']);

        $link = $linkMapper->findOneByColumn('url', $url);
    } catch (\Exception $e) {
        // let it through
    }
} elseif (is_numeric($_GET['id'])) {
    try {
        $link = $linkMapper->findById($_GET['id']);

        $url = $link->getUrl();
    } catch (\Exception $e) {
        error(101);
    }
}

/* Log it
 */
if ($link instanceof \Schoolbox\Resource\Link) {
    $linkMapper->logAction($_SESSION['user'], 'access', $link);
}

if (!empty($url)) {
    $urlObject = new \Zend\Uri\Uri($url);
    if (!$urlObject->isValidRelative()) {
        // If using Cyberhound: redirect to its proxy rather than the URL directly.
        // Only redirect for non-local links though.
        $cyberHoundApiUrl       = Config::get('cyberhound_api_url');
        $cyberHoundApiSharedKey = Config::get('cyberhound_api_shared_key');
        if ($cyberHoundApiUrl && $cyberHoundApiSharedKey) {
            $cyberHoundUrl = new \Zend\Uri\Uri($cyberHoundApiUrl);
            $cyberHoundUrl
                ->setPath('/api/overrideurl')
                ->setQuery([
                    'key'  => hash('sha256', $_SERVER['REQUEST_TIME'].base64_encode($url).$cyberHoundApiSharedKey),
                    'time' => $_SERVER['REQUEST_TIME'],
                    'url'  => base64_encode($url),
                ]);
            $url = $cyberHoundUrl->toString();
        }
    }
    header('Location: ' . $url);
    exit;
} else {
    error(2);
}
