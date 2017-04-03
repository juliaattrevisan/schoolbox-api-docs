<?php
/**
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2017, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Survey\Component\Eportfolio;

use Component_Eportfolio_ControllerAbstract;

use Schoolbox\Survey\Attempt\Mapper as SurveyAttemptMapper;
use Schoolbox\Survey\Attempt\Model as Attempt;
use Schoolbox\Survey\Mapper as SurveyMapper;
use Schoolbox\Survey\Model as Survey;
use Schoolbox\Survey\Question\Mapper as SurveyQuestionMapper;
use Schoolbox\Survey\Response\Mapper as SurveyResponseMapper;
use Schoolbox\Survey\Response\Model as Response;
use Schoolbox\Survey\Section\Mapper as SurveySectionMapper;
use Schoolbox\Survey\Section\Model as Section;

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

            /** @var Survey $survey */
            $survey = SurveyMapper::getInstance()->findById($surveyId);

            // Retrieve all attempts for the survey

            $survey->setAttempts(SurveyAttemptMapper::getInstance()->findByColumnList([
                'user_id' => $studentId,
                'assess_id' => $surveyId
            ], ['start' => 'desc']
            ));

            // Retrieve the responses for each of these attempts

            /** @var Attempt $attempt */
            foreach ($survey->getAttempts() as $attempt) {
                $attempt->setResponses(SurveyResponseMapper::getInstance()->findByColumn('attempt_id', $attempt->getId()));
            }

            // Retrieve all of the surveys sections in sequence order

            $survey->setSections(SurveySectionMapper::getInstance()->findByColumn('assess_id', $survey->getId(), [
                'order' => [
                    'sequence' => 'ASC'
                ]
            ]));

            // For each section get its questions in sequence order

            /** @var Section $section */
            foreach ($survey->getSections() as $section) {
                $section->setQuestions(SurveyQuestionMapper::getInstance()->findByColumn('section_id', $section->getId(), [
                    'order' => [
                        'sequence' => 'ASC'
                    ]
                ]));
            }

            new \Alaress_Probe($survey);

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