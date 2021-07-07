<?php

/**
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Pastoral\UploadQueue;

use Schoolbox\ISoftDeletable;
use Schoolbox\Mapper\Traits\Model\CreatedAtDate;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use Schoolbox\Mapper\Traits\Model\Id;
use Schoolbox\Mapper\Traits\Model\UpdatedAtDate;
use Schoolbox\Pastoral\Model as Pastoral;
use Schoolbox\Resource\Storage\IStorage;

class Model implements ISoftDeletable
{
    use Id;
    use CreatedAtDate;
    use UpdatedAtDate;
    use DeletedAtDate;

    /** @var Pastoral */
    protected $pastoral;

    /** @var int SIS Pastoral record ID */
    protected $externalId;

    /** @var string|null SIS Student ID */
    protected $studentExternalId;

    /** @var IStorage $storage */
    protected $storage;

    /** @var string */
    protected $type;

    /** @var string */
    protected $status;

    /** @var string */
    protected $message;

    /** @var string */
    protected $realname;

    /**
     * @param Pastoral $pastoral
     *
     * @return self
     */
    public function setPastoral($pastoral)
    {
        $this->pastoral = $pastoral;

        return $this;
    }

    /**
     * @param IStorage $storage
     *
     * @return self
     */
    public function setStorage($storage)
    {
        $this->storage = $storage;

        return $this;
    }

    /**
     * @return IStorage
     */
    public function getStorage()
    {
        return $this->storage;
    }

    /**
     * @return Pastoral
     */
    public function getPastoral()
    {
        return $this->pastoral;
    }

    /**
     * @return string
     */
    public function getRealname(): string
    {
        return $this->realname;
    }

    /**
     * @param string $realname
     * @return static
     */
    public function setRealname($realname)
    {
        $this->realname = $realname;
        return $this;
    }


    /**
     * @return int
     */
    public function getExternalId()
    {
        return $this->externalId;
    }

    /**
     * @param int $externalId
     *
     * @return self
     */
    public function setExternalId($externalId)
    {
        $this->externalId = $externalId;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getStudentExternalId(): ?string
    {
        return $this->studentExternalId;
    }

    /**
     * @param string|null $studentExternalId
     *
     * @return self
     */
    public function setStudentExternalId(?string $studentExternalId): self
    {
        $this->studentExternalId = $studentExternalId;

        return $this;
    }

    /**
     * @param string $status
     *
     * @return self
     */
    public function setStatus($status)
    {
        $this->status = $status;

        return $this;
    }

    /**
     * @return string
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * @param string $message
     *
     * @return self
     */
    public function setMessage($message)
    {
        $this->message = $message;

        return $this;
    }

    /**
     * @return string
     */
    public function getMessage()
    {
        return $this->message;
    }
}
