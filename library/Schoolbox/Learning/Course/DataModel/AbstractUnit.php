<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Course\DataModel;

use Schoolbox\Collection;
use Schoolbox\GenericCollection;
use Schoolbox\IModel;
use Schoolbox\ISoftDeletable;
use Schoolbox\Learning\Assessment\DataModel\IAssessmentContextDataModel;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\AssessmentContext;
use Schoolbox\Mapper\Traits\Model\CreateCollection;
use Schoolbox\Mapper\Traits\Model\CreatedAtDate;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use Schoolbox\Mapper\Traits\Model\Id;
use Schoolbox\Mapper\Traits\Model\UpdatedAtDate;

/**
 * Common properties + behaviour for UnitSpecs + UnitImpls
 */
abstract class AbstractUnit implements ISoftDeletable, AssessmentContext, IAssessmentContextDataModel, IModel
{
    use Id;
    use CreatedAtDate;
    use UpdatedAtDate;
    use DeletedAtDate;
    use CreateCollection;

    /**
     * The assessables that are a part of this unit
     *
     * @var IAssessable[]|GenericCollection
     */
    private $assessables;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->assessables = new GenericCollection(IAssessable::class);
    }

    /**
     * @return $this
     */
    public function getBaseContext()
    {
        return $this;
    }

    /* Getters / Setters
     */

    /**
     * @return GenericCollection|IAssessable[]
     */
    public function getAssessables()
    {
        return $this->assessables;
    }

    /**
     * @param Collection|IAssessable[] $assessables
     * @return static
     */
    public function setAssessables(Collection $assessables)
    {
        $this->assessables->replaceWith($assessables);
        return $this;
    }

    /**
     * @return AbstractCourse
     */
    abstract public function getCourse();
}
