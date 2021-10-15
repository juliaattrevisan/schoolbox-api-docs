<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\Feedback\SubSource;

use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Lti\Model as LtiAssessment;
use Schoolbox\Learning\Assessment\Lti\Feedback\MapperProxy;

class Lti extends AbstractSubSource
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
        return $assessment instanceof LtiAssessment;
    }
}
