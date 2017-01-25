<?php
/**
 * Assess_Export_Xml
 *
 * Export an entire quiz (including sections, questions + solutions) into xml format
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

use Schoolbox\Learning\Assessment\DataModel\Mapper as AssessmentMapper;
use Schoolbox\Learning\Assessment\Quiz\Model as QuizAssessable;

class Assess_Export_Xml extends Evaluation_Export_Xml
{
    /** @var AssessmentMapper */
    private $assessmentMapper;

    /**
     * @var QuizAssessable
     */
    private $quizAssessable;

    /**
     * @param array $assess
     * @param Assess_Factory $factory
     * @param Assess_Section $sectionHandler
     * @param Assess_Question $questionHandler
     * @param SitePage $page
     * @param AssessmentMapper $assessmentMapper
     */
    public function __construct(
        array            $assess,
        Assess_Factory   $factory,
        Assess_Section   $sectionHandler,
        Assess_Question  $questionHandler,
        SitePage         $page,
        AssessmentMapper $assessmentMapper
    ) {
        parent::__construct($assess, $factory, $sectionHandler, $questionHandler, $page);
        $this->assessmentMapper = $assessmentMapper;
        $this->quizAssessable = $this->assessmentMapper->findById($assess['assessment_id']);
    }

    /**
     * Renders the quiz-specific data
     *
     * @param mixed[] $assess
     */
    protected function renderEvaluation(array $assess)
    {
        $quiz   = $this->quizAssessable;

        $this->getPage()->setVar([
            'ASSESS_TITLE'             => htmlspecialchars($quiz->getTitle(), ENT_QUOTES),
            'ASSESS_DESCRIPTION'       => Scrub::HTML($quiz->getDescription()),
            'ASSESS_PUBLISH'           => $quiz->getPublishAt()->getDatabase(),
            'ASSESS_OPEN'              => $quiz->getOpenDate()->getDatabase(),
            'ASSESS_DUE'               => $quiz->getDueDate()->getDatabase(),
            'ASSESS_CLOSE'             => $quiz->getCloseAt()->getDatabase(),
            'ASSESS_WEIGHT'            => $quiz->getWeight(),
            'ASSESS_DURATION'          => $quiz->getDuration(),
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
