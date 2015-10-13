<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Resource\Folder;


use Schoolbox\RoleMapper;
use Schoolbox\Collection;
use Schoolbox\Curriculum\Node;
use Schoolbox\ISoftDeletable;
use Schoolbox\Resource\AbstractFolder;
use Schoolbox\Resource\CategoryCode;
use Schoolbox\Resource\HomepageConfig;
use Schoolbox\Role;
use Schoolbox\User;
use Schoolbox\UserMapper;

/**
 * Class Model
 *
 * The SBFS Folder Model. Represents a folder within SBFS.
 *
 * @package Schoolbox\Resource\Folder
 */
class Model extends AbstractFolder implements ISoftDeletable
{
    use Node\Traits\HasCurriculumNodes;

    /**
     * A homepage instance
     *
     * @var \Schoolbox\Resource\HomepageConfig
     */
    protected $homepage;

    /**
     * A collection of CategoryCode() instances
     *
     * @var Collection|CategoryCode[]
     */
    protected $codes;

    /**
     * Deleted at
     *
     * @var \Alaress_Date_Format
     */
    protected $deletedAt;

    /**
     * Unmapped field - this contains the number of tasks that are in this
     * category's children.
     *
     * @var int
     */
    protected $taskCount;

    /**
     * A group instance
     *
     * @var \Schoolbox\Resource\Group
     */
    protected $group;

    /**
     * A collection of user instances
     *
     * @var Collection|User[]
     */
    protected $teacher_collection;

    /**
     * Constructor
     *
     */
    public function __construct()
    {
        $this->setCodes(new Collection(array()));
        $this->setCurriculumNodes(new Collection());
    }

    /**
     * Does this category have a homepage?
     *
     * @return bool
     */
    public function hasHomepage()
    {
        return ($this->homepage instanceof HomepageConfig && $this->homepage->getTemplate()->getId());
    }

    /**
     * Get code string
     *
     * Gets your category codes in a , seperated string
     * @return string
     */
    public function getCodeString()
    {
        $codeSet = array();

        if (count($this->codes)) {
            foreach ($this->codes as $code) {
                if (!$code->isDeleted()) {
                    $codeSet[] = $code->getCode();
                }
            }

            return implode(',', $codeSet);
        }

        return '';
    }

    /**
     * Add code
     *
     * @param  CategoryCode $code
     * @return $this
     */
    public function addCode($code)
    {
        $this->getCodes()->add($code);
        return $this;
    }

    /**
     * Add curriculum node
     *
     * @param  Node\Model $node
     * @return $this
     */
    public function addCurriculumNode(Node\Model $node)
    {
        $this->getCurriculumNodes()->add($node);
        return $this;
    }


    /**
     * Populates the teacher collection
     *
     * @param bool $includeSynergy
     */
    public function loadTeachers($includeSynergy = true)
    {
        // populate the teacher collection

        $this->teacher_collection = new Collection();

        if ($includeSynergy) {
            // TODO remove handler dependency
            $DEPRECATED_EXTERNAL_CLASS = new \External_Class();

            $codeCollection = $this->getCodes();

            if ($codeCollection->count()) {
                $externalIdArray = $DEPRECATED_EXTERNAL_CLASS->getTeacherName($codeCollection->first()->getCode());

                if(is_array($externalIdArray)) {
                    foreach ($externalIdArray as $extId) {
                        $this->teacher_collection->merge(UserMapper::getInstance()->findByColumn('synergy_id', $extId));
                    }
                }
            }
        }

        // do we include staff included in this category internally as well? for now, no.
    }

    /**
     * "home" if this is a homepage, "folder" otherwise.
     *
     * {@inheritdoc}
     */
    public function getFolderType()
    {
        // I'd also like to say this is a course
        if ($this->getTaskCount() > 0) {
            return 'course';
        }
        return $this->hasHomepage() ? 'home' : 'folder';
    }

    /**
     * Is deleted
     *
     * @return bool
     */
    public function isDeleted()
    {
        return ($this->getDeletedAt() instanceof \Alaress_Date_Format && !$this->getDeletedAt()->isNull());
    }

    /* Getters/Setters
     */

    /**
     * @param  Collection $codes
     * @return $this
     */
    public function setCodes($codes)
    {
        $this->codes = $codes;
        return $this;
    }

    /**
     * @return Collection|CategoryCode[]
     */
    public function getCodes()
    {
        return $this->codes;
    }

    /**
     * Set homepage
     *
     * @param \Schoolbox\Resource\HomepageConfig $homepage
     * @return $this
     */
    public function setHomepage($homepage)
    {
        $this->homepage = $homepage;
        return $this;
    }

    /**
     * Get homepage
     *
     * @return \Schoolbox\Resource\HomepageConfig
     */
    public function getHomepage()
    {
        return $this->homepage;
    }

    /**
     * Set group
     *
     * @param \Schoolbox\Resource\Group $group
     * @return $this
     */
    public function setGroup($group)
    {
        $this->group = $group;
        return $this;
    }

    /**
     * Get group
     *
     * @return \Schoolbox\Resource\Group
     */
    public function getGroup()
    {
        return $this->group;
    }

    /**
     * Returns the teacher collection
     *
     * @return Collection|User[]
     */
    public function getTeachers()
    {
        return $this->teacher_collection;
    }

    /**
     * Set deleted at
     *
     * @param \Alaress_Date_Format $deletedAt
     */
    public function setDeletedAt($deletedAt)
    {
        $this->deletedAt = $deletedAt;
    }

    /**
     * Get deleted at
     *
     * @return \Alaress_Date_Format
     */
    public function getDeletedAt()
    {
        return $this->deletedAt;
    }

    /**
     * Get task count
     *
     * @return int
     */
    public function getTaskCount()
    {
        return $this->taskCount;
    }

    /**
     * Set task count
     *
     * @param  int $taskCount
     *
     * @return $this
     */
    public function setTaskCount($taskCount)
    {
        $this->taskCount = $taskCount;
        return $this;
    }
}
