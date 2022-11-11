<?php

/**
 * Pad
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Etherpad;

use Alaress_Date_Format;
use Schoolbox\ISoftDeletable;
use Schoolbox\Mapper\Traits\Model\CreatedAtDate;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use Schoolbox\Mapper\Traits\Model\UpdatedAtDate;

class Pad implements ISoftDeletable
{
    use CreatedAtDate;
    use UpdatedAtDate;
    use DeletedAtDate;

    /**
     * Identifier
     *
     * @var null
     */
    private $id = null;

    /**
     * Etherpad Pad ID
     *
     * @var null
     */
    private $padId = '';

    /**
     * Homepage id
     *
     * @var string
     */
    private $homepageId = '';

    /**
     * The place where this pad was first created
     *
     * @var string
     */
    private $environment = '';

    /**
     * Actual pad name given by user
     *
     * @var string
     */
    private $name = '';

    /**
     * Pad body
     *
     * @var string
     */
    private $body = '';

    /**
     * Whether this pad is locked
     *
     * @var int
     */
    private $lock = 0;

    /**
     * Constructor
     */
    public function __construct()
    {
    }

    /**
     * Determine whether this pad is locked or not
     *
     * @return bool
     */
    public function isLocked()
    {
        if ($this->getLock() == 1) {
            return true;
        }

        return false;
    }

    /**
     * Lock this pad
     */
    public function lock()
    {
        $this->setLock(1);
    }

    /**
     * Unlock this pad
     */
    public function unlock()
    {
        $this->setLock(0);
    }

    /**
     * Client-facing version of the name, which includes
     * the lock status of the pad
     *
     * @return string
     */
    public function getDisplayName()
    {
        $name = $this->getName();

        if ($this->isLocked()) {
            $name .= ' (Locked)';
        }

        return $name;
    }

    /**
     * Was this pad first spawned in the environment indicated by INSTANCE_ID
     *
     * @return bool
     */
    public function isExistsInCurrentEnvironment()
    {
        if ($this->getEnvironment() == INSTANCE_ID) {
            return true;
        }

        return false;
    }

    /* Getters/Setters
     */

    /**
     * Set body
     *
     * @param string $body
     */
    public function setBody($body)
    {
        $this->body = $body;
    }

    /**
     * Get body
     *
     * @return string
     */
    public function getBody()
    {
        return $this->body;
    }


    /**
     * Set id
     *
     * @param null $id
     */
    public function setId($id)
    {
        $this->id = $id;
    }

    /**
     * Get id
     *
     * @return null
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set name
     *
     * @param string $name
     */
    public function setName($name)
    {
        $this->name = $name;
    }

    /**
     * Get name
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Set pad id
     *
     * @param null $padId
     */
    public function setPadId($padId)
    {
        $this->padId = $padId;
    }

    /**
     * Get pad id
     *
     * @return null
     */
    public function getPadId()
    {
        return $this->padId;
    }

    /**
     * Set lock
     *
     * @param int $lock
     */
    public function setLock($lock)
    {
        $this->lock = $lock;
    }

    /**
     * Get lock
     *
     * @return int
     */
    public function getLock()
    {
        return $this->lock;
    }

    /**
     * Set homepage id
     *
     * @param string $homepageId
     */
    public function setHomepageId($homepageId)
    {
        $this->homepageId = $homepageId;
    }

    /**
     * Get homepage id
     *
     * @return string
     */
    public function getHomepageId()
    {
        return $this->homepageId;
    }

    /**
     * Set environment
     *
     * @param string $environment
     */
    public function setEnvironment($environment)
    {
        $this->environment = $environment;
    }

    /**
     * Get environment
     *
     * @return string
     */
    public function getEnvironment()
    {
        return $this->environment;
    }
}
