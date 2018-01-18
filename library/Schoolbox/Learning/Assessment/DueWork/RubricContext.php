<?php
/**
 * RubricContext
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Learning\Assessment\DueWork;

use Schoolbox\Assessment\Component\SubmissionBox\Rubric\GridGenerator;
use Schoolbox\User;
use Schoolbox\Assessment\Component\SubmissionBox\Rubric\RubricMapper;
use Schoolbox\Learning\Assessment\DueWork\Model as DueWork;
use Schoolbox\Learning\Assessment\DueWork\Feedback\Model as DueWorkFeedback;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;

class RubricContext
{

    /**
     * @var RubricMapper
     */
    private $rubricMapper;

    /**
     * @param RubricMapper $rubricMapper
     */
    public function __construct(RubricMapper $rubricMapper)
    {
        $this->rubricMapper = $rubricMapper;
    }

    /**
     * Get a markable grid for Rubrics marking, for an Instructor and particular IResponse
     *
     * @param Model $dueWork
     * @param User  $student
     * @param int   $responseId
     *
     * @return array
     */
    public function generateMarkableForResponseAndSelfReflection(DueWork $dueWork, User $student, int $responseId)
    {
        if (!$dueWork->getUseRubric()) {
            return ['rubric' => null];
        }

        $context = $this->generateDefaultContext($dueWork);

        /* Spawn rubric grid
         */
        (new GridGenerator($this->rubricMapper, $dueWork->getRubric(), $student))->applyStudentMarkForSelfReflectableResponse(false, $responseId, $student);
        $context['rubric'] = $dueWork->getRubric()->toContext();

        return $context;
    }

    /**
     * Get a markable grid for Rubrics marking, for an Instructor and particular IResponse
     *
     * @param Model $dueWork
     * @param User  $student
     * @param int   $responseId
     *
     * @return array
     */
    public function generateMarkableForResponse(DueWork $dueWork, User $student, int $responseId)
    {
        if (!$dueWork->getUseRubric()) {
            return ['rubric' => null];
        }

        $context = $this->generateDefaultContext($dueWork);

        /* Spawn rubric grid
         */
        (new GridGenerator($this->rubricMapper, $dueWork->getRubric(), $student))->applyStudentMarkForResponse(false, $responseId);
        $context['rubric'] = $dueWork->getRubric()->toContext();

        return $context;
    }

    /**
     * Get a markable grid for Rubrics marking, for an Instructor
     *
     * @param DueWork  $dueWork
     * @param User     $student
     *
     * @return array
     */
    public function generateMarkable(DueWork $dueWork, User $student)
    {
        if (!$dueWork->getUseRubric()) {
            return ['rubric' => null];
        }

        $context = $this->generateDefaultContext($dueWork);

        /* Spawn rubric grid
         */
        (new GridGenerator($this->rubricMapper, $dueWork->getRubric(), $student))->applyStudentMark(false);
        $context['rubric'] = $dueWork->getRubric()->toContext();

        return $context;
    }


    /**
     * Generate default $context array structure
     *
     * @param Model $dueWork
     *
     * @return array
     */
    private function generateDefaultContext(DueWork $dueWork)
    {

        /* Inject available levels for this DueWork's GradeGroup
         */
        $gradeLevels = [];

        foreach ($dueWork->getGradeGroup()->getGradeLevels() as $gradeLevel) {
            $gradeLevels[$gradeLevel->getLowerBound()] = $gradeLevel->getCaption();
        }

        $context = [
            'markType' => [
                'mode' => $dueWork->getMarkType()->getSlug(),
                'conf' => $gradeLevels,
            ],
        ];

        return $context;
    }

    /**
     * Get a readonly grid and the student's current published rubric values
     *
     * @param DueWork $dueWork
     * @param User    $student
     * @param User    $currentUser
     * @param Access  $access
     * @param bool    $publishedOnly
     *
     * @return array
     */
    public function generate(DueWork $dueWork, User $student, User $currentUser, Access $access, $publishedOnly = true)
    {
        return $this->generateReadonlyInternal($dueWork, $student, $currentUser, $access, $publishedOnly);
    }

    /**
     * Get a student agnostic readonly grid
     *
     * @param DueWork $dueWork
     *
     * @return array
     */
    public function generateBasic(DueWork $dueWork, User $currentUser)
    {
        if (!$dueWork->getUseRubric()) {
            return ['rubric' => null];
        }

        $context = [
            'rubric' => $dueWork->getRubric()->toContext($currentUser),
        ];

        // Don't show total mark here at all (it's zero anyway when there is no Participant)
        unset($context['rubric']['total']);

        return $context;
    }

    /**
     * Get a readonly grid for a particular feedback.
     *
     * @param DueWorkFeedback $feedback
     * @param User            $currentUser
     * @param Access          $access
     * @param bool            $publishedOnly
     * @return array
     */
    public function generateForFeedback(DueWorkFeedback $feedback, User $currentUser, Access $access, $publishedOnly = true)
    {
        $dueWork = $feedback->getAssessable();
        if (!$dueWork instanceof DueWork) {
            return ['rubric' => null];
        }
        return $this->generateReadonlyInternal(
            $dueWork,
            $feedback->getReviewee(),
            $currentUser,
            $access,
            $publishedOnly,
            $feedback->getId()
        );
    }

    /**
     * Internal method for generating a readonly rubric
     *
     * @param Model  $dueWork
     * @param User   $student
     * @param User   $currentUser
     * @param Access $access
     * @param bool   $publishedOnly
     * @param null   $feedbackId
     * @return array
     */
    private function generateReadonlyInternal(
        DueWork $dueWork,
        User $student,
        User $currentUser,
        Access $access,
        $publishedOnly = true,
        $feedbackId = null
    ) {
        if (!$dueWork->getUseRubric()) {
            return ['rubric' => null];
        }

        (new GridGenerator($this->rubricMapper, $dueWork->getRubric(), $student))->applyStudentMark($publishedOnly, $feedbackId);

        $context = [
            'rubric' => $dueWork->getRubric()->toContext($currentUser),
        ];

        // Hide total mark from students and parents
        $accessLevel = $access->getFolderAccess($currentUser, $dueWork->getBaseContext()->getFolder()->getId());
        if ($accessLevel < UserAccess::ACCESS_WRITE) {
            unset($context['rubric']['total']);
        }

        return $context;
    }
}
