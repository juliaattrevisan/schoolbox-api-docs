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

use Schoolbox\User;
use Schoolbox\Assessment\Component\SubmissionBox\Rubric\RubricMapper;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\DueWork\Model as DueWork;

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
     * Get a markable grid for Rubrics marking
     *
     * @param DueWork $dueWork
     * @param User $student
     * @return array
     */
    public function generateMarkable(DueWork $dueWork, User $student)
    {
        if(!$dueWork->getUseRubric()){
            return ['rubric' => null];
        }

        $markTypeSlug = $dueWork->getMarkType()->getSlug();

        $publishedOnly = false;
        $this->rubricMapper->applyStudentMark($dueWork->getRubric(), $student, $publishedOnly);

        $context = [
            'rubric' => $dueWork->getRubric()->toContext(),
            'markType' => [
                'mode' => $markTypeSlug
            ]
        ];

        /* Inject available levels for this DueWork's GradeGroup
         */
        $gradeLevels = [];

        foreach ($dueWork->getGradeGroup()->getGradeLevels() as $gradeLevel) {
            $gradeLevels[$gradeLevel->getLowerBound()] = $gradeLevel->getCaption();
        }

        $context['markType']['conf'] = $gradeLevels;

        return $context;
    }

    /**
     * Get a readonly grid and the student's current published rubric values
     *
     * @param DueWork $dueWork
     * @param User $student
     * @param User $currentUser
     * @return array
     */
    public function generate(DueWork $dueWork, User $student, User $currentUser)
    {
        if(!$dueWork->getUseRubric()){
            return ['rubric' => null];
        }

        $publishedOnly = true;
        $this->rubricMapper->applyStudentMark($dueWork->getRubric(), $student, $publishedOnly);

        $context = [
            'rubric' => $dueWork->getRubric()->toContext($currentUser),
        ];

        // Hide total mark from students and parents
        if (!($currentUser->isSuperuser() || $currentUser->getRole()->isStaff())) {
            unset($context['rubric']['total']);
        }

        return $context;
    }
}