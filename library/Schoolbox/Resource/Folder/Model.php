<?php
/**
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Resource\Folder;

use Schoolbox\Collection;
use Schoolbox\Curriculum\Node;
use Schoolbox\GenericCollection;
use Schoolbox\ISoftDeletable;
use Schoolbox\Learning\Assessment\DataModel\IAssessmentContextDataModel;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\AssessmentContext;
use Schoolbox\Learning\Course\DataModel\Specification\Model as CourseSpecification;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Model as UnitSpecification;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use Schoolbox\Resource;
use Schoolbox\Resource\AbstractFolder;
use Schoolbox\Resource\Browser\NodeInterface;
use Schoolbox\Resource\Folder\Code\Model as FolderCode;
use Schoolbox\Resource\HomepageConfig;
use Schoolbox\Resource\HomepageTemplate;
use Schoolbox\Resource\Storage\IStorage;
use Schoolbox\Role;
use Schoolbox\User;
use Schoolbox\UserMapper;
use Schoolbox\ValueLabelInterface;
use Schoolbox\YearLevel;
use TypeError;
use UnexpectedValueException;

/**
 * Class Model
 *
 * The SBFS Folder Model. Represents a folder within SBFS.
 *
 * @package Schoolbox\Resource\Folder
 * @method Model getParent()
 */
class Model extends AbstractFolder implements ISoftDeletable, ValueLabelInterface, AssessmentContext, IAssessmentContextDataModel
{
    use Node\Traits\HasCurriculumNodes;
    use DeletedAtDate;

    /**
     * A homepage instance (if this folder has one)
     *
     * @var null|Resource\HomepageConfig
     */
    private $homepage;

    /**
     * A collection of FolderCode() instances
     *
     * You must index these by folder_code.code to enable efficient de-duplication
     * when building collection (array key lookup vs array value traversal)
     *
     * Null means 'unknown'. Empty collection means this folder has no codes.
     *
     * @var null|Collection|FolderCode[]
     */
    private $codes;

    /**
     * A collection of YearLevels
     *
     * @var null|GenericCollection|YearLevel\Model[]
     */
    private $yearLevels;

    /**
     * Place holder for one of folder codes of this folder, which matches a class code and a campus code from SIS.
     * This is for Mapper::findForCampusAndClass().
     * @var null|string
     */
    private $matchedCode;

    /**
     * A group instance
     *
     * @var null|\Schoolbox\Resource\Group
     */
    protected $group;

    /**
     * A collection of user instances
     *
     * @var Collection|User[]
     */
    protected $teacher_collection;

    /**
     * The Course Specification (if any!) that this folder has.
     *
     * @var CourseSpecification|null
     */
    protected $courseSpecification;

    /**
     * The Unit Specification (if any!) that this folder has.
     *
     * @var UnitSpecification|null
     */
    private $unitSpecification;

    /**
     * @var IAssessable[]|GenericCollection<IAssessable>
     */
    private $assessables;

    /**
     * Does this category have a homepage?
     *
     * @return bool
     */
    public function hasHomepage()
    {
        return ($this->homepage instanceof HomepageConfig && $this->homepage->getTemplate() instanceof HomepageTemplate);
    }

    /**
     * Is this a spec folder?
     *
     * (In other words, does it have a course or unit specification?)
     *
     * @return bool
     */
    public function isSpecFolder(): bool
    {
        return $this->hasCourseSpecification() || $this->hasUnitSpecification();
    }

    /**
     * Does this folder have a course specification?
     *
     * @return bool
     */
    public function hasCourseSpecification()
    {
        return ($this->courseSpecification instanceof CourseSpecification && $this->courseSpecification->getId());
    }

    /**
     * Does this folder have a unit specification?
     *
     * @return bool
     */
    public function hasUnitSpecification()
    {
        return ($this->unitSpecification instanceof UnitSpecification && $this->unitSpecification->getId());
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
     * Returns title of folder, for AssessmentContext interface.
     * @return string
     */
    public function getTitle() {
        return $this->getNameWithCodes();
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

        if (\count($this->codes)) {
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
     * Returns whether this folder is a class by checking for non-deleted folder codes
     *
     * @return bool
     */
    public function isClass()
    {
        if (!is_iterable($this->codes)) {
            return false;
        }
        foreach ($this->codes as $code) {
            if (!$code->isDeleted()) {
                return true;
            }
        }
        return false;
    }


    /**
     * Returns whether this folder's name indicates the folder is a rolled-over class
     *
     * WARNING: This is pretty fragile. If you change a folder's name after roll-over this
     *          will return a false negative.
     *
     * @return bool
     */
    public function wasClass(): bool
    {
        return (bool)preg_match('#\[.+\]$#', $this->name);
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

            $externalIds = [];

            foreach ($codeCollection as $code) {
                $teacherIds = $DEPRECATED_EXTERNAL_CLASS->getTeachers($code->getCode());
                if(is_array($teacherIds)) {
                    $externalIds = array_merge($externalIds, $teacherIds);
                }
            }

            if (count($externalIds) > 0) {
                $externalIds = array_unique($externalIds);
                $this->teacher_collection = UserMapper::getInstance()->findByColumnList([
                    'synergy_id' => $externalIds,
                    'g.group_type' => Role::STAFF
                ]);
            }
        }

        // do we include staff included in this category internally as well? for now, no.
        return $this;
    }

    /**
     * "home" if this is a homepage, "folder" otherwise.
     *
     * {@inheritdoc}
     */
    public function getNodeType(): string
    {
        if($this->hasCourseSpecification()){
            return NodeInterface::TYPE_COURSE;
        }
        if($this->hasUnitSpecification()){
            return NodeInterface::TYPE_UNIT;
        }
        return $this->hasHomepage() ? NodeInterface::TYPE_HOME : NodeInterface::TYPE_FOLDER;
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
            'label' => $this->getTitle()
        ];
    }

    /**
     * If an Assessable is in a folder, then its context is this folder.
     *
     * @return $this
     */
    public function getFolder()
    {
        return $this;
    }

    /**
     * If an Assessable is in a folder, then its context is this folder.
     *
     * Note that this doesn't set anything - it just returns $folder.
     * I have deemed overriding this object's values to be too dangerous and
     * confusing.
     *
     * @param Model $folder
     * @return static This method just returns $folder
     */
    public function setFolder(Model $folder)
    {
        return $folder;
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
     * @param GenericCollection $codes
     * @return $this
     * @throws TypeError
     */
    public function setCodes($codes)
    {
        $codes->assertCollectionOf(Code\Model::class);
        $this->codes = $codes;
        return $this;
    }

    /**
     * @return GenericCollection<FolderCode>|FolderCode[]|null
     */
    public function getCodes()
    {
        return $this->codes;
    }

    /**
     * Set homepage
     *
     * @param null|Resource\HomepageConfig $homepage
     * @return $this
     */
    public function setHomepage(?Resource\HomepageConfig $homepage): self
    {
        $this->homepage = $homepage;
        return $this;
    }

    /**
     * Get homepage
     *
     * @return Resource\HomepageConfig
     */
    public function getHomepage(): ?Resource\HomepageConfig
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
     * @param Collection $teachers
     * @return Model
     * @throws \DomainException
     * @throws UnexpectedValueException
     */
    public function setTeachers(Collection $teachers): self
    {
        if ($this->teacher_collection === null) {
            $this->teacher_collection = new GenericCollection(User::class);
        }
        $this->teacher_collection->replaceWith($teachers);
        return $this;
    }


    /**
     * @return CourseSpecification
     */
    public function getCourseSpecification()
    {
        return $this->courseSpecification;
    }

    /**
     * @param CourseSpecification $courseSpecification
     * @return static
     */
    public function setCourseSpecification(CourseSpecification $courseSpecification = null)
    {
        $this->courseSpecification = $courseSpecification;
        return $this;
    }


    /**
     * @return bool
     */
    public function hasCoverImage()
    {
        return $this->getCoverImage() instanceof IStorage && strlen($this->getCoverImage()->getHash());
    }


    /**
     * @return $this
     */
    public function getBaseContext()
    {
        return $this;
    }

    /**
     * @return UnitSpecification
     */
    public function getUnitSpecification()
    {
        return $this->unitSpecification;
    }

    /**
     * @param UnitSpecification|null $unitSpecification
     * @return static
     */
    public function setUnitSpecification(UnitSpecification $unitSpecification = null)
    {
        $this->unitSpecification = $unitSpecification;
        return $this;
    }

    /**
     * @return IAssessable[]|GenericCollection<IAssessable>
     */
    public function getAssessables()
    {
        return $this->assessables;
    }

    /**
     * @param Collection|GenericCollection|IAssessable[] $assessables
     * @return static
     * @throws TypeError
     */
    public function setAssessables(Collection $assessables)
    {
        $assessables->assertCollectionOf(IAssessable::class);
        $this->assessables = $assessables;
        return $this;
    }

    /**
     * Folders are an implementation context
     *
     * @return false
     */
    public function isSpecificationContext()
    {
        return false;
    }

    /**
     * Folders are an implementation context
     *
     * @return true
     */
    public function isImplementationContext()
    {
        return true;
    }

    /**
     * Sets current search code
     * @param string $code
     */
    public function setMatchedCode(string $code): void
    {
        $this->matchedCode = $code;
    }

    /**
     * Gets current search code
     * @return string
     */
    public function getMatchedCode(): string
    {
        return $this->matchedCode ?? '';
    }

    /**
     * @return GenericCollection|YearLevel\Model[]|null
     */
    public function getYearLevels(): ?GenericCollection
    {
        return $this->yearLevels;
    }

    /**
     * @param GenericCollection|YearLevel\Model[]|null $yearLevels
     * @return $this
     * @throws TypeError
     */
    public function setYearLevels(GenericCollection $yearLevels): self
    {
        $yearLevels->assertCollectionOf(YearLevel\Model::class);
        $this->yearLevels = $yearLevels;
        return $this;
    }
}
