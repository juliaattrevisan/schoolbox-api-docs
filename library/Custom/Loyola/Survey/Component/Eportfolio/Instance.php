<?php
/**
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2017, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Custom\Loyola\Survey\Component\Eportfolio;

use Component_Instance;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Survey\Mapper as SurveyMapper;
use Schoolbox\Survey\Model as Survey;
use Schoolbox\User;

class Instance extends Component_Instance
{
    /** @var SurveyMapper $surveyMapper */
    protected $surveyMapper;

    /** @var Survey $survey  */
    protected $survey;

    /**
     * Hide the component from students palettes if the survey has not been configured
     *
     * @return bool
     */
    public function renderInPalette()
    {
        /** @var User $user */
        $user = $_SESSION["user"];

        // Try to load the survey we are going to render

        $this->surveyMapper = SurveyMapper::getInstance();

        try {
            $this->survey = $this->surveyMapper->findById($this->getConfigByKey('survey')->getValue());
        } catch (MapperException $e) {
            // The survey was not found (or no survey was set in the configuration)
            if (!$this->survey instanceof Survey && $user->getRole()->isStudent()) {
                // The logged in user is a student, they can't do anything meaningful with the component so hide it
                return false;
            }
        }

        return true;
    }
}