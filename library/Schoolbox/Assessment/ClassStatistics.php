<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Assessment;

use Schoolbox\Learning\Quiz\Model as Quiz;
use Schoolbox\Learning\Quiz\Mapper as QuizMapper;
use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\Assessment\Component\SubmissionBoxMapper;
use Schoolbox\Assessment\Component\SubmissionBox\ReturnedWorkMapper;
use Schoolbox\Assessment\Component\SubmissionBox\Submission;
use Schoolbox\Learning\Quiz\Attempt\Mapper as QuizAttemptMapper;
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\User;

/**
 * ClassStatistics
 *
 * @deprecated AFAICT this is used nowhere
 */
class ClassStatistics
{
    /**
     * @var Folder
     */
    protected $category;

    /**
     * @var array
     */
    protected $workMap = array();

    /**
     * @var SubmissionBoxMapper
     */
    protected $submissionBoxMapper;

    /**
     * @var Submission\SubmissionMapper
     */
    protected $submissionMapper;

    /**
     * @var ReturnedWorkMapper
     */
    protected $returnMapper;

    /**
     * @var QuizMapper
     */
    protected $assessMapper;

    /**
     * @var QuizAttemptMapper
     */
    protected $attemptMapper;

    /**
     * Constructor
     *
     * @param Folder $category
     * @param Term\Model $term
     * @param User $user
     */
    public function __construct($category, Term\Model $term, User $user)
    {
        $this->category = $category;

        // compile the due work, assessments, marks returned, et al
        $this->submissionBoxMapper = new SubmissionBoxMapper();
        $this->submissionMapper = Submission\SubmissionMapper::getInstance();
        $this->returnMapper = new SubmissionBox\ReturnedWorkMapper();

        $this->assessMapper = new QuizMapper();
        $this->attemptMapper = new QuizAttemptMapper();

        /* Visibility
         * Exclude hidden quizzes from the Assessments cube
         */
        $quizVisibility = new \Assess\Visibility();
        //load children for the current user, to confirm they are the parent
        (new \Schoolbox\External\User())->loadChildren([$user]);
        $quizVisibilities = $quizVisibility->getVisibilityArray($user, $_SESSION['user']);

        // retrieve ALL the submission boxes for this category
        $boxCollection = $this->submissionBoxMapper->findByColumnList(
            array(
                'category'        => $this->getCategory()->getId(),
                'task_id'         => null,
                'mark_visibility' => $quizVisibilities
            )
        );

        // I don't think that term can ever be null.
        // If it's found that it can, then I'll have to just add all boxes.
        foreach ($boxCollection as $box) {
            if ($term->contains($box->getAssessmentModel()->getDueDate())) {
                $this->addWork($box);
            }
        }

        // retrieve ALL the online assessments (not surveys) for this category
        $assessCollection = $this->assessMapper->findByColumnList(array(
            'category_id'     => $this->getCategory()->getId(),
            'availability'    => 'assess',
            'mark_visibility' => $quizVisibilities
        ));
        foreach ($assessCollection as $assess) {
            if ($term->contains($assess->getFinish())) {
                $this->addWork($assess);
            }
        }
    }

    /**
     * Return the category this statistics object is referencing
     *
     * @return Folder
     */
    public function getCategory()
    {
        return $this->category;
    }

    /**
     * Add a dueWork or onlineAssess to the map, accounting for timestamp collisions
     *
     * @param SubmissionBox|Quiz $work
     * @return bool
     */
    protected function addWork($work)
    {
        if ($work instanceof Quiz) {
            $stamp = $work->getFinish()->getUnixTimeStamp();
            $marks = $this->attemptMapper->getAllAttempts($work->getId(), array(
                'most_recent' => true,
                'calc_average' => true
            ));
        } elseif ($work instanceof SubmissionBox) {
            $stamp = $work->getAssessmentModel()->getDueDate()->getUnixTimeStamp();
            $marks = $this->getDueWorkMarks($work);
        } else {
            return false;
        }

        // accommodate map collisions by incrementing seconds
        while (array_key_exists($stamp, $this->workMap)) {
            ++$stamp;
        }

        $this->workMap[$stamp] = array(
            'object' => $work,
            'marks' => $marks,
        );
        return true;
    }

    protected function getDueWorkMarks(SubmissionBox $box)
    {
        $marks = array();
        $count = 0;
        $sum = 0;

        // Later, this should be updated to return in most recent order.
        // $this->returnMapper->findByColumn('box_id', $box->getId(), array('returned' => 'desc');
        $returnCollection = $this->returnMapper->findByColumn('box_id', $box->getId());

        // iterate across returns and index by student id
        foreach ($returnCollection as $return) {
            /* @var $return \Schoolbox\Assessment\Component\SubmissionBox\ReturnedWork */
            $userId = $return->getOwner()->getId();

            // Don't use this mark if it's not more recent than an existing mark
            // for this student.
            if (array_key_exists($userId, $marks)) {
                $thisReturnDate = $return->getCreatedAt();
                $lastReturnDate = $marks[$userId]['date'];
                if ($thisReturnDate->compare($lastReturnDate) < 0) {
                    continue;
                }
            }

            $marks[$userId] = array(
                'mark'         => $return->getNormalizedMark(),
                'display_mark' => $return->getHumanReadableMark(),
                'comment'      => $return->getComment(),
                'attempts'     => 0,
                'date'         => $return->getCreatedAt(),
            );
        }

        // Calculate sum and count only after the returned marks are collated.
        foreach ($marks as $mark) {
            if (isset($mark['mark'])) {
                $count++;
                $sum += $mark['mark'];
            }
        }

        // retrieve submissions and append any user IDs that haven't been marked
        $submissions = $this->submissionMapper->findByColumnList(array(
            'box_id' => $box->getId(),
        ));

        foreach ($submissions as $submission) {
            $userId = $submission->getOwner()->getId();
            if (!array_key_exists($userId, $marks)) {
                $marks[$userId]['attempts'] = 1;
            } else {
                $marks[$userId]['attempts']++;
            }
        }

        // calculate and insert an average
        $marks['avg'] = $count ? $sum / $count : null;

        return $marks;
    }

    /**
     * Extracts the nominated user's marks for the category
     *
     * @param User $user
     * @return array
     */
    public function extractUserMarks(User $user)
    {
        $id = $user->getId();
        $works = array();

        foreach ($this->workMap as $stamp => $work) {
            $marks = array_key_exists($id, $work['marks']) ? $work['marks'][$id] : array('attempts' => 0);
            $works[$stamp] = array(
                'object' => $work['object'],
                'marks' => $marks
            );
        }
        ksort($works);
        return array_values($works);
    }

    /**
     * Get all the students data for box and whisker averages
     * @return array
     */
    public function getAllUsersMarks()
    {
        $allData = array();
        foreach ($this->workMap as $dueDate => $current) {
            $box = $current['object'];
            $allMarks = $current['marks'];
            $allData[$dueDate] = array();
            /* Stack results
             */
            foreach ($allMarks as $userId => $currentMark) {
                if ($box instanceof Quiz) {
                    if ($userId !== 'avg') {
                        if (count($currentMark)) {
                            $latestAttempt = array_pop($currentMark);
                            $allData[$dueDate][] = array(
                                'info' => null,
                                'uid' => null,
                                'value' => (float)$latestAttempt['mark']
                            );
                        }
                    }
                } elseif ($box instanceof SubmissionBox) {
                    if (strlen(trim($currentMark['mark']))) {
                        $allData[$dueDate][] = array(
                            'info' => null,
                            'uid' => null,
                            'value' => (float)$currentMark['mark']
                        );
                    }
                }
            }
        }
        ksort($allData);
        return array_values($allData);
    }
}
