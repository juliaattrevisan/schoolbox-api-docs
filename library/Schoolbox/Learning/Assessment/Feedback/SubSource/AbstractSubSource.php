<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Assessment\Feedback\SubSource;

use Schoolbox\Collection;
use Schoolbox\Learning\Assessment\Feedback\IFeedbackDataSource;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\IFeedback;
use Schoolbox\Mapper\AbstractMapperProxy;
use Schoolbox\User;

/**
 * Base class for feedback sub-sources.
 */
abstract class AbstractSubSource implements IFeedbackDataSource
{
    /**
     * @var AbstractMapperProxy
     */
    protected $mapperProxy;

    /**
     * The field of the mapper proxy's table which contains the student ID.
     *
     * @var string
     */
    protected $studentField;

    /**
     * Constructor
     *
     * @param AbstractMapperProxy $mapperProxy
     */
    public function __construct(AbstractMapperProxy $mapperProxy)
    {
        $this->mapperProxy = $mapperProxy;
    }

    /**
     * @param IAssessable $assessment
     * @return Collection|IFeedback[]
     */
    public function findForAssessment(IAssessable $assessment)
    {
        if ($this->recognizes($assessment)) {
            return $this->mapperProxy->findByColumnList(['assessment_id' => $assessment->getId()])
                                     ->map(function ($item) use ($assessment) {
                                         /** @var IFeedback $item */
                                         return $item->setAssessable($assessment);
                                     });
        }
        return new Collection();
    }

    /**
     * @inheritdoc
     */
    public function findForStudent(User $student)
    {
        return $this->mapperProxy->findByColumnList([$this->studentField => $student->getId()]);
    }

    /**
     * @inheritdoc
     */
    public function save(IFeedback $response)
    {
        if ($this->recognizes($response->getAssessable())) {
            $this->mapperProxy->save($response);
        }
    }

    /**
     * @inheritdoc
     */
    public function delete(IFeedback $response)
    {
        if ($this->recognizes($response->getAssessable())) {
            $this->mapperProxy->delete($response);
        }
    }


    /**
     * Creates a new model appropriate to the target assessment
     *
     * @param IAssessable $target
     * @return IFeedback
     */
    public function createModel(IAssessable $target)
    {
        if ($this->recognizes($target)) {
            return $this->mapperProxy->createModel();
        }
        return null;
    }


}
