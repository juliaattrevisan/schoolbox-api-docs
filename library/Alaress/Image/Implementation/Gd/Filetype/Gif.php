<?php
/**
 * Alaress_Image_Filetype_Gif
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Alaress_Image_Filetype_Gif extends Alaress_Image_Filetype_Abstract {

    /**
     * Create an image
     *
     * @param $filename
     * @return resource
     */
    public function loadImageResource($filename) {
        //
        // Sadly silence operator is needed for all imagecreatefrom* methods,
        // otherwise error output corrupts image output.
        return @imagecreatefromgif($filename);
    }

    /**
     * Save image to file system
     *
     * @param $image
     * @param $filename
     */
    public function save($image, $filename) {
        imagegif($image, $filename);
    }


    private function outputHeaders() {
        header('Cache-Control: max-age=10800, private');
        header('Expires: ' . date(DATE_RFC822, strtotime(' 2 day')));
        header('Pragma: private');
        header('Content-type: image/gif');
    }

    /**
     * Stream image to browser directly
     *
     * @param $image
     */
    public function stream($image) {
        $this->outputHeaders();
        imagegif($image);
        exit;
    }

    public function streamFile($filename) {
        $this->outputHeaders();
        header('Content-length: ' . filesize($filename));
        header('Last-Modified: ' . date('r', filemtime($filename)));
        readfile($filename);
        exit;
    }

    /**
     * GIF does support the alpha channel
     *
     * @return bool Always true
     */
    public function supportsAlphaChannel() {
        return true;
    }

}
