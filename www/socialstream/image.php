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

$post = PostMapper::getInstance()->findById($postId);

$cache = new Cache\Original();
$cache->init($post->getThumbnail());

/* Transform it to some arbitrary dimension for now
 * and borrow the Storage transformation definitions
 */
$cache = new Cache\Filesystem($cache->getCachedFile(), $size);

$processor = new \Alaress_Image_Processor_Standard($cache, new \Alaress_Image_Transformation_Storage());
$processor->load()->stream();
