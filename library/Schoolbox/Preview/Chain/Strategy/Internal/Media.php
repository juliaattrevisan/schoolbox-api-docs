<?php
/**
 * Media
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Preview\Chain\Strategy\Internal;

use Schoolbox\Resource\Storage\Mapper as StorageMapper;

class Media extends Base {

    /**
     * Audio/visual files
     *
     * @return mixed
     */
    public function test() {
        if ($this->isStorage()) {
            $storageMapper = new StorageMapper();

            try {
                $item = $storageMapper->findByHash($this->getHash());

                if ($item->getMimetype()->isVideo()) {
                    return [
                        'type' => 'html-fragment',
                        'twig' => [
                            'template' => '@preview/Strategy/Internal/Video.twig',
                            'data' => [
                                'hash' => $this->getHash()
                            ]
                        ]
                    ];
                }
                elseif ($item->getMimetype()->isAudio()) {
                    return [
                        'type' => 'html-fragment',
                        'twig' => [
                            'template' => '@preview/Strategy/Internal/Audio.twig',
                            'data' => [
                                'hash' => $this->getHash()
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