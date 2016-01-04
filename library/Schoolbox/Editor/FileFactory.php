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
     * Constructor
     *
     * @param $hash
     */
    public function __construct($hash) {
        $this->setHash($hash);
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

        list($type, $id) = explode(':', $this->getHash());

        switch($type) {
            case 'browse':
                $mapper = new FileMapper();
                $file = $mapper->findById($id);

                $data = [
                    'type' => $file->getMimetype()->getType(),
                    'hash' => $file->getHash(),

                    /* Used for video, sadly
                     */
                    'id' => $file->getId()
                ];

                break;

            default:
                break;
        }

        return json_encode($data);
    }

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
}