<?php
/**
 * File
 *
 * A File that was uploaded as part of a Student's Submission
 * for a particular Due Work
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Learning\Assessment\DueWork\Submission\File;

use Schoolbox\Resource\Storage\IStorage;
use \Schoolbox\Mapper\Traits\Model\CreatedAtDate;
use \Schoolbox\Resource\File\StorableTrait;

class Model implements IStorage
{
    use StorableTrait;
    use CreatedAtDate;

    /**
     * A crococococodoc UUID value which identifies the file
     * that was uploaded to crococococdoc
     *
     * @var string
     */
    protected $crocodocId;

    /**
     * A plagscan result instance
     *
     * @var Plagscan
     */
    protected $plagscan;

    /**
     * File constructor.
     */
    public function __construct()
    {
    }

    /**
     * This Submission File has an attached Plagscan result document
     *
     * @return bool
     */
    public function hasPlagscan()
    {
        return $this->getPlagscan() !== null;
    }

    /**
     * Wrap up hasPlagscan() logic here, to save you from doing it everywhere
     *
     * @return string
     */
    public function findPlagscanScore()
    {
        if ($this->hasPlagscan()) {
            return $this->getPlagscan()->getScore();
        }

        /* TODO: Probably check for returning a status of some sort first
         */

        return '';
    }

    /**
     * Is this a file that was spawned via Submit Online wizzywig?
     */
    public function isOnlineSubmission()
    {
        /* Known versions of the Online Submission file which stores
         * the wizzywig contents
         */
        $knownFilenames = ['online-submission', 'Online Submission'];

        if (in_array($this->getFilename(), $knownFilenames) && ($this->getStorage()->getMimetype() == 'text/html')) {
            return true;
        }

        return false;
    }

    /* Getters/Setters
     */

    /**
     * @return string
     */
    public function getCrocodocId()
    {
        return $this->crocodocId;
    }

    /* Getters/Setters
     */

    /**
     * @param string $crocodocId
     * @return self
     */
    public function setCrocodocId($crocodocId)
    {
        $this->crocodocId = $crocodocId;
        return $this;
    }

    /**
     * @return Plagscan
     */
    public function getPlagscan()
    {
        return $this->plagscan;
    }

    /**
     * @param Plagscan $plagscan
     * @return self
     */
    public function setPlagscan($plagscan)
    {
        $this->plagscan = $plagscan;
        return $this;
    }
}