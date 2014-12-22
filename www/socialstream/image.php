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

require_once(dirname(__FILE__) . '/../../include/Bootstrap.php');

$postId = $_GET['id'];
$size = $_GET['size'];

try {
    $post = PostMapper::getInstance()->findById($postId);
} catch (\Schoolbox\Mapper\Exception $e) {
    \SitePage::redirectToBrokenImageWithTransform('storage', $size);
}

$cache = new Cache\Original();
$cache->init($post->getThumbnail());

/* Transform it to some arbitrary dimension for now
 * and borrow the Storage transformation definitions
 */
$cache = new Cache\Filesystem($cache->getCachedFile(), $size);

try {
    $processor = new \Alaress_Image_Processor_Standard($cache, new \Alaress_Image_Transformation_Storage());
    $processor->load()->stream();
} catch (\Exception_Schoolbox $e) {
    \SitePage::redirectToBrokenImageWithTransform('storage', $size);
}
