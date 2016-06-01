<?php

define('IMG_max_size', 30000000);

class Alaress_Image
{
    public function imageResize($src, $maxHeight, $maxWidth, $crop = true, $resample = 1, $quality = 75)
    {

        if (is_readable($src)) {
            // image src size
            list($origWidth, $origHeight, $imageType) = getimagesize($src);

            //test image size (cannot handle large 30mb+ uncompressed images)
            if (($origHeight * $origWidth) > IMG_max_size) {
                error(1);
            }

            //resize image propotionally within the max height and width and crop
            // image dest size $destWidth = width, $destHeight = height
            $srcRatio = $origWidth / $origHeight; // width/height ratio
            $destRatio = $maxWidth / $maxHeight;

            // dont enlarge images beyond existing size (doesnt look good)
            if ($origHeight < $maxHeight && $origWidth < $maxWidth) {
                $destHeight = $origHeight;
                $destWidth = $origWidth;
            }
            else if (($destRatio < $srcRatio && $crop) || ($destRatio > $srcRatio && !$crop)) {
                $destHeight = $maxHeight;
                $destWidth = $maxHeight * $srcRatio;
            }
            else
            {
                $destWidth = $maxWidth;
                $destHeight = $maxWidth / $srcRatio;
            }

            // src image
            switch ($imageType)
            {
                case 1: //GIF
                    $srcImage = imagecreatefromgif($src);
                    break;

                case 2: //JPEG
                    $srcImage = imagecreatefromjpeg($src);
                    break;

                case 3: //PNG
                    $srcImage = imagecreatefrompng($src);
                    break;

                default:
                    return false;
                    break;
            }

            // resampling
            if ($crop) {
                //calculate cropping, sample image from inside
                $heightRatio = $origHeight / $destHeight;
                $widthRatio = $origWidth / $destWidth;

                $sampleX = ($origWidth / 2) - (($maxWidth * $widthRatio) / 2);
                $sampleY = ($origHeight / 2) - (($maxHeight * $heightRatio) / 2);

                // true color image, with anti-aliasing
                $destImage = imagecreatetruecolor($maxWidth, $maxHeight);

                if ($resample) {
                    imagecopyresampled($destImage, $srcImage, 0, 0, $sampleX, $sampleY, $destWidth + 1, $destHeight + 1, $origWidth, $origHeight);
                }
                else {
                    imagecopyresized($destImage, $srcImage, 0, 0, $sampleX, $sampleY, $destWidth + 1, $destHeight + 1, $origWidth, $origHeight);
                }
            }
            else {

                // true color image, with anti-aliasing
                $destImage = imagecreatetruecolor($destWidth, $destHeight);

                if ($resample) {
                    imagecopyresampled($destImage, $srcImage, 0, 0, 0, 0, $destWidth + 1, $destHeight + 1, $origWidth, $origHeight);
                }
                else {
                    imagecopyresized($destImage, $srcImage, 0, 0, 0, 0, $destWidth + 1, $destHeight + 1, $origWidth, $origHeight);
                }
            }


            return $destImage;
        }
    }
}
