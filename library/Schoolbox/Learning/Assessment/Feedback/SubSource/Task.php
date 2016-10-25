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
use Schoolbox\Learning\Assessment\Task\Feedback\MapperProxy;
use Schoolbox\Learning\Assessment\Task\Feedback\Model;
use Schoolbox\Learning\Assessment\Task\Model as TaskObject;

/**
 * Sub-source for task responses.
 */
class Task implements IFeedbackDataSource
{
    /**
     * @var MapperProxy
     */
    protected $mapperProxy;

    /**
     * Constructor
     *
     * @param MapperProxy $mapperProxy
     */
    public function __construct(MapperProxy $mapperProxy)
    {
        $this->mapperProxy = $mapperProxy;
    }

    /**
     * @inheritdoc
     */
    public function recognizes(IAssessable $assessment)
    {
        return $assessment instanceof TaskObject;
    }

    /**
     * @param IAssessable $assessment
     * @return Collection|Model[]
     */
    public function findForAssessment(IAssessable $assessment)
    {
        if ($this->recognizes($assessment)) {
            return $this->mapperProxy->findByColumnList(['assessment_id' => $assessment->getId()])
                                     ->map(function ($item) use ($assessment) {
                                         /** @var Model $item*/
                                         return $item->setAssessable($assessment);
                                     });
        }
        return new Collection();
    }
}
