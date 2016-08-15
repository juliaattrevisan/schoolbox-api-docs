<?php
/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
namespace Schoolbox\Learning\Feedback;

use Alaress\Web\Controller\Base;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Mapper\Exception as MapperException;

class Controller extends Base
{
    /**
     * @var ErrorMapper
     */
    protected $errorMapper;

    /**
     * Controller constructor.
     * @param ErrorMapper $errorMapper
     */
    public function __construct(ErrorMapper $errorMapper)
    {
        $this->errorMapper = $errorMapper;
    }


    /**
     * Displays feedback for the specified assessment and student
     *
     * @api   GET /learning/assessment/:id/feedback/:studentId
     * @level 165
     */
    public function getAssessmentFeedback()
    {
        // TODO - generate data

        $this->getResponse()->content->set($this->buildHtmlOutput('@learningFeedback/assessmentFeedback.twig', [ ]));
    }


    /**
     * Displays a single feedback item
     *
     * @api   GET /learning/feedback/:id
     * @level 165
     */
    public function getFeedback()
    {
        // TODO - generate data
        $assessmentsToMark = [];
        $assessmentsNotSubmitted = [];

        $this->getResponse()->content->set($this->buildHtmlOutput('@learning/pendingAssessments.twig', [
            'assessmentsToMark'       => $assessmentsToMark,
            'assessmentsNotSubmitted' => $assessmentsNotSubmitted,
        ]));
    }


    /* Getters/Setters
     */

    /**
     * @return ErrorMapper
     */
    protected function getErrorMapper()
    {
        return $this->errorMapper;
    }

    /**
     * @param ErrorMapper $errorMapper
     * @return static
     */
    protected function setErrorMapper(ErrorMapper $errorMapper)
    {
        $this->errorMapper = $errorMapper;
        return $this;
    }
}
