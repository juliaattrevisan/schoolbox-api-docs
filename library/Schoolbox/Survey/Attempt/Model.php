<?php
/**
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Survey\Attempt;

use Schoolbox\ISoftDeletable;
use Schoolbox\Mapper\Traits\Model\Id;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use Schoolbox\Survey\Model as Survey;

class Model implements ISoftDeletable
{
    use Id;
    use DeletedAtDate;

    /** @var Survey $survey */
    protected $survey;

    /**
     * @param Survey $survey
     * @return self
     */
    public function setSurvey($survey)
    {
        $this->survey = $survey;
        return $this;
    }

    /**
     * @return Survey
     */
    public function getSurvey()
    {
        return $this->survey;
    }
}