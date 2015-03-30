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

use Schoolbox\Preview\Chain\Strategy\Internal\LinkRedir;
use Schoolbox\Preview\Chain\Strategy\Internal\Image;
use Schoolbox\Preview\Chain\Strategy\Internal\Media;
use Schoolbox\Preview\Chain\Strategy\Internal\IFrame;
use Schoolbox\Preview\Chain\Strategy\Internal\Redirect;
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
            $storageMapper = new StorageMapper();

            try {
                $item = $storageMapper->findByHash($hash);

                $this->start([
                    new Image($url, $item),
                    new Media($url, $item),
                    new IFrame($url, $item),
                    new Redirect($url, $item),
                ]);
            }
            catch (\Exception $e) {
                error(41);
            }
        }
        /* LinkRedir: get the url, log the view, then redirect or open
         * it in the Preview modal as usual
         */
        else if ($id = $this->isLinkRedir($url)) {
            var_dump($id);
            die();
        }
        /* None of the usual cases - just redirect there
         */
        else {
            $this->start([
                new Redirect($url, null),
            ]);
        }
    }

    /**
     * Get the link id out of the url
     *
     * @param $url
     * @return bool
     */
    private function isLinkRedir($url) {
        $id = false;

        $bits = parse_url($url);
        $querystring = $bits['query'];

        preg_match('/id=([0-9]+)/', $querystring, $matches);

        if (count($matches)) {
            $id = $matches[1];
        }

        return $id;
    }

    /**
     * Get the hash out of a storage url
     *
     * @param $url
     * @return bool
     */
    private function isStorage($url) {
        $hash = false;

        $bits = parse_url($url);
        $querystring = $bits['query'];

        preg_match('/storage\/fetch\.php?hash=([a-z0-9]+)/i', $querystring, $matches);

        if (count($matches)) {
            $hash = $matches[1];
        }

        return $hash;
    }
}