<?php
/**
 * ${NAME}
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace SocialStream;

use Alaress\Image;
use Alaress\Image\Implementation\Imagine;
use Schoolbox\Resource\Storage;

require_once __DIR__ . '/../../conf/core/require.php';
$kernel = new \Schoolbox\Application\BasicRequest\Kernel();

header('Cache-Control: max-age=10800, private');
header_remove('Pragma');

$postId = $_GET['id'];
$size   = $_GET['size'];

try {
    $post = PostMapper::getInstance()->findById($postId);
} catch (\Schoolbox\Mapper\Exception $e) {
    \SitePage::redirectToBrokenImageWithTransform('storage', $size);
    exit;
}

// No thumbnail: show nothing.
// This is a bad request by the client.
if (!strlen($post->getThumbnail())) {
    \SitePage::redirectToBrokenImageWithTransform('storage', $size, 400);
    exit;
}


$cache = new Cache\Original();
$cache->initPost($post);

$urlDetect = new UrlDetection($post->getUrl());

if ($urlDetect->isStorageUrl()) {
    $hash    = $urlDetect->getHash();
    $storage = Storage\Mapper::getInstance()->findByHash($hash);

} else {
    $storage = $cache->getFile();
}

try {
    $cache     = new Image\Cache\Storage($storage, $size);
    $processor = new Image\Processor\Standard(
        $cache,
        new Image\Transformation\Storage(),
        new Imagine\Filetype\Factory(),
        new Imagine\Processor\Strategy\Factory()
    );
    $processor->load()->stream();
} catch (\Throwable $e) {
    \SitePage::redirectToBrokenImageWithTransform('storage', $size);
}
