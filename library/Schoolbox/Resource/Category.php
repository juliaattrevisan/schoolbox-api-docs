<?php
/**
 * Stub mapper for categories.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */


namespace Schoolbox\Resource;

use Schoolbox\Collection;
use Schoolbox\FolderInterface;
use Schoolbox\User;
use Schoolbox\UserMapper;
use Schoolbox\ISoftDeletable;


class Category implements ISoftDeletable, FolderInterface
{
    /**
     * @var int
     */
    protected $id;

    /**
     * @var string
     */
    protected $name;

    /**
     * @var Category
     */
    protected $parent;

    /**
     * @var \Schoolbox\Resource\HomepageConfig
     */
    protected $homepage;

    /**
     * @var Collection
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
     *
     * @var \Schoolbox\Resource\Group
     */
    protected $group;

    /** @var Collection */
    protected $teacher_collection;

    public function __construct()
    {
        $this->setCodes(new Collection(array()));
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
     * Gets your category codes in a , seperated string
     * @return string
     */
    public function getCodeString()
    {
        if (count($this->codes)) {
            foreach ($this->codes as $code) {
                $codeSet[] = $code->getCode();
            }

            return implode(',', $codeSet);
        }

        return '';
    }

    /**
     * @param  CategoryCode $code
     * @return $this
     */
    public function addCode($code)
    {
        $this->getCodes()->add($code);
        return $this;
    }

    /**
     * @param  int $id
     * @return $this
     */
    public function setId($id)
    {
        $this->id = $id;
        return $this;
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param  string $name
     * @return $this
     */
    public function setName($name)
    {
        $this->name = $name;
        return $this;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param  Category $parent
     * @return $this
     */
    public function setParent($parent)
    {
        $this->parent = $parent;
        return $this;
    }

    /**
     * @return Category
     */
    public function getParent()
    {
        return $this->parent;
    }

    /**
     * @param \Schoolbox\Resource\HomepageConfig $homepage
     * @return $this
     */
    public function setHomepage($homepage)
    {
        $this->homepage = $homepage;
        return $this;
    }

    /**
     * @return \Schoolbox\Resource\HomepageConfig
     */
    public function getHomepage()
    {
        return $this->homepage;
    }

    /**
     * @param \Schoolbox\Resource\Group $group
     * @return $this
     */
    public function setGroup($group)
    {
        $this->group = $group;
        return $this;
    }

    /**
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

                foreach ($externalIdArray as $extId) {
                    $this->teacher_collection->merge(UserMapper::getInstance()->findByColumn('synergy_id', $extId));
                }
            }
        }

        // do we include staff included in this category internally as well? for now, no.

    }

    /**
     * @param \Alaress_Date_Format $deletedAt
     */
    public function setDeletedAt($deletedAt)
    {
        $this->deletedAt = $deletedAt;
    }

    /**
     * @return \Alaress_Date_Format
     */
    public function getDeletedAt()
    {
        return $this->deletedAt;
    }

    /**
     * @return int
     */
    public function getTaskCount()
    {
        return $this->taskCount;
    }

    /**
     * @param  int $taskCount
     *
     * @return $this
     */
    public function setTaskCount($taskCount)
    {
        $this->taskCount = $taskCount;
        return $this;
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

    //
    // TODO implement group type, user access joins
//	protected $user_access;

    /**
     * @return bool
     */
    public function isDeleted()
    {
        return ($this->getDeletedAt() instanceof \Alaress_Date_Format);
    }
}