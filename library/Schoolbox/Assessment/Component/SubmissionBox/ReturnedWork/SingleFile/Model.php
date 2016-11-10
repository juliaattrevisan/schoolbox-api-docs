<?php
/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Assessment\Component\SubmissionBox\ReturnedWork\SingleFile;

use Schoolbox\Assessment\Component\SubmissionBox\ReturnedWork;
use Schoolbox\ISoftDeletable;
use Schoolbox\Mapper\Traits\Model\CreatedAtDate;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use Schoolbox\Mapper\Traits\Model\UpdatedAtDate;
use Schoolbox\Resource\File\IFile;
use Schoolbox\Resource\File\StorableTrait;
use Schoolbox\User;

/**
 * A single file within a student's submission to a due work.
 */
class Model implements IFile, ISoftDeletable
{
    use StorableTrait;
    use CreatedAtDate;
    use UpdatedAtDate;
    use DeletedAtDate;

    /**
     * @var ReturnedWork
     */
    protected $return;

    /**
     * @var string
     */
    protected $uuid;


    /**
     * @return string
     */
    public function getUuid()
    {
        return $this->uuid;
    }

    /**
     * @param string $uuid
     * @return static
     */
    public function setUuid($uuid)
    {
        $this->uuid = $uuid;
        return $this;
    }

    /**
     * @return ReturnedWork
     */
    public function getReturn()
    {
        return $this->return;
    }

    /**
     * @param ReturnedWork $return
     * @return static
     */
    public function setReturn(ReturnedWork $return)
    {
        $this->return = $return;
        return $this;
    }

    /**
     * @return User
     */
    public function getOwner()
    {
        return $this->getReturn()->getOwner();
    }
}
