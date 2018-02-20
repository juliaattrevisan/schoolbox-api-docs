<?php
/**
 * ${NAME}
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */
use Schoolbox\Application\Exception\NotFound as SchoolboxNotFoundException;
use Alaress\Image\Cache;
use Alaress\Image\Processor;

require_once __DIR__ . '/../../conf/core/require.php';
spl_autoload_call('Schoolbox\\Application\\BasicRequest');
$kernel = new \Schoolbox\Application\BasicRequest\Kernel();

/* Preserve old checks
 */
session_start();

if (empty($_SESSION['id'])) {
    redirectToBroken(403);
}

try {
    $image = Schoolbox\Resource\My\File\Mapper::getInstance()->findById($_GET['id']);
} catch (Schoolbox\Mapper\Exception $e) {
    redirectToBroken(404);
    exit;
}

if (isset($_GET['key'])) {
    $key = $_GET['key'];
    $transform = new \Alaress\Image\Transformation\Storage();

} elseif (isset($_GET['width']) && isset($_GET['height'])) {
    $key = $_GET['width'] . 'x' . $_GET['height'];
    $transform = new \Alaress\Image\Transformation\MyImage($_GET['width'], $_GET['height']);

} else {
    redirectToBroken(400);
    exit;
}

try {
    /** @var Schoolbox\Resource\My\File\Model $image */
    $cache = new Cache\Storage($image->getStorage(), $key);
    createProcessor($cache, $transform)->load()->stream();

} catch (SchoolboxNotFoundException $e) {
    redirectToBroken(404);

} catch (Exception $e) {
    redirectToBroken(500);
}

exit;

/**
 * @param int $code
 */
function redirectToBroken($code = 500)
{
    if (isset($_GET['key'])) {
        SitePage::redirectToBrokenImageWithTransform('storage', $_GET['key'], $code);
    } elseif (isset($_GET['width'])) {
        SitePage::redirectToBrokenImageOfSize($_GET['width'], $_GET['height '] ?: $_GET['width'], $code);
    }
}

/**
 * @param $cache
 * @param $transform
 * @return Processor\Standard
 */
function createProcessor($cache, $transform)
{
    $ftFactory    = new \Alaress\Image\Implementation\Imagine\Filetype\Factory();
    $stratFactory = new \Alaress\Image\Implementation\Imagine\Processor\Strategy\Factory();

    if (isset($_GET['key'])) {
        return new Processor\Standard($cache, $transform, $ftFactory, $stratFactory);
    } else {
        return new Processor\Freeform($cache, $transform, $ftFactory, $stratFactory);
    }
}
