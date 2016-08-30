<?php
/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
namespace Schoolbox\Learning\Assessment;

use Alaress\Web\Controller\Base;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\User as User;

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
        $this->setErrorMapper($errorMapper);
    }


    /**
     * Displays information regarding an assessment
     *
     * @api   GET /learning/assessment/:assessmentId/status
     * @level 165
     * @param IAssessable $assessment
     * @todo implement IAssessable interface
     */
    public function getStatus($assessment)
    {
        // TODO - generate data

        $this->getResponse()->content->set($this->buildHtmlOutput('@learningAssessment/status.twig', [
            'assessment' => $assessment,
        ]));
    }


    /**
     * Displays specified student's progress in relation to a specified assessable
     *
     * @api   GET /learning/assessment/:assessmentId/summary/:studentId
     * @level 165
     * @param IAssessable $assessment
     * @param User $student
     * @todo implement IAssessable interface
     */
    public function getSummary($assessment, User $student)
    {
        // TODO - generate data

        $this->getResponse()->content->set($this->buildHtmlOutput('@learningAssessment/summary.twig', [
            'assessment' => $assessment,
            'student'    => $student,
        ]));
    }

    /**
     * Displays a form enabling a student to submit items to an assessment
     *
     * @api   GET /learning/assessment/:assessmentId/submit/:studentId
     * @level 165
     * @param IAssessable $assessment
     * @param User $student
     * @todo implement IAssessable interface
     */
    public function getSubmitForm($assessment, User $student)
    {
        // TODO - generate data

        $this->getResponse()->content->set($this->buildHtmlOutput('@learningAssessment/submitForm.twig', [
            'assessment' => $assessment,
            'student'    => $student,
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
