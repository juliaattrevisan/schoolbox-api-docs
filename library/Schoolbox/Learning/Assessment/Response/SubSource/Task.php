<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Assessment\Response\SubSource;

use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Response\IResponseDataSource;
use Schoolbox\Learning\Assessment\Task\Model as TaskObject;

/**
 * Sub-source for task responses.
 */
class Task implements IResponseDataSource
{
    /**
     * @inheritdoc
     */
    public function recognizes(IAssessable $assessment)
    {
        return $assessment instanceof TaskObject;
    }

    /**
     * @inheritdoc
     */
    public function findForAssessment(IAssessable $assessment)
    {
    }
}
