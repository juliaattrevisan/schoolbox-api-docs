<?php
/**
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2017, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Survey\Component\Eportfolio;

use Component_Eportfolio_ControllerAbstract;

use Schoolbox\Survey\Attempt\Mapper as SurveyAttemptMapper;
use Schoolbox\Survey\Mapper as SurveyMapper;

class Controller extends Component_Eportfolio_ControllerAbstract
{
    /**
     * @var mixed
     */
    protected $source;

    /**
     * Loads the grid component into its set configuration.
     *
     * @return true
     */
    public function execute()
    {
        if (is_numeric($this->getConfig('src'))) {
        }

        return true;
    }

    /**
     * @param String $templateHandle
     * @return bool
     */
    public function renderTemplate($templateHandle)
    {
        // Get the ID of the student whose ePortfolio we are viewing

        // Retrieve the survey ID from the component configuration

        $surveyId = $this->getConfig('survey');

        $studentId = $this->getContext()->getUserId();


        if (!empty($surveyId)) {

            // Retrieve the survey

            $survey = SurveyMapper::getInstance()->findById($surveyId);

            var_dump($survey);

            // Get the most recent attempt

            $attempt = SurveyAttemptMapper::getInstance()->findOneByColumnList([
                    'user_id' => $studentId,
                    'assess_id' => $surveyId
                ], ['start' => 'desc']
            );

        } else {
            $survey = null;
            $responses = null;
        }

        return $this->renderTwigTemplate(
            $templateHandle,
            '@surveyEportfolio/component.twig',
            [
                'componentInstanceId'   => $this->getInstance()->getId(),
                'componentClass'        => $this->getComponentName(),
                'config'                => $this->getConfig(),
                'survey'                => $survey
            ]
        );
    }
}