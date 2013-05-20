<?php

/**
 * 
 * @author      Alex Tworowsky <alex.tworowsky@alaress.com.au>
 * @copyright   2011 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 * 
 */
 
class Plagscan_Document extends Model {
    const STATUS_QUEUED = 1;

    const STATUS_PROCESSING = 2;

    const STATUS_COMPLETE = 3;

    /**
     * Status of the document, if it's returned as unscannable?
     */
    const STATUS_UNSCANNABLE = -1;

    public $id;

    public $file;

    public $fileId;

    public $plagscan;

    public $plagscanId;

    public $state;

    public $status;

    public $html;
}