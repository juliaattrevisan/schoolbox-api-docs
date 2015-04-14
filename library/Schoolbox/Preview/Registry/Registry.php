<?php
/**
 * Registry
 *
 * A list of preprocessing Observer classes which help determine
 * the actual url from some encoded/encapsulated context, like a
 * link imprisoned in redir.php?id=39, or in task/start/143
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Preview\Registry;

use Schoolbox\Collection;
use Schoolbox\Preview\Registry\Decoder\Base as DecoderBase;

class Registry {

    /**
     * A Collection of decoders
     *
     * @var Collection
     */
    private $decoders = null;

    /**
     * The original url to dstill e.g. /task/start/143
     *
     * @var string
     */
    private $url = '';

    /**
     * Constructor
     */
    public function __construct($url) {
        $this->setUrl($url);
        $this->setDecoders(new Collection());
    }

    /**
     * Add a decoder to the stack
     *
     * @param DecoderBase $decoder
     */
    public function add(DecoderBase $decoder) {
        $this->getDecoders()->add($decoder);
    }

    /**
     * Use the set of decoders to find the first one that
     * can understand the format of the url
     */
    public function findUrl() {
        foreach ($this->getDecoders() as $currentDecoder) {
            $outcome = $currentDecoder->decode($this->getUrl());

            if (strlen($outcome)) {
                return $outcome;
            }
        }

        return '';
    }

    /* Getters/Setters
     */

    /**
     * Set decoders
     *
     * @param Collection $decoders
     */
    private function setDecoders($decoders)
    {
        $this->decoders = $decoders;
    }

    /**
     * Get decoders
     *
     * @return Collection
     */
    private function getDecoders()
    {
        return $this->decoders;
    }

    /**
     * Set url
     *
     * @param string $url
     */
    private function setUrl($url)
    {
        $this->url = $url;
    }

    /**
     * Get url
     *
     * @return string
     */
    private function getUrl()
    {
        return $this->url;
    }
}