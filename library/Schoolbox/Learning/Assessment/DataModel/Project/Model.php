<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
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
     * @param Collection|GenericCollection|Assessment\Model[] $assessments
     * @return $this
     */
    public function setAssessments(Collection $assessments): self
    {
        $assessments->assertCollectionOf(Assessment\Model::class);
        $this->assessments = $assessments;
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
