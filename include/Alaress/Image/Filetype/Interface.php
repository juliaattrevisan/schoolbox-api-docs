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
    public function create($filename);

    public function stream($image);

    public function streamFile($filename);

    public function save($image, $filename);
}
