<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment\Feedback\SubSource;

use Schoolbox\Learning\Assessment\DialogAgent\Model as DialogAgentObject;
use Schoolbox\Learning\Assessment\DialogAgent\Feedback\MapperProxy;
use Schoolbox\Learning\Assessment\IAssessable;

/**
 * Sub-source for Dialog Agent feedback.
 */
class DialogAgent extends AbstractSubSource
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
        return $assessment instanceof DialogAgentObject;
    }
}
