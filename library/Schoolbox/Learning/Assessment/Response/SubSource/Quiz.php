<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Assessment\Response\SubSource;

use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Quiz\Attempt\MapperProxy;
use Schoolbox\Learning\Assessment\Quiz\Model as QuizObject;

/**
 * Sub-source for quiz responses.
 */
class Quiz extends AbstractSubSource
{
    /**
     * @inheritdoc
     */
    protected $studentField = 'user_id';

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
        return $assessment instanceof QuizObject;
    }
}
