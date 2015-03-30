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
use Schoolbox\Resource\Storage\Mapper as StorageMapper;
use Schoolbox\Resource\Storage\Model as StorageModel;
use Schoolbox\File\Mimetype;

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
            $this->processStorage($url, $hash);
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
     * @return bool
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
     * Process Storage file analysis
     *
     * @param $url
     * @param $hash
     */
    private function processStorage($url, $hash) {
        $storageMapper = new StorageMapper();

        try {
            $item = $storageMapper->findByHash($hash);
        }
        catch (\Exception $e) {

            /* A default stub object, for interface compatibility
             */
            $item = new StorageModel();
            $item->setMimetype(new Mimetype());
        }

        $this->start([
            new Image($url, $item),
            new Media($url, $item),
            new IFrame($url, $item),
            new Redirect($url, $item),
        ]);
    }
}