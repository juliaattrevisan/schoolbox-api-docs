<?php
/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
namespace Schoolbox\Learning\Feedback;

use Alaress\Web\Controller\Base;
use Crocodoc\Renderer\Strategy\Crocodoc;
use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\Assessment\Component\SubmissionBoxMapper;
use Schoolbox\Assessment\Term\Mapper as TermMapper;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\User;
use Schoolbox\Assessment\Component\SubmissionBox\Rubric\RubricMapper;

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
     * Displays feedback for the specified assessment and student
     *
     * @api   GET /learning/assessment/:id/feedback/:studentId
     * @param IAssessable|int TODO - replace int with IAssessable in Module
     * @param User $student
     * @level 165
     */
    public function getAssessmentFeedback($assessmentId, User $student)
    {
        // TODO - generate data
        // Replaces /submission/inlineGrading/view/:submissionId/:studentId
        //       eg /submission/inlineGrading/view/56/219
        // Replaces /assess/review/:assessId/:sectionId/:attemptId
        //       eg /assess/review/9/8/7


        // submission box object
        // student object
        // submission returns[]
        // plagscan (summon a plag scan doc) search for 'plagscan_document' attached to a student submission
        // needs to handle the inline grader (is an iFrame) needs to determine the URL for the iFrame
        // runs the submission through a chain, and determines how to render it,
        // the current code gets passed a student submission, and returns an 'inline-grade' object
        // also needs to handle rubrics (rubrics is a meta object)
        // just pass submission_collection through to twig
        // just launch rubrics builder, it will return rubric (would prefer that twig interrogates and handles output, rather than simple string being inserted)

        $twigData = [
            'breadcrumbs' => self::getDummyBreadcrumbs(),
            'assessment'  => \Schoolbox\Learning\Assessment\Controller::getDummyAssessment($assessmentId),
            'student'     => $student,
        ];

        // Due Work
        if(in_array($assessmentId, [2])){
            // Inline-grader / Crocodoc is intimately bound to SitePage-style rendering.
            // Needs to be objectified and passed to twig, and have twig interrogate it.
            $submissionFile = SubmissionBox\Submission\FileMapper::getInstance()->findById(18);
            $crocodoc = new Crocodoc(
                $this->di->get('sitePage'),
                $submissionFile,
                null,
                [],
                TermMapper::getInstance()->findById(8)
            );
            $crocodoc->render();
            $twigData['crocodoc'] = $crocodoc;
        }

        // Project || Due Work
        if(in_array($assessmentId, [1,2])){
            // Rubric
            $submissionBox = SubmissionBoxMapper::getInstance()->findById(56);
            $twigData['rubric'] = RubricMapper::getInstance()->findBySubmissionBoxAndStudent($submissionBox, $student)->toContext();
        }

        $this->getResponse()->content->set($this->buildHtmlOutput('@learningFeedback/assessmentFeedback.twig', $twigData));
    }


    /**
     * Displays crocodoc/inline grading iframe, may also download as PDF
     *
     * @api   GET /learning/feedback/:id
     * @param IFeedback|int $feedback  TODO ensure param is IFeedback using Module
     * @level 165
     */
    public function getFeedback($feedback)
    {
        // TODO - generate data
        // Inline-grader / Crocodoc is intimately bound to SitePage-style rendering.
        // Needs to be objectified and passed to twig, and have twig interrogate it.
        $submissionFile = SubmissionBox\Submission\FileMapper::getInstance()->findById(18);
        $crocodoc = new Crocodoc(
            $this->di->get('sitePage'),
            $submissionFile,
            null,
            [],
            TermMapper::getInstance()->findById(8)
        );
        $twigData = [
            'breadcrumbs' => self::getDummyBreadcrumbs(),
            'crocodoc'    => $crocodoc,
        ];

        $this->getResponse()->content->set($this->buildHtmlOutput('@learning/pendingAssessments.twig', $twigData));
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

    // ================ DUMMY DATA GENERATION ===============================================

    /**
     * Temp method to generate dummy breadcrumbs
     *
     * @todo remove this method when no longer required
     * @return array
     */
    public static function getDummyBreadcrumbs(){
        return [
            [
                'href'  => '/learning/assessment/1/feedback/230',
                'label' => 'FirstCrumb',
            ],
            [
                'href'  => '/learning/assessment/2/feedback/230',
                'label' => 'SecondCrumb',
            ],
            [
                'href'  => '/learning/assessment/3/feedback/230',
                'label' => 'ActiveCrumb',
            ],
        ];
    }
}
