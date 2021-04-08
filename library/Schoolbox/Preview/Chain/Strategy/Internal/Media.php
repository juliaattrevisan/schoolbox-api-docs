<?php
/**
 * Media
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Preview\Chain\Strategy\Internal;

use Schoolbox\Video\QueueMapper;

class Media extends Base
{
    /**
     * Audio/visual files
     *
     * @return mixed
     */
    public function test()
    {
        if ($this->item instanceof \Schoolbox\Resource\File\Model) {
            $type = 'sbfs';
            $id = $this->item->getId();
        } elseif ($this->item instanceof \Schoolbox\Resource\Storage\IStorage) {
            $type = 'storage';
            $id = $this->item->getHash();
        } else {
            return $this->testNext();
        }

        if ($this->item->getMimetype()->isVideo()) {
            return [
                'type' => 'inline',
                'twig' => [
                    'template' => '@preview/Strategy/Internal/Video.twig',
                    'data' => [
                        'type' => $type,
                        'hash' => $id,
                        'error' => QueueMapper::getInstance()->hasError($this->item),
                    ]
                ]
            ];
        } elseif ($this->item->getMimetype()->isAudio()) {
            return [
                'type' => 'inline',
                'twig' => [
                    'template' => '@preview/Strategy/Internal/Audio.twig',
                    'data' => [
                        'type' => $type,
                        'hash' => $id,
                        'error' => QueueMapper::getInstance()->hasError($this->item),
                    ]
                ]
            ];
        }

        return $this->testNext();
    }
}
