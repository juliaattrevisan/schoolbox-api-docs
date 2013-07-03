<?php
/**
 * Alaress_Image_Filetype_Interface
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
interface Alaress_Image_Filetype_Interface {

    /**
     * Create an image
     *
     * @param $filename
     * @throws Exception_Schoolbox_NotFound if file can't be read
     * @return resource
     */
    public function create($filename);

    public function loadImageResource($filename);

    public function stream($image);

    public function streamFile($filename);

    public function save($image, $filename);
}
