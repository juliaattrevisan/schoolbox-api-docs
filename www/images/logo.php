<?php
/**
 * Outputs an icon from a spritesheet at a given size, or with a given transform
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

use Alaress\Image\Cache;
use Alaress\Image\Implementation\Imagine;
use Alaress\Image\Processor\Freeform;
use Alaress\Image\Transformation;

require_once __DIR__.'/../../include/Bootstrap.php';

$logoSizes = array('skin_logo_square' => array(
                                            'normal'    => array(80,80),
                                            'hidpi'     => array(160,160),
                                            'favicon'   => array(64,64),
                                            'tile70'    => array(70,70),
                                            'tile150'   => array(150,150),
                                            'apple57'   => array(57,57),
                                            'apple60'   => array(60,60),
                                            'apple72'   => array(72,72),
                                            'apple76'   => array(76,76),
                                            'apple114'  => array(114,114),
                                            'apple120'  => array(120,120),
                                            'apple144'  => array(144,144),
                                            'apple152'  => array(152,152),
                                            'apple180'  => array(180,180),

                                        ),
              'skin_logo_large' => array(
                                            'normal' => array(196, 400),
                                            'hidpi' => array(392, 800),
                                            'tile310' => array(310,310),
                                        ),
              'skin_logo_login' => array(
                                            'normal' => array(296,400),
                                            'hidpi' => array(592, 800),
                                            'tile310' => array(310,150),
              ),
);

$logo = $_GET['logo'];
$size = $_GET['size'];

if(!( array_key_exists($logo, $logoSizes) && array_key_exists($size, $logoSizes[$logo]))) {
    SitePage::redirectToBrokenImageOfSize(80, 80, 404);
}


/* Choose a caching method to use
 */
$key   = $logoSizes[$logo][$size][0] . 'x' . $logoSizes[$logo][$size][1];
$store  = Schoolbox\Resource\Storage\Mapper::getInstance()->findByHash(Config::get($logo));

/* Process and stream image
 */
try {
    $imageProcessor = new Freeform(
        new Cache\Storage($store, $key),
        new Transformation\MyImage( $logoSizes[$logo][$size][0], $logoSizes[$logo][$size][1]),
        new Imagine\Filetype\Factory(),
        new Imagine\Processor\Strategy\Factory()
    );
    $imageProcessor->load()->stream();
} catch (Exception_Schoolbox_NotFound $e) {
    SitePage::redirectToBrokenImageOfSize($_GET['width'], $_GET['height'], 404);
}
catch (Exception $e) {
    SitePage::redirectToBrokenImageOfSize($_GET['width'], $_GET['height'], 500);
}