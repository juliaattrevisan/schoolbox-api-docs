<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Assessment\Feedback\SubSource;

use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Task\Feedback\MapperProxy;
use Schoolbox\Learning\Assessment\Task\Model as TaskObject;
use Schoolbox\User;

/**
 * Sub-source for task responses.
 */
class Task extends AbstractSubSource
{
    /**
     * Constructor
     *
     * @param MapperProxy $mapperProxy
     */
    public function __construct(MapperProxy $mapperProxy)
    {
        parent::__construct($mapperProxy);
    }

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
    public function findForStudent(User $student)
    {
        return $this->mapperProxy->findByColumnList(['student_id' => $student->getId()]);
    }
}
