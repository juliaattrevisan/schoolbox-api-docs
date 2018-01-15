<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Assessment\DataModel\Project;

use Schoolbox\Assessment\Component\SubmissionBox\GradeLevel\Group\Model as GradeGroup;
use Schoolbox\Assessment\Component\SubmissionBox\MarkType;
use Schoolbox\Collection;
use Schoolbox\GenericCollection;
use Schoolbox\ISoftDeletable;
use Schoolbox\Learning\Assessment\DataModel as Assessment;
use Schoolbox\Learning\Assessment\DataModel\AssessmentModelTrait;
use Schoolbox\Learning\Assessment\DataModel\IAssessableDataModel;
use Schoolbox\Learning\Assessment\DataModel\IAssessmentContextDataModel;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use Schoolbox\Mapper\Traits\Model\Id;
use Schoolbox\Resource\Folder\Model as FolderModel;
use Schoolbox\User;

/**
 * Data Model for Projects
 */
class Model implements IAssessableDataModel, IAssessmentContextDataModel, ISoftDeletable
{
    use Id;
    use DeletedAtDate;

    use AssessmentModelTrait;

    /**
     * The average summator type.
     *
     * @var string
     */
    const SUMMATOR_AVERAGE = 'avg';

    /**
     * The minimum summator type.
     *
     * @var string
     */
    const SUMMATOR_MINIMUM = 'min';

    /**
     * The maximum summator type.
     *
     * @var string
     */
    const SUMMATOR_MAXIMUM = 'max';

    /**
     * The weighted average summator type.
     *
     * @var string
     */
    const SUMMATOR_WEIGHTED_AVERAGE = 'weighted';

    /**
     * Owner of the project.
     *
     * @var User
     */
    protected $owner;

    /**
     * The title of the project.
     *
     * @var string
     */
    protected $title;

    /**
     * The long form description of the project.
     *
     * @var string
     */
    protected $description;

    /**
     * The weight of this project in determining class grades.
     *
     * @var int
     */
    protected $weight;

    /**
     * The name of the summator that this uses.
     *
     * @var string
     */
    protected $summator;

    /**
     * The mark type this project uses.
     *
     * @var MarkType
     */
    protected $markType;

    /**
     * The grade group this project uses (only if $this->markType is letter grade)
     *
     * @var GradeGroup
     */
    protected $gradeGroup;

    /**
     * The collection of this project's sub-assessment models.
     *
     * @var Collection|Assessment\Model[]
     */
    protected $assessments;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->assessments = new GenericCollection(Assessment\Model::class);
        $this->markType    = new MarkType();
    }

    /**
     * @return FolderModel
     */
    public function getFolder()
    {
        return $this->getAssessmentModel()->getAssessmentContext()->getFolder();
    }

    /**
     * @param FolderModel $folder
     * @return static
     */
    public function setFolder(FolderModel $folder)
    {
        $this->getAssessmentModel()->getAssessmentContext()->setFolder($folder);
        return $this;
    }

    /**
     * @return string
     */
    public function getSummator()
    {
        return $this->summator;
    }

    /**
     * @param string $summator
     * @return static
     */
    public function setSummator($summator)
    {
        $this->summator = $summator;
        return $this;
    }

    /**
     * @return GenericCollection|Assessment\Model[]
     */
    public function getAssessments()
    {
        return $this->assessments;
    }

    /**
     * @param GenericCollection|Assessment\Model[] $assessments
     * @return static
     */
    public function setAssessments(GenericCollection $assessments)
    {
        $this->assessments->replaceWith($assessments);
        return $this;
    }

    /**
     * @return MarkType
     */
    public function getMarkType()
    {
        return $this->markType;
    }

    /**
     * @param MarkType $markType
     * @return static
     */
    public function setMarkType(MarkType $markType)
    {
        $this->markType = $markType;
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
     * @param string $title
     * @return static
     */
    public function setTitle($title)
    {
        $this->title = $title;
        return $this;
    }

    /**
     * @return string
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * @param string $description
     * @return static
     */
    public function setDescription($description)
    {
        $this->description = $description;
        return $this;
    }

    /**
     * @return int
     */
    public function getWeight()
    {
        return $this->weight;
    }

    /**
     * @param int $weight
     * @return static
     */
    public function setWeight($weight)
    {
        $this->weight = $weight;
        return $this;
    }

    /**
     * @return GradeGroup
     */
    public function getGradeGroup()
    {
        return $this->gradeGroup;
    }

    /**
     * @param GradeGroup $gradeGroup
     * @return static
     */
    public function setGradeGroup(GradeGroup $gradeGroup = null)
    {
        $this->gradeGroup = $gradeGroup;
        return $this;
    }

    /**
     * @return User
     */
    public function getOwner()
    {
        return $this->owner;
    }

    /**
     * @param User $owner
     * @return static
     */
    public function setOwner(User $owner)
    {
        $this->owner = $owner;
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function isSpecificationContext()
    {
        return $this->getAssessmentModel()->getAssessmentContext()->isSpecificationContext();
    }

    /**
     * @inheritdoc
     */
    public function isImplementationContext()
    {
        return $this->getAssessmentModel()->getAssessmentContext()->isImplementationContext();
    }
}
