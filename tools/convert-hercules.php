<?php
/**
 * Converts Hercules images (in /templates/{{site.location}}/m/hercules.html) to
 * image files.
 */

foreach (glob(dirname(__DIR__).'/templates/*/m/hercules.html') as $file) {
    if (preg_match('|/templates/(.*?)/m/hercules.html$|', $file, $matches)) {
        $site_location = $matches[1];
        $document = new DOMDocument();
        $document->loadHTMLFile($file);
        foreach ($document->getElementsByTagName('img') as $image) {
            /** @var DomElement $image */
            $source = $image->getAttribute('src');
            if (preg_match('|^data:image/png|', $source)) {

//                // Does it have a width and/or height defined? If so resize it
//                $width  = null;
//                $height = null;
//                if ($image->hasAttribute('width')) {
//                    $width = $image->getAttribute('width');
//                }
//                if ($image->hasAttribute('height')) {
//                    $height = $image->getAttribute('height');
//                }

                $im = imagecreatefromstring(file_get_contents($source));
                if ($im !== false) {

                    $dir     = dirname(__DIR__)."/www/images/$site_location/";
                    $outfile = "$dir/hercules.png";
                    @mkdir($dir, 0777, true);
                    @unlink($outfile);

                    // Resize image if necessary
//                    if (isset($width) || isset($height)) {
//                        $width  ?: imagesx($im);
//                        $height ?: imagesy($im);
//                        $copy = imagecreatetruecolor($width, $height);
//                        imagesavealpha($copy, true);
//                        imagecopyresampled($copy, $im, 0, 0, 0, 0, $width, $height, imagesx($im), imagesy($im));
//                        $im = $copy;
////                        imagepng($copy, $outfile);
////                        if (!isset($width)) {
////                            $width = imagesx($im);
////                        }
////                        if (!isset($height)) {
////                            $height = imagesx($im);
////                        }
//                    }
//                    imagesavealpha($im, true);
                    imagepng($im, $outfile);

//                    // create the directory and put the image into it
//                    file_put_contents("$dir/hercules.png", file_get_contents($source));
                }
            }
        }
    }
}
