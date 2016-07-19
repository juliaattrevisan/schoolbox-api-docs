<?php
/**
 * Base
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\SportsFixtures\Snapshot\Type;

use \Schoolbox\SportsFixtures\Team\Metadata\Metadata;
use \Schoolbox\SportsFixtures\Match\Model as Match;

abstract class Base
{
    /**
     * @var Match
     */
    protected $match;

    /**
     * @var Metadata
     */
    protected $metadata;

    /**
     * Coach constructor.
     *
     * @param Match $match
     * @param Metadata $metadata
     */
    public function __construct(Match $match, Metadata $metadata)
    {
        $this->setMatch($match);
        $this->setMetadata($metadata);

        $this->setup();
    }

    abstract protected function setup();

    /**
     * @return Match
     */
    protected function getMatch()
    {
        return $this->match;
    }

    /**
     * @param Match $match
     */
    protected function setMatch($match)
    {
        $this->match = $match;
    }

    /**
     * @return Metadata
     */
    protected function getMetadata()
    {
        return $this->metadata;
    }

    /**
     * @param Metadata $metadata
     */
    protected function setMetadata($metadata)
    {
        $this->metadata = $metadata;
    }

}