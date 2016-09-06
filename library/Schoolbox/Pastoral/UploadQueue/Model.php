<?php
/**
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Pastoral\UploadQueue;

use Schoolbox\ISoftDeletable;
use Schoolbox\Mapper\Traits\Model\CreatedAtDate;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use Schoolbox\Mapper\Traits\Model\Id;
use Schoolbox\Mapper\Traits\Model\UpdatedAtDate;

class Model implements ISoftDeletable
{
    use Id;
    use CreatedAtDate;
    use UpdatedAtDate;
    use DeletedAtDate;
    
    /** @var int */
    protected $pastoralId;

    /** @var string */
    protected $hash;

    /** @var string */
    protected $type;

    /** @var string */
    protected $status;

    /**
     * @return int
     */
    public function getPastoralId() {
        return $this->pastoralId;
    }

    /**
     * @param int $pastoralId
     * @return self
     */
    public function setPastoralId($pastoralId) {
        $this->pastoralId = $pastoralId;
        return $this;
    }

    /**
     * @param string $hash
     * @return self
     */
    public function setHash($hash) {
        $this->hash = $hash;
        return $this;
    }

    /**
     * @return string
     */
    public function getHash() {
        return $this->hash;
    }

    /**
     * @param string $type
     * @return self
     */
    public function setType($type) {
        $this->type = $type;
        return $this;
    }

    /**
     * @return string
     */
    public function getType() {
        return $this->type;
    }

    /**
     * @param string $status
     * @return self
     */
    public function setStatus($status) {
        $this->status = $status;
        return $this;
    }

    /**
     * @return string
     */
    public function getStatus() {
        return $this->status;
    }

}
