<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Assessment\Response\SubSource;

use Schoolbox\Collection;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Quiz\Attempt\MapperProxy;
use Schoolbox\Learning\Assessment\Quiz\Attempt\Model;
use Schoolbox\Learning\Assessment\Quiz\Model as QuizObject;
use Schoolbox\Learning\Assessment\Response\IResponseDataSource;

/**
 * Sub-source for quiz responses.
 */
class Quiz implements IResponseDataSource
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
        return $assessment instanceof QuizObject;
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
