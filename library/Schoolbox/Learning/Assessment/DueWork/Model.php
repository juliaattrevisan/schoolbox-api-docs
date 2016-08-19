<?php
/**
 * DueWork
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Learning\Assessment\DueWork;

use \Schoolbox\Collection;
use \Schoolbox\Learning\Assessment\AbstractAssessment;

class Model extends AbstractAssessment
{
    /**
     * Accept zero submissions online
     */
    const ACCEPT_ONLINE_NONE = 0;

    /**
     * Accept one submission online
     */
    const ACCEPT_ONLINE_SINGLE = 1;

    /**
     * Accept multiple submissions online
     */
    const ACCEPT_ONLINE_MULTI = 2;

    /**
     * All the Files that are part of the Due Work specification
     * e.g. "Here are some pictures to look at, so you can complete
     * this Due Work"
     *
     * @var Collection
     */
    protected $allFiles;

    /**
     * The "type" of the Due Work
     * (Essay, Homework, etc)
     *
     * @var Type
     */
    protected $type;

    /**
     * An enum for accepting submissions online
     *
     * @var int
     */
    protected $acceptOnline;

    /**
     * Model constructor.
     */
    public function __construct()
    {

        /* I know Dr hates hook methods, so do this instead
         */
        parent::__construct();

        $this->setAllFiles(new Collection());
        $this->setType(new Type());
        $this->setAcceptOnline(self::ACCEPT_ONLINE_NONE);
    }

    /**
     * @inheritdoc
     *
     * @return string
     */
    public function getTypeName()
    {
        return $this->getType()->getName();
    }

    /**
     * This DueWork accepts no submissions online
     *
     * @return $this
     */
    public function acceptsNone()
    {
        $this->setAcceptOnline(self::ACCEPT_ONLINE_NONE);
        return $this;
    }

    /**
     * This DueWork accepts one submissions online
     *
     * @return $this
     */
    public function acceptsSingle()
    {
        $this->setAcceptOnline(self::ACCEPT_ONLINE_SINGLE);
        return $this;
    }

    /**
     * This DueWork accepts multiple submissions online
     *
     * @return $this
     */
    public function acceptsMultiple()
    {
        $this->setAcceptOnline(self::ACCEPT_ONLINE_MULTI);
        return $this;
    }

    /* Getters/Setters
     */

    /**
     * @return Collection
     */
    public function getAllFiles()
    {
        return $this->allFiles;
    }

    /**
     * @param Collection $allFiles
     * @return self
     */
    public function setAllFiles($allFiles)
    {
        $this->allFiles = $allFiles;
        return $this;
    }

    /**
     * @return Type
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @param Type $type
     * @return self
     */
    public function setType($type)
    {
        $this->type = $type;
        return $this;
    }

    /**
     * @return int
     */
    public function getAcceptOnline()
    {
        return $this->acceptOnline;
    }

    /**
     * @param int $acceptOnline
     * @return $this
     */
    public function setAcceptOnline($acceptOnline)
    {
        $this->acceptOnline = $acceptOnline;
        return $this;
    }
}