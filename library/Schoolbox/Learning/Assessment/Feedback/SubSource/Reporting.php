<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2026, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment\Feedback\SubSource;

use Schoolbox\Learning\Assessment\Reporting\Model as ReportingObject;
use Schoolbox\Learning\Assessment\Reporting\Feedback\MapperProxy;
use Schoolbox\Learning\Assessment\IAssessable;

/**
 * Sub-source for Reporting Activity feedback.
 */
class Reporting extends AbstractSubSource
{
    /**
     * @inheritdoc
     */
    protected $studentField = 'learner_id';


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
        return $assessment instanceof ReportingObject;
    }
}
