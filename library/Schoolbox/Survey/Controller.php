<?php

/**
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Survey;

use Alaress\Web\Controller\Base;
use Schoolbox\Survey\Mapper as SurveyMapper;

class Controller extends Base
{
    /** @var SurveyMapper */
    private $surveyMapper;

    /**
     * Constructor
     *
     * @param SurveyMapper $surveyMapper
     */
    public function __construct(SurveyMapper $surveyMapper)
    {
        $this->surveyMapper = $surveyMapper;
    }

    /**
     * Perform an AJAX search for surveys
     *
     * @return string
     */
    public function getSelectize()
    {
        return $this->getResponse()->content->set($this->buildJsonOutput(
            $this->surveyMapper->findByKeyword(
                $this->getRequest()->query['query']
            )
        ));
    }
}
