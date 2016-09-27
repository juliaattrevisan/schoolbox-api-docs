<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Assessment\DataModel\Project;

use Schoolbox\Assessment\Component\SubmissionBox\MarkType;
use Schoolbox\Collection;
use Schoolbox\Learning\Assessment\DataModel\AssessmentModelTrait;
use Schoolbox\Learning\Assessment\DataModel\IAssessableDataModel;
use Schoolbox\Mapper\Traits\Model\Id;

/**
 * Data Model for Projects
 */
class Model implements IAssessableDataModel
{
    use Id;

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
    const SUMMATOR_WEIGHTED_AVERAGE = 'weightedAvg';

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
     * The collection of assessable models.
     *
     * @var Collection|IAssessableDataModel[]
     */
    protected $assessments;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->assessments = new Collection();
        $this->markType    = new MarkType();
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
     * @return Collection|IAssessableDataModel[]
     */
    public function getAssessments()
    {
        return $this->assessments;
    }

    /**
     * @param Collection|IAssessableDataModel[] $assessments
     * @return static
     */
    public function setAssessments(Collection $assessments)
    {
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
}
