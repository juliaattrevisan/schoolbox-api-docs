<?php

/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment\Response\SubSource;

use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Task\Response\MapperProxy;
use Schoolbox\Learning\Assessment\Task\Model as TaskObject;

/**
 * Sub-source for task responses.
 */
class Task extends AbstractSubSource
{
    /**
     * @inheritdoc
     */
    protected $studentField = 'student_id';

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
}
