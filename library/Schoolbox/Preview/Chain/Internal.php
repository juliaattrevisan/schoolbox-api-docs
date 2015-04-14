<?php
/**
 * Chain
 *
 * Seeks preview from an External (non-local) url
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Preview\Chain;

use Schoolbox\Preview\Chain\Strategy\Internal\Image;
use Schoolbox\Preview\Chain\Strategy\Internal\Media;
use Schoolbox\Preview\Chain\Strategy\Internal\IFrame;
use Schoolbox\Preview\Chain\Strategy\Internal\Redirect;
use Schoolbox\Resource\FileMapper;
use Schoolbox\Resource\Storage\Mapper as StorageMapper;

class Internal extends Base {

    /**
     * Constructor
     *
     * @param $url
     */
    public function __construct($url) {

        /* Storage item: find the hash, and analyse it to find out
         * how to display it (or redirect, if it can't be displayed)
         */
        if ($hash = $this->isStorage($url)) {
            try {
                $item = StorageMapper::getInstance()->findByHash($hash);
                $this->processStorage($url, $item);
            } catch (\Schoolbox\Mapper\Exception $e ) {
                //noop
            }
        }
        else if($id = $this->isSend($url)) {
            try {
                $item = FileMapper::getInstance()->findById($id);
                $this->processStorage($url, $item);
            } catch (\Schoolbox\Mapper\Exception $e ) {
                //noop
            }
        }
        /* Just redirect straight there
         */
        else {
            $this->start([
                new Redirect($url, null),
            ]);
        }
    }

    /**
     * Get the hash out of a storage url
     *
     * @param $url
     * @return string
     */
    private function isStorage($url) {
        $hash = false;

        preg_match('/storage\/fetch\.php\?hash=([a-z0-9]+)/i', $url, $matches);

        if (count($matches)) {
            $hash = $matches[1];
        }

        return $hash;
    }

    /**
     * Get the id from a send URL
     *
     * @param $url
     * @return string
     */
    private function isSend($url) {
        preg_match('/send.php\?id=([0-9]+)/i', $url, $matches);

        if (count($matches)) {
            $id = $matches[1];
        }

        return $id;
    }


    /**
     * Process Storage file analysis
     *
     * @param $url
     * @param $item
     */
    private function processStorage($url, $item) {
        $this->start([
            new Image($url, $item),
            new Media($url, $item),
            new IFrame($url, $item),
            new Redirect($url, $item),
        ]);
    }
}