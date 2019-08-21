<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Assessment\Course;

use Schoolbox\ISoftDeletable;
use Schoolbox\Learning\Assessment\DataModel\AssessmentModelTrait;
use Schoolbox\Learning\Assessment\DataModel\IAssessableDataModel;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use Schoolbox\Mapper\Traits\Model\Id;
use Schoolbox\User;

class Task implements IAssessableDataModel, ISoftDeletable
{
    use AssessmentModelTrait;
    use Id;
    use DeletedAtDate;

    /**
     * @var string
     */
    protected $link;

    /**
     * @var bool
     */
    protected $requires_confirmation;

    /**
     * @var User
     */
    protected $owner;

    /**
     * Clears any attempts from the clone, and clones each Section in the Quiz
     * @return void
     */
    function __clone()
    {
        // Mark this object as a clone
        $this->id = null;
    }

    /* Getters / Setters
     */


    /**
     * @param  \Schoolbox\User $owner
     * @return $this
     */
    public function setOwner($owner)
    {
        $this->owner = $owner;
        return $this;
    }

    /**
     * @return \Schoolbox\User
     */
    public function getOwner()
    {
        return $this->owner;
    }

    /**
     * @param  string $link
     * @return $this
     */
    public function setLink($link)
    {
        $this->link = $link;
        return $this;
    }

    /**
     * @return string
     */
    public function getLink()
    {
        return $this->link;
    }

    /**
     * @param  bool $requires_confirmation
     * @return $this
     */
    public function setRequiresConfirmation($requires_confirmation)
    {
        $this->requires_confirmation = $requires_confirmation;
        return $this;
    }

    /**
     * @return bool
     */
    public function getRequiresConfirmation()
    {
        return $this->requires_confirmation;
    }
}
