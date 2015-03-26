<?php
/**
 * Image
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Preview\Chain\Strategy\Internal;

use Schoolbox\Resource\Storage\Mapper as StorageMapper;

class Image extends Base {

    /**
     * Test
     *
     * @return mixed
     */
    public function test() {
        if ($this->isStorage()) {
            $storageMapper = new StorageMapper();

            try {
                $item = $storageMapper->findByHash($this->getHash());

                if ($item->getMimetype()->isImage()) {
                    return [
                        'type' => 'html-fragment',
                        'twig' => [
                            'template' => '@preview/Strategy/Image.twig',
                            'data' => [
                                'src' => $this->getUrl()
                            ]
                        ]
                    ];
                }
            }
            catch (\Exception $e) {
                error(41);
            }
        }

        return $this->testNext();
    }

    /**
     * Check if this is a storage url, it could contain anything
     */
    private function isStorage() {
        preg_match('#storage\/fetch\.php#', $this->getUrl(), $matches);

        if (count($matches)) {
            return true;
        }

        return false;
    }
}