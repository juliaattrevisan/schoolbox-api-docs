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

class Media extends Base {

    /**
     * Audio/visual files
     *
     * @return mixed
     */
    public function test() {

        if($this->getItem() instanceof \Schoolbox\Resource\File\Model) {
            $type = 'sbfs';
            $id = $this->getItem()->getId();
        }
        else if($this->getItem() instanceof \Schoolbox\Resource\Storage\IStorage) {
            $type = 'storage';
            $id = $this->getItem()->getHash();
        }
        else {
            return $this->testNext();
        }

        if ($this->getItem()->getMimetype()->isVideo()) {
            return [
                'type' => 'inline',
                'twig' => [
                    'template' => '@preview/Strategy/Internal/Video.twig',
                    'data' => [
                        'type' => $type,
                        'hash' => $id
                    ]
                ]
            ];
        }
        elseif ($this->getItem()->getMimetype()->isAudio()) {
            return [
                'type' => 'inline',
                'twig' => [
                    'template' => '@preview/Strategy/Internal/Audio.twig',
                    'data' => [
                        'type' => $type,
                        'hash' => $id
                    ]
                ]
            ];
        }

        return $this->testNext();
    }
}