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
     * Get a markable grid for Rubrics marking
     *
     * @param IAssessable $assessment
     * @param User $student
     * @return array
     */
    public function generateMarkable(IAssessable $assessment, User $student)
    {
        /** @var DueWork $assessment */
        $markTypeSlug = $assessment->getMarkType()->getSlug();

        RubricMapper::getInstance()->applyStudentMark($assessment->getRubric(), $student);

        $context = [
            'rubric' => $assessment->getRubric()->toContext(),
            'markType' => [
                'mode' => $markTypeSlug
            ]
        ];

        return $context;
    }

    /**
     * Get a readonly grid and the student's current rubric values
     *
     * @param IAssessable $assessment
     * @param User $student
     * @param User $currentUser
     * @return array
     */
    public function generate(IAssessable $assessment, User $student, User $currentUser)
    {
        /** @var DueWork $assessment */
        $markTypeSlug = $assessment->getMarkType()->getSlug();

        RubricMapper::getInstance()->applyStudentMark($assessment->getRubric(), $student);

        /** @var DueWork $assessment */
        $context = [
            'rubric' => $assessment->getRubric()->toContext($currentUser),
            'markType' => [
                'mode' => $markTypeSlug
            ]
        ];

//        // Hide total mark from students and parents
//        if (!($groupHandler->isSuperuser() || $groupHandler->isStaff())) {
//            unset($context['rubric']['total']);
//        }

        return $context;
    }
}