<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Assessment\Response\SubSource;

use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Quiz\Model as QuizObject;
use Schoolbox\Learning\Assessment\Response\IResponseDataSource;

/**
 * Sub-source for quiz responses.
 */
class Quiz implements IResponseDataSource
{
    /**
     * @inheritdoc
     */
    public function recognizes(IAssessable $assessment)
    {
        return $assessment instanceof QuizObject;
    }

    /**
     * @inheritdoc
     */
    public function findForAssessment(IAssessable $assessment)
    {
    }
}
