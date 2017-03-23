<?php
/**
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Pastoral\Comment;

use Schoolbox\ISoftDeletable;
use Schoolbox\Mapper\Traits\Model\Id;
use Schoolbox\Mapper\Traits\Model\CreatedAtDate;
use Schoolbox\Mapper\Traits\Model\UpdatedAtDate;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use Schoolbox\User;
use Schoolbox\Pastoral\Model as Pastoral;

class Model implements ISoftDeletable {

    use Id;
    use CreatedAtDate;
    use UpdatedAtDate;
    use DeletedAtDate;
    
    /** @var Pastoral */
    protected $pastoral;

    /** @var int */
    protected $externalId;

    /** @var string */
    protected $body;

    /** @var User */
    protected $owner;

    /**
     * @param Pastoral $pastoral
     * @return self
     */
    public function setPastoral($pastoral) {
        $this->pastoral = $pastoral;
        return $this;
    }

    /**
     * @return Pastoral
     */
    public function getPastoral() {
        return $this->pastoral;
    }

    /**
     * @param string $externalId
     * @return self
     */
    public function setExternalId($externalId) {
        $this->externalId = $externalId;
        return $this;
    }

    /**
     * @return int
     */
    public function getExternalId() {
        return $this->externalId;
    }    

    /**
     * @param string $body
     * @return self
     */
    public function setBody($body) {
        $this->body = $body;
        return $this;
    }

    /**
     * @return string
     */
    public function getBody() {
        return $this->body;
    }

    /**
     * @param User $owner
     * @return self
     */
    public function setOwner($owner) {
        $this->owner = $owner;
        return $this;
    }

    /**
     * @return User
     */
    public function getOwner() {
        return $this->owner;
    }
}
