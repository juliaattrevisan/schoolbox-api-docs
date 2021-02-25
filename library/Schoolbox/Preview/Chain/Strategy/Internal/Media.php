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
            $error = false;

            /* Check its status. If it's processing we can send that flag out to
             * frontend so we can hide the controls from the ui
             *
             * Seems like you can only have unique hashes in this table via business
             * logic, but not by db constraints like UNIQUE keys?
             */
            try {
                $videoQueueItem = (QueueMapper::getInstance())
                    ->findOneByColumn('hash', $this->getItem()->getStorage()->getHash());
            } catch (\Schoolbox\Mapper\Exception $e) {

                // noop: hash not found
            }

            /* If it was in the queue, check its processing status
             * If it's complete, we can show it normally
             * Otherwise send the $error flag into the ui
             */
            if ($videoQueueItem && !$videoQueueItem->isComplete()) {
                $error = true;
            }

            return [
                'type' => 'inline',
                'twig' => [
                    'template' => '@preview/Strategy/Internal/Video.twig',
                    'data' => [
                        'type' => $type,
                        'hash' => $id,
                        'error' => $error
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
