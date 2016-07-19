<?php
/**
 * Coach
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\SportsFixtures\Snapshot;

use \Schoolbox\SportsFixtures\Team\Metadata\Metadata;

class Coach implements ISnapshot
{
    /**
     * @var Metadata
     */    
    private $metadata;

    /**
     * Coach constructor.
     *
     * @param Metadata $metadata
     */
    public function __construct(Metadata $metadata)
    {
        $this->setMetadata($metadata);
    }

    /**
     * @inheritdoc
     */
    public function snapshot()
    {
    }

    /* Getters/Setters
     */

    /**
     * @return Metadata
     */
    private function getMetadata()
    {
        return $this->metadata;
    }

    /**
     * @param Metadata $metadata
     */
    private function setMetadata($metadata)
    {
        $this->metadata = $metadata;
    }
}