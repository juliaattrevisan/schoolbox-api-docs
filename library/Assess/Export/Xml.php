<?php

/**
 * Assess_Export_Xml
 *
 * Export an entire quiz (including sections, questions + solutions) into xml format
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Schoolbox\Learning\Assessment\DataModel\Mapper as AssessmentMapper;
use Schoolbox\Learning\Assessment\DataModel\Model as QuizAssessment;

class Assess_Export_Xml extends Evaluation_Export_Xml
{
    /** @var AssessmentMapper */
    private $assessmentMapper;

    /** @var QuizAssessment */
    private $quizAssessment;

    /**
     * @param array $assess
     * @param Assess_Factory $factory
     * @param Assess_Section $sectionHandler
     * @param Assess_Question $questionHandler
     * @param SitePage $page
     * @param AssessmentMapper $assessmentMapper
     */
    public function __construct(
        array $assess,
        Assess_Factory $factory,
        Assess_Section $sectionHandler,
        Assess_Question $questionHandler,
        SitePage $page,
        AssessmentMapper $assessmentMapper
    ) {
        parent::__construct($assess, $factory, $sectionHandler, $questionHandler, $page);
        $this->assessmentMapper = $assessmentMapper;
        $this->quizAssessment = $this->assessmentMapper->findById($assess['assessment_id']);
    }

    /**
     * Renders the quiz-specific data
     *
     * @param mixed[] $assess
     */
    protected function renderEvaluation(array $assess)
    {
        $quiz   = $this->quizAssessment;

        $this->getPage()->setVar([
            'ASSESS_TITLE'             => Scrub::text($quiz->getTitle()),
            'ASSESS_WORKTYPE'          => !is_null($quiz->getWorkType()) ? Scrub::text($quiz->getWorkType()->getName()) : "",
            'ASSESS_DESCRIPTION'       => Scrub::html($quiz->getDescription()),
            'ASSESS_PUBLISH'           => $quiz->getPublishDate()->getDatabase(),
            'ASSESS_OPEN'              => $quiz->getOpenDate()->getDatabase(),
            'ASSESS_DUE'               => $quiz->getDueDate()->getDatabase(),
            'ASSESS_CLOSE'             => $quiz->getCloseDate()->getDatabase(),
            'ASSESS_WEIGHT'            => $quiz->getWeight(),
            'ASSESS_DURATION'          => $assess['duration'],
            'ASSESS_MARK_VISIBILITY'   => $assess['mark_visibility'],
            'ASSESS_DEFAULT_OPEN_SPAN' => $quiz->getDefaultOpenSpan(),
        ]);
        parent::renderEvaluation($assess);
    }

    /**
     * @param array $section
     */
    protected function renderSection(array $section)
    {
        $this->getPage()->setVar([
            'SECTION_RANDOM' => $section['random'],
            'SECTION_POOL'   => $section['pool']
        ]);
        parent::renderSection($section);
    }


    protected function getEvaluationType()
    {
        return 'assess';
    }

    protected function getTemplateFile()
    {
        return 'assess/exportToMoodle.xml';
    }
}
