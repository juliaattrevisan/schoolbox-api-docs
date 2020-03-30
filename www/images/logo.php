<?php
/**
 * Outputs an icon from a spritesheet at a given size, or with a given transform
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Alaress\Image\Cache;
use Alaress\Image\Implementation\Imagine;
use Alaress\Image\Processor\Freeform;
use Alaress\Image\Transformation;

require_once __DIR__ . '/../../conf/core/require.php';
$kernel = new \Schoolbox\Application\BasicRequest\Kernel();

header('Cache-Control: max-age=10800, private');

$placeholders = [
    'skin_logo_login'  => '/core/logo.png',
    'skin_logo_square' => '/core/m-hercules.png',
    'skin_logo_large'  => '/core/hercules.png',
];

$logoSizes = [
    'skin_logo_square' => [
        'normal'   => [80, 80],
        'hidpi'    => [160, 160],
        'favicon'  => [64, 64],
        'tile70'   => [70, 70],
        'tile150'  => [150, 150],
        'apple57'  => [57, 57],
        'apple60'  => [60, 60],
        'apple72'  => [72, 72],
        'apple76'  => [76, 76],
        'apple114' => [114, 114],
        'apple120' => [120, 120],
        'apple144' => [144, 144],
        'apple152' => [152, 152],
        'apple180' => [180, 180],
    ],
    'skin_logo_large'  => [
        'normal'  => [196, 400],
        'hidpi'   => [392, 800],
        'tile310' => [310, 310],
    ],
    'skin_logo_login'  => [
        'normal'  => [296, 400],
        'hidpi'   => [592, 800],
        'tile310' => [310, 150],
    ],
];

$logo = $_GET['logo'];
$size = $_GET['size'];

if (!(array_key_exists($logo, $logoSizes) && array_key_exists($size, $logoSizes[$logo]))) {
    SitePage::redirectToBrokenImageOfSize(80, 80, 404);
}


try {
    /* Choose a caching method to use
     */
    $key   = $logoSizes[$logo][$size][0] . 'x' . $logoSizes[$logo][$size][1];
    $store = Schoolbox\Resource\Storage\Mapper::getInstance()->findByHash(Config::get($logo));

    /* Process and stream image
     */
    $imageProcessor = new Freeform(
        new Cache\Storage($store, $key),
        new Transformation\MyImage($logoSizes[$logo][$size][0], $logoSizes[$logo][$size][1]),
        new Imagine\Filetype\Factory(),
        new Imagine\Processor\Strategy\Factory()
    );
    $imageProcessor->load()->stream();
} catch (Exception $e) {
    $placeHolderFile = __DIR__ . $placeholders[$logo];
    if (is_readable($placeHolderFile)) {
//        header('Cache-Control: max-age=10800, private');
//        header('Expires: ' . date(DATE_RFC822, strtotime(' 2 day')));
        header('Content-type: image/png');
//        header('Pragma: private');

        readfile($placeHolderFile);
        exit;

    } else {
        SitePage::redirectToBrokenImageOfSize($_GET['width'], $_GET['height'], 404);
    }
}
