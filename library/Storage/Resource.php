<?php
/**
 * Storage Resource Model
 *
 * This is the standard object for a stored resource in Schoolbox
 *
 * @deprecated Use Schoolbox\File instead
 *
 * @author      James Leckie <james.leckie@alaress.com.au>
 * @copyright   2012 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 */

class Storage_Resource extends Model {
    public $id;
    public $filename;
    public $hash;
    public $filesize;
    public $mime;
    public $uploaded_by;
    public $uploaded_on;
    public $last_accessed;


    public function setHash($filelocation) {
        $this->hash = sha1_file($filelocation);
    }

    public function getFileLocation() {
        return Cfg::get('storage_dir') . '/' . $this->id . '.dat';
    }

}