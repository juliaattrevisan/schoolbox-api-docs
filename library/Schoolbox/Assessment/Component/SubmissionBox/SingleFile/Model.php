<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Component\SubmissionBox\SingleFile;

use Alaress_Date_Format;
use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\Resource\File\IFile;
use Schoolbox\Resource\File\StorableTrait;
use Schoolbox\User;

/**
 * File attached to a submission box (due work).
 */
class Model implements IFile
{
    use StorableTrait;


    /**
     * @var SubmissionBox
     */
    private $box;


    /**
     * @return SubmissionBox
     */
    public function getBox(): SubmissionBox
    {
        return $this->box;
    }


    /**
     * @param SubmissionBox $box
     * @return static
     */
    public function setBox(SubmissionBox $box): self
    {
        $this->box = $box;
        return $this;
    }


    /**
     * @return User
     */
    public function getOwner()
    {
        return $this->box->getOwner();
    }


    /**
     * @return Alaress_Date_Format
     */
    public function getCreatedAt()
    {
        return $this->storage->getCreatedAt();
    }
}
