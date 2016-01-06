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

use \Schoolbox\Resource\Attachment\Collector\CollectorFactory;

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
     * @param $hashTuple
     */
    public function __construct($hashTuple) {
        $this->setHash(str_replace('sbfs', 'browse', $hashTuple));
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
        list($filesystem, $id) = explode(':', $this->getHash());

        if ($this->getHash()) {
            $collector = (new CollectorFactory())->build([$this->getHash()])->getFirst();

            $data = [
                'filesystem' => $filesystem,
                'type' => $collector->getMimetype()->getType(),
                'hash' => $collector->getHash(),

                /* Used for video/audio, sadly
                 */
                'id' => $id
            ];
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
}