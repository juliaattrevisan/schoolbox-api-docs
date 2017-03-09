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

class Registry
{

    /**
     * A Collection of decoders
     *
     * @var Collection|DecoderBase[]
     */
    private $decoders;

    /**
     * The original url to distill e.g. /task/start/143
     *
     * @var string
     */
    private $url;


    /**
     * Constructor
     *
     * @param string $url
     */
    public function __construct($url)
    {
        $this->url      = $url;
        $this->decoders = new Collection();
    }


    /**
     * Add a decoder to the stack
     *
     * @param DecoderBase $decoder
     */
    public function add(DecoderBase $decoder)
    {
        $this->decoders->add($decoder);
    }


    /**
     * Use the set of decoders to find the first one that
     * can understand the format of the url
     */
    public function findUrl()
    {
        foreach ($this->decoders as $currentDecoder) {
            $outcome = $currentDecoder->decode($this->url);

            if ('' !== $outcome) {
                return $outcome;
            }
        }

        return '';
    }

}
