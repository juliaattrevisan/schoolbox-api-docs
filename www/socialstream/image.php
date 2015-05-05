<?php
/**
 * ${NAME}
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
namespace SocialStream;

use Alaress\Image;
use Alaress\Image\Implementation\Imagine;
use Schoolbox\Resource\Storage;

require_once __DIR__ . '/../../include/Bootstrap.php';

$postId = $_GET['id'];
$size = $_GET['size'];

try {
    $post = PostMapper::getInstance()->findById($postId);
} catch (\Schoolbox\Mapper\Exception $e) {
    \SitePage::redirectToBrokenImageWithTransform('storage', $size);
}

$cache = new Cache\Original();
$cache->initPost($post);

$urlDetect = new UrlDetection($post->getUrl());

if ($urlDetect->isStorageUrl()) {
    $hash = $urlDetect->getHash();
    $storage = Storage\Mapper::getInstance()->findByHash($hash);

} else {
    $storage = $cache->getCachedFile();
}

$cache = new Image\Cache\Storage($storage, $size);

try {
    $processor = new Image\Processor\Standard(
        $cache,
        new Image\Transformation\Storage(),
        new Imagine\Filetype\Factory(),
        new Imagine\Processor\Strategy\Factory()
    );
    $processor->load()->stream();
} catch (\Exception_Schoolbox $e) {
    \SitePage::redirectToBrokenImageWithTransform('storage', $size);
}
