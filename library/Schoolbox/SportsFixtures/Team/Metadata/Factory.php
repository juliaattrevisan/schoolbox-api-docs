<?php
/**
 * Factory
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\SportsFixtures\Team\Metadata;

use \Schoolbox\SportsFixtures\Match\Model as Match;

class Factory
{
    /**
     * @var Match
     */
    private $match;

    /**
     * Factory constructor.
     *
     * @param $match
     */
    public function __construct($match)
    {
        $this->setMatch($match);
    }

    /**
     * If this is < 0, then the Match's start datetime is in the past
     * ie. it has already occurred
     *
     * @return Base
     */
    public function produce()
    {
        $diff = \Alaress_Date_Format::now()->diffInSeconds($this->getMatch()->getStartAt(), false);

        if ($diff > 0) {
            return new Current($this->getMatch());
        }

        return new Historic($this->getMatch());
    }

    /* Getters/Setters
     */

    /**
     * @return Match
     */
    private function getMatch()
    {
        return $this->match;
    }

    /**
     * @param Match $match
     */
    private function setMatch($match)
    {
        $this->match = $match;
    }
}