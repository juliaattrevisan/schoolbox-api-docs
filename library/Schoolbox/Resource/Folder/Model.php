<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Resource\Folder;


use Schoolbox\Collection;
use Schoolbox\Curriculum\Node;
use Schoolbox\ISoftDeletable;
use Schoolbox\Resource\AbstractFolder;
use Schoolbox\Resource\Folder\Code\Model as FolderCode;
use Schoolbox\Resource\HomepageConfig;
use Schoolbox\User;
use Schoolbox\UserMapper;
use Schoolbox\ValueLabelInterface;

/**
 * Class Model
 *
 * The SBFS Folder Model. Represents a folder within SBFS.
 *
 * @package Schoolbox\Resource\Folder
 */
class Model extends AbstractFolder implements ISoftDeletable, ValueLabelInterface
{
    use Node\Traits\HasCurriculumNodes;

    /**
     * A homepage instance
     *
     * @var \Schoolbox\Resource\HomepageConfig
     */
    protected $homepage;

    /**
     * A collection of FolderCode() instances
     *
     * @var Collection|FolderCode[]
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
     * Get name of category with list of class codes (eg. 'Chemistry (CH123)')
     *
     * @return string
     */
    public function getNameWithCodes()
    {
        $codes = $this->getCodeString();

        if (!empty($codes)) {
            $codes = " ({$codes})";
        }

        return $this->getName() . $codes;
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
     * Get a truncated version of the Folder Code string
     * by getting the first $maxCodes codes and appending
     * an ellipsis to indicate the rest. If there are less
     * than $maxCodes codes associated with the folder, just
     * return all of them
     *
     * @param int $maxCodes
     * @return string
     */
    public function getTruncatedCodeString($maxCodes = 4) {

        //Get first 4 codes, clone cause its a mutable collection
        if ($this->getCodes()->count() > $maxCodes) {
            $subCodeString = array();

            $subCodes = clone $this->getCodes();
            /** @var Collection|Code\Model[] $subCodes */
            $subCodes = $subCodes->slice(0, $maxCodes);

            foreach ($subCodes as $subCode) {
                $subCodeString[] = $subCode->getCode();
            }

            $codeString = implode(',', $subCodeString) . '...';
        }
        else {
            $codeString = $this->getCodeString();
        }

        return $codeString;
    }

    /**
     * Add code. Ensures added code not already present.
     *
     * @param  FolderCode $code
     * @return $this
     */
    public function addCode($code)
    {
        foreach($this->getCodes() as $existing){
            if($existing->getCode() == $code->getCode()){
                return $this;
            }
        }

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

    /**
     * Return a value/label array pair
     *
     * @return array
     */
    public function findValueLabelPair()
    {
        return [
            'value' => $this->getId(),
            'label' => $this->getName()
        ];
    }

    /**
     * Decide whether this node is a Root node
     *
     * @return bool
     */
    public function isRootNode()
    {
        return ($this->getId() == 0);
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
     * @return Collection|FolderCode[]
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
     * Check if the folder is a group
     *
     * @return boolean
     */
    public function isGroup()
    {
        if ($this->getGroup() != null) {

            return $this->getGroup()->getType() != null;
        }

        return false;
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
