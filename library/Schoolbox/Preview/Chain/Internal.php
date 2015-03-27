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

class Internal extends Base {

    /**
     * Constructor
     *
     * @param $url
     * @param $hash
     */
    public function __construct($url, $hash) {

        if ($this->isStorage($url)) {
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
    }


    /**
     * Check if this is a storage url, it could contain anything
     */
    protected function isStorage($url) {
        preg_match('#storage\/fetch\.php#', $url, $matches);

        if (count($matches)) {
            return true;
        }

        return false;
    }
}