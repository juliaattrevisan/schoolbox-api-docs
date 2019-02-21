<?php
/**
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Pastoral\Comment;

use Schoolbox\ISoftDeletable;
use Schoolbox\Mapper\Exception\InsufficientHydrationException;
use Schoolbox\Mapper\Traits\Model\CreatedAtDate;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use Schoolbox\Mapper\Traits\Model\Id;
use Schoolbox\Mapper\Traits\Model\UpdatedAtDate;
use Schoolbox\Pastoral\Model as Pastoral;
use Schoolbox\User;

class Model implements ISoftDeletable
{
    use Id;
    use CreatedAtDate;
    use UpdatedAtDate;
    use DeletedAtDate;

    /** @var Pastoral */
    protected $pastoral;

    /**
     * Optional. Null if not inserted into external SIS
     *
     * @var null|string
     */
    private $externalCode;

    /** @var string */
    protected $body;

    /** @var bool */
    private $private;

    /** @var User */
    protected $owner;


    /**
     * Returns whether this is a public comment
     *
     * @return bool
     * @throws InsufficientHydrationException
     */
    public function isPublic(): bool
    {
        if ($this->private === null) {
            throw new InsufficientHydrationException("Expected bool, found null");
        }
        return !$this->private;
    }


    /**
     * Returns whether this is a private comment
     *
     * @return bool
     * @throws InsufficientHydrationException
     */
    public function isPrivate(): bool
    {
        if ($this->private === null) {
            throw new InsufficientHydrationException("Expected bool, found null");
        }
        return !$this->private;
    }


    /**
     * @param Pastoral $pastoral
     *
     * @return self
     */
    public function setPastoral(Pastoral $pastoral)
    {
        $this->pastoral = $pastoral;

        return $this;
    }

    /**
     * @return Pastoral
     */
    public function getPastoral()
    {
        return $this->pastoral;
    }

    /**
     * @param null|string $externalCode
     *
     * @return $this
     */
    public function setExternalCode($externalCode = null)
    {
        $this->externalCode = $externalCode;

        return $this;
    }

    /**
     * @return null|string
     */
    public function getExternalCode()
    {
        return $this->externalCode;
    }

    /**
     * @param string $body
     *
     * @return self
     */
    public function setBody($body)
    {
        $this->body = $body;

        return $this;
    }

    /**
     * @return string
     */
    public function getBody()
    {
        return $this->body;
    }

    /**
     * @param User $owner
     *
     * @return self
     */
    public function setOwner($owner)
    {
        $this->owner = $owner;

        return $this;
    }

    /**
     * @return User
     */
    public function getOwner()
    {
        return $this->owner;
    }

    /**
     * @param bool $private
     *
     * @return self
     */
    public function setPrivate($private)
    {
        $this->private = $private;

        return $this;
    }

    /**
     * @return bool
     */
    public function getPrivate()
    {
        return $this->private;
    }
}
