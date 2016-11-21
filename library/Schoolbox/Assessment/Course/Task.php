<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Assessment\Course;

use Alaress_Date_Format;
use Schoolbox\ISoftDeletable;
use Schoolbox\Learning\Assessment\DataModel\AssessmentModelTrait;
use Schoolbox\Learning\Assessment\DataModel\IAssessableDataModel;
use Schoolbox\Mapper\Traits\Model\Id;
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\User;

class Task implements ISoftDeletable, IAssessableDataModel
{
    use AssessmentModelTrait;

    use Id;

    const TYPE_TASK = 1;
    const TYPE_DUE_WORK = 2;
    const TYPE_ONLINE_ASSESS = 3;

    /**
     * @var Folder
     */
    protected $category;

    /**
     * @var string
     */
    protected $title;

    /**
     * @var string
     */
    protected $link;

    /**
     * @var bool
     */
    protected $requires_confirmation;

    /**
     * @var int
     */
    protected $order;

    /**
     * @var User
     */
    protected $owner;

    /**
     * @var Alaress_Date_Format
     */
    protected $created;

    /**
     * @var Alaress_Date_Format
     */
    protected $deleted;


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
     * @param  \Schoolbox\Resource\Folder\Model $category
     * @return $this
     */
    public function setCategory($category)
    {
        $this->category = $category;
        return $this;
    }

    /**
     * @return \Schoolbox\Resource\Folder\Model
     */
    public function getCategory()
    {
        return $this->category;
    }

    /**
     * @param  \Alaress_Date_Format $created
     * @return $this
     */
    public function setCreated($created)
    {
        $this->created = $created;
        return $this;
    }

    /**
     * @return \Alaress_Date_Format
     */
    public function getCreated()
    {
        return $this->created;
    }

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
     * @param  \Alaress_Date_Format $deleted
     * @return $this
     */
    public function setDeleted($deleted)
    {
        $this->deleted = $deleted;
        return $this;
    }

    /**
     * @return \Alaress_Date_Format
     */
    public function getDeleted()
    {
        return $this->deleted;
    }

    /**
     * @param  string $title
     * @return $this
     */
    public function setTitle($title)
    {
        $this->title = $title;
        return $this;
    }

    /**
     * @return string
     */
    public function getTitle()
    {
        return $this->title;
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

    /**
     * @param  int $order
     * @return $this
     */
    public function setOrder($order)
    {
        $this->order = $order;
        return $this;
    }

    /**
     * @return int
     */
    public function getOrder()
    {
        return $this->order;
    }

    /**
     * Is this deleted? (added so there's a consistent method for determining if
     * a model is deleted)
     *
     * @return bool
     */
    public function isDeleted()
    {
        return ($this->getDeleted() instanceof \Alaress_Date_Format && !$this->getDeleted()->isNull());
    }


}
