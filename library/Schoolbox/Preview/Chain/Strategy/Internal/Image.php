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
     * Image files
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
}