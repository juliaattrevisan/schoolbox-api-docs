<?php

/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace User\Logger;

use Schoolbox\Mapper\Traits\Model\Id;
use Alaress_Date_Format;

class Model
{
    use Id;

    /**
     * Intentionally not a model (this is a log record)
     *
     * @var string
     */
    private $user;

    /**
     * Intentionally not a model (this is a log record)
     *
     * @var string
     */
    private $assumed;

    /** @var string */
    private $ip;

    /** @var Alaress_Date_Format */
    private $createdAt;


    /**
     * @return null|string
     */
    public function getUser(): ?string
    {
        return $this->user;
    }


    /**
     * @param string $user
     * @return $this
     */
    public function setUser(string $user): self
    {
        $this->user = $user;
        return $this;
    }


    /**
     * @return null|string
     */
    public function getAssumed(): ?string
    {
        return $this->assumed;
    }


    /**
     * @param string $assumed
     * @return $this
     */
    public function setAssumed(string $assumed): self
    {
        $this->assumed = $assumed;
        return $this;
    }


    /**
     * @return null|string
     */
    public function getIp(): ?string
    {
        return $this->ip;
    }


    /**
     * @param string $ip
     * @return $this
     */
    public function setIp(string $ip): self
    {
        $this->ip = $ip;
        return $this;
    }


    /**
     * @return null|Alaress_Date_Format
     */
    public function getCreatedAt(): ?Alaress_Date_Format
    {
        return $this->createdAt;
    }


    /**
     * @param Alaress_Date_Format $createdAt
     * @return $this
     */
    public function setCreatedAt(Alaress_Date_Format $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }
}
