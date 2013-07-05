<?php
/**
 * Alaress_Image_Filetype_Jpg
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Alaress_Image_Filetype_Jpg extends Alaress_Image_Filetype_Abstract {
    private $quality = 90;

    /**
     * Create an image
     *
     * @param $filename
     * @return resource
     */
    public function loadImageResource($filename) {
        return @imagecreatefromjpeg($filename);
    }

    /**
     * Save image to file system
     *
     * @param $image
     * @param $filename
     */
    public function save($image, $filename) {
        imagejpeg($image, $filename, $this->getQuality());
    }


    private function outputHeaders() {
        header('Cache-Control: max-age=10800, private');
        header('Expires: ' . date(DATE_RFC822, strtotime(' 2 day')));
        header('Pragma: private');
        header('Content-type: image/jpeg');
    }

    /**
     * Stream image to browser directly
     *
     * @param $image
     */
    public function stream($image) {
        $this->outputHeaders();
        imagejpeg($image);
        exit;
    }

    public function streamFile($filename) {
        $this->outputHeaders();
        header('Content-length: ' . filesize($filename));
        header('Last-Modified: ' . date('r', filemtime($filename)));
        readfile($filename);
        exit;
    }


    /* Getters/Setters
     */

    /**
     * Set quality
     *
     * @param $quality
     */
    private function setQuality($quality) {
        $this->quality = $quality;
    }

    /**
     * Get quality
     *
     * @return int
     */
    private function getQuality() {
        return $this->quality;
    }
}
