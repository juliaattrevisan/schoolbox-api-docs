<?php
/**
 * FileFactory
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Editor;

use \Schoolbox\Resource\File\Mapper as FileMapper;


class FileFactory {

    /**
     * A hash tuple e.g. browse:55
     *
     * @var string
     */
    private $hash = '';

    /**
     * An otfs numeric id
     *
     * @var string
     */
    private $id = '';

    /**
     * Constructor
     *
     * @param $hashOrId
     */
    public function __construct($hashOrId) {
        if (is_numeric($hashOrId)) {
            $this->setId($hashOrId);
        }
        else {
            $this->setHash($hashOrId);
        }
    }

    /**
     * Produce json_encoded data
     *
     * @return string
     */
    public function produce() {
        $data = [
            'type' => '',
            'hash' => '',
            'id' => ''
        ];

        /* When identifying a storage file via browse:xxx which is
         * a string used internally in resource browser messages
         */
        if ($this->getHash()) {
            list($type, $id) = explode(':', $this->getHash());
        }
        /* When identifying the difference between a video and
         * an audio via sendVideo.php?id=xxx
         */
        else {
            $type = 'browse';
            $id = $this->getId();
        }

        switch($type) {
            case 'browse':
                $mapper = new FileMapper();
                $file = $mapper->findById($id);

                $data = [
                    'type' => $file->getMimetype()->getType(),
                    'hash' => $file->getHash(),

                    /* Used for video/audio, sadly
                     */
                    'id' => $file->getId()
                ];

                break;

            default:
                break;
        }

        return json_encode($data);
    }

    /* Getters/Setters
     */

    /**
     * Set hash
     *
     * @param string $hash
     */
    private function setHash($hash)
    {
        $this->hash = $hash;
    }

    /**
     * Get hash
     *
     * @return string
     */
    private function getHash()
    {
        return $this->hash;
    }

    /**
     * Get id
     *
     * @return string
     */
    private function getId()
    {
        return $this->id;
    }

    /**
     * Set id
     *
     * @param string $id
     */
    private function setId($id)
    {
        $this->id = $id;
    }
}