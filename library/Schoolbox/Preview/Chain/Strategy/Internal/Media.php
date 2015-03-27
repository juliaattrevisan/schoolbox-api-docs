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
        if ($this->getItem()->getMimetype()->isVideo()) {
            return [
                'type' => 'html-fragment',
                'twig' => [
                    'template' => '@preview/Strategy/Internal/Video.twig',
                    'data' => [
                        'hash' => $this->getItem()->getHash()
                    ]
                ]
            ];
        }
        elseif ($this->getItem()->getMimetype()->isAudio()) {
            return [
                'type' => 'html-fragment',
                'twig' => [
                    'template' => '@preview/Strategy/Internal/Audio.twig',
                    'data' => [
                        'hash' => $this->getItem()->getHash()
                    ]
                ]
            ];
        }

        return $this->testNext();
    }
}