<?php
/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
namespace Schoolbox\Learning;

use Alaress\Web\Controller\Base;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\User;
use Schoolbox\Assessment\Term\Model as Term;

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
     * Displays a list of Assessments that require marking, and Assessments currently unsubmitted
     *
     * @api   GET /learning
     * @level 165
     */
    public function getPendingAssessments()
    {
        // Is current user not SuperUser or Staff ?
        if (!($this->getUser()->isSuperuser() || $this->getUser()->getRole()->isStaff())) {
            $this->getResponse()->redirect->to($this->getRouter()->generate('learning.getDue', [
                'studentId' => $this->getUser()->getId()
            ]));
        }

        // TODO - generate data
        $assessmentsToMark = [
            Assessment\Controller::getDummyAssessment(2),
        ];
        $assessmentsNotSubmitted = [
            Assessment\Controller::getDummyAssessment(3),
        ];

        $this->getResponse()->content->set($this->buildHtmlOutput('@learning/pendingAssessments.twig', [
            'assessmentsToMark'       => $assessmentsToMark,
            'assessmentsNotSubmitted' => $assessmentsNotSubmitted,
        ]));
    }

    /**
     * Displays a list of marks for students in the specified class
     *
     * @api   GET /learning/markbook/:classId
     * @level 165
     * @param Folder $class
     */
    public function getMarkbook(Folder $class)
    {
        // Replaces /submission/listSubmission/{termSlug}/{folderId}
        //       eg /submission/listSubmission/{termSlug}/{folderId}
        // Replaces /submission/viewSubmission/{dueWorkId}
        //       eg /submission/viewSubmission/56

        // Is current user not SuperUser or Staff ?
        if (!($this->getUser()->isSuperuser() || $this->getUser()->getRole()->isStaff())) {
            $this->getResponse()->redirect->to($this->getRouter()->generate('learning.getDue', [
                'studentId' => $this->getUser()->getId()
            ]));
        }


        // Does this class exist ?
        if (!$class->getId()) {
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_PAGE_NOT_FOUND);
        }

        // TODO - generate data
        $marksheet = [];

        $this->getResponse()->content->set($this->buildHtmlOutput('@learning/marksheet.twig', [
            'class'     => $class,
            'marksheet' => $marksheet,
        ]));
    }

    /**
     * Displays a list assessables that a student needs to complete
     *
     * @api   GET /due/{studentId}
     * @level 165
     * @param User $student
     */
    public function getDue(User $student)
    {
        var_dump($student);die();

        // TODO - generate data
        $assessmentsDue = [
            Assessment\Controller::getDummyAssessment(2),
            Assessment\Controller::getDummyAssessment(3),
            Assessment\Controller::getDummyAssessment(4),
        ];

        $this->getResponse()->content->set($this->buildHtmlOutput('@learning/dueWork.twig', [
            'student'        => $student,
            'assessmentsDue' => $assessmentsDue,
        ]));
    }


    /**
     * Displays a list of grades/results for the specified user and term for all subjects
     *
     * @api   GET /learning/grades/{userId}/{termCode}
     * @level 165
     * @param User $student
     * @param Term $term
     */
    public function getGrades(User $student, Term $term)
    {
        // Replaces /submission/allWork/:studentId/:termSlug
        //       eg /submission/allWork/230/2016-2

        // TODO - generate data
        $grades = [
            [
                'subject'     => 'English 3/4',
                'assessables' => [
                    Assessment\Controller::getDummyAssessment(2),
                ]
            ],
            [
                'subject'     => 'Maths Advanced',
                'assessables' => [
                    Assessment\Controller::getDummyAssessment(3),
                ]
            ],
        ];

        $this->getResponse()->content->set($this->buildHtmlOutput('@learning/grades.twig', [
            'student' => $student,
            'term'    => $term,
            'grades'  => $grades,
        ]));
    }

    /**
     * Displays a list of grades for the specified user, term and subject
     *
     * @api   GET /learning/grades/{userId}/{termCode}/{subjectId}
     * @level 165
     * @param User $student
     * @param Term $term
     * @param Folder $subject
     */
    public function getGradesForSubject(User $student, Term $term, Folder $subject)
    {
        // Replaces /submission/allWork/:studentId/:termSlug/:folderId
        //       eg /submission/allWork/230/2016-2/176

        // TODO - generate data
        $grades = [
            [
                'subject'     => $subject->getName(),
                'assessables' => [
                    Assessment\Controller::getDummyAssessment(2),
                ]
            ],
        ];

        $this->getResponse()->content->set($this->buildHtmlOutput('@learning/grades.twig', [
            'student' => $student,
            'term'    => $term,
            'grades'  => $grades,
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
