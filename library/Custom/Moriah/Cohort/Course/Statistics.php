<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Custom\Moriah\Cohort\Course;

use Assess\Visibility;
use Schoolbox\Learning\Quiz\Model;
use Schoolbox\Assessment\Component\OnlineAssessment\AttemptMapper;
use Schoolbox\Learning\Quiz\Mapper;
use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\Assessment\Component\SubmissionBox\ReturnedWorkMapper;
use Schoolbox\Assessment\Component\SubmissionBox\Submission;
use Schoolbox\Assessment\Component\SubmissionBoxMapper;
use Schoolbox\Assessment\Course\Task;
use Schoolbox\Assessment\Course\UnitInstance;
use Schoolbox\Assessment\Course\UnitInstanceMapper;
use Schoolbox\Assessment\Term;
use Schoolbox\Collection;
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\User;

/**
 * Course-context port of `\Schoolbox\Assessment\ClassStatistics`.
 *
 * Currently only suited to Moriah's Schoolbox install, but likely to be able
 * to be ported to others once Moriah-specific business logic is removed.
 *
 * @package Custom\Moriah\Cohort\Course
 */
class Statistics
{
    /**
     * @var Folder
     */
    protected $course;

    /**
     * @var array
     */
    protected $workMap = array();

    /**
     * @var Collection|Folder[]
     */
    protected $allFolders;

    /**
     * @var SubmissionBoxMapper
     */
    protected $submissionBoxMapper;

    /**
     * @var Submission\FileMapper
     */
    protected $submissionMapper;

    /**
     * @var ReturnedWorkMapper
     */
    protected $returnMapper;

    /**
     * @var Mapper
     */
    protected $assessMapper;

    /**
     * @var AttemptMapper
     */
    protected $attemptMapper;

    /**
     * Constructor
     *
     * @param Folder     $course      The course or unit home page to get stats for.
     * @param Term\Model $term        The term to limit results to
     * @param User       $student     The user for whom the report is for
     * @param User       $currentUser The user who is viewing the report
     * @param int[]      $workTypes   If not empty, limit to these work types
     *                                (Quizzes are SubmissionBox\WorkType::TYPE_ONLINE_ASSESSMENT)
     */
    public function __construct(
        Folder $course,
        Term\Model $term,
        User $student,
        User $currentUser,
        $workTypes = []
    ) {
        $this->course = $course;

        // compile the due work, assessments, marks returned, et al
        $this->unitInstanceMapper  = UnitInstanceMapper::getInstance();
        $this->submissionBoxMapper = SubmissionBoxMapper::getInstance();
        $this->submissionMapper    = Submission\FileMapper::getInstance();
        $this->returnMapper        = SubmissionBox\ReturnedWorkMapper::getInstance();
        $this->assessMapper        = Mapper::getInstance();
        $this->attemptMapper       = AttemptMapper::getInstance();

        /* Visibility
         * Exclude hidden quizzes from the Assessments cube
         */
        $quizVisibility   = new Visibility();
        $quizVisibilities = $quizVisibility->getVisibilityArray($student, $currentUser);

        // SB-12505 - only items visible to parents are visible
        $quizVisibilities = array_diff(
            $quizVisibilities,
            [SubmissionBox::MARK_VISIBILITY_PRIVATE, SubmissionBox::MARK_VISIBILITY_STUDENT,]
        );

        // Get any classes which use this course.
        // (ever, not just in this date span - assessments could have varying due dates)
        $this->allFolders = $this->unitInstanceMapper->findAllFoldersUsingCourse($course);

        // And add work for all of these folders. (Group by unit.)
        foreach ($this->allFolders as $folder) {
            $allUnits = $this->unitInstanceMapper->findForCategoryId($folder->getId());
            foreach ($allUnits as $unit) {
                $boxIds    = [];
                $assessIds = [];
                foreach ($unit->getTasks() as $task) {
                    $boxIds[]    = $task->getSubmissionBox()->getId();
                    $assessIds[] = $task->getAssessment()->getId();
                }
                $boxIds    = array_unique(array_filter($boxIds));
                $assessIds = array_unique(array_filter($assessIds));

                // retrieve ALL the submission boxes for this unit
                // (dependent on work type)
                $params = [
                    'id'              => $boxIds,
                    'mark_visibility' => $quizVisibilities,
                ];
                if (!empty($workTypes)) {
                    $params['type'] = $workTypes;
                }
                $boxCollection = $this->submissionBoxMapper->findByColumnList($params);

                // I don't think that term can ever be null.
                // If it's found that it can, then I'll have to just add all boxes.
                foreach ($boxCollection as $box) {
                    if ($term->contains($box->getDueDate())) {
                        $this->addWork($box, $unit);
                    }
                }

                // retrieve ALL the online assessments (not surveys) for this category
                if (empty($workTypes) || in_array(SubmissionBox\WorkType::TYPE_ONLINE_ASSESSMENT, $workTypes)) {
                    $assessCollection = $this->assessMapper->findByColumnList(
                        array(
                            'assess_id'       => $assessIds,
                            'availability'    => 'assess',
                            'mark_visibility' => $quizVisibilities
                        )
                    );
                    foreach ($assessCollection as $assess) {
                        if ($term->contains($assess->getFinish())) {
                            $this->addWork($assess, $unit);
                        }
                    }
                }
            }
        }
    }

    /**
     * @var UnitInstanceMapper
     */
    protected $unitInstanceMapper;

    /**
     * Return the category this statistics object is referencing
     *
     * @return Folder
     */
    public function getCourse()
    {
        return $this->course;
    }

    /**
     * Add a dueWork or onlineAssess to the map, accounting for timestamp collisions
     *
     * @param SubmissionBox|Model $work
     * @param UnitInstance                   $unit The unit that this work originated from.
     *
     * @return bool
     */
    protected function addWork($work, UnitInstance $unit)
    {
        if ($work instanceof Model) {
            $marks = $this->attemptMapper->getAllAttempts(
                $work->getId(),
                [
                    'most_recent'  => true,
                    'calc_average' => false,
                ]
            );
            // I only want a single mark per student, not every mark.
            $marks  = array_map('array_pop', $marks);
            $workId = 'oa'.$work->getId();
        } elseif ($work instanceof SubmissionBox) {
            $marks  = $this->getDueWorkMarks($work);
            $workId = 'sb'.$work->getId();
        } else {
            return false;
        }

        $baseTask = null;
        // I need the base task this work belonged to.
        foreach ($unit->getTasks() as $taskInstance) {
            if ($taskInstance->getId() == $work->getTaskInstance()->getId()) {
                $baseTask = $taskInstance->getTask();
                break;
            }
        }
        if (!$baseTask instanceof Task) {
            return false;
        }
        $unitId = $unit->getUnitCategory()->getId();
        $taskId = $baseTask->getId();
        if (!isset($this->workMap[$unitId])) {
            $this->workMap[$unitId] = [
                'unit'  => $unit,
                'tasks' => [],
            ];
        }
        // Use task ID as key instead of stamp
        if (!isset($this->workMap[$unitId]['tasks'][$taskId])) {
            $this->workMap[$unitId]['tasks'][$taskId] = [
                'task'    => $baseTask,
                'work'    => [],
                'average' => null,
            ];
        }
        if (!isset($this->workMap[$unitId]['tasks'][$taskId]['work'][$workId])) {
            $this->workMap[$unitId]['tasks'][$taskId]['work'][$workId] = [
                'object' => $work,
                'marks'  => [],
            ];
        }
        // Marks need to be merged and re-averaged.
        // Need to add a due work level as well
        $this->workMap[$unitId]['tasks'][$taskId]['work'][$workId]['marks'] += $marks;
        $this->workMap[$unitId]['tasks'][$taskId]['average']                 = $this->calculateAverage(
            $this->workMap[$unitId]['tasks'][$taskId]['work']
        );
        return true;
    }

    /**
     * Gets marks for this box only.
     *
     * @param  SubmissionBox $box
     * @return array
     */
    protected function getDueWorkMarks(SubmissionBox $box)
    {
        $marks = array();

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
                $thisReturnDate = $return->getReturned();
                $lastReturnDate = $marks[$userId]['date'];
                if ($thisReturnDate->compare($lastReturnDate) < 0) {
                    continue;
                }
            }

            $marks[$userId] = array(
                'mark'         => $return->getNormalizedMark(),
                'display_mark' => $return->getMark(),
                'comment'      => $return->getComment(),
                'attempts'     => 0,
                'date'         => $return->getReturned(),
            );
        }

        // retrieve submissions and append any user IDs that haven't been marked
        $submissions = $this->submissionMapper->findByColumnList(
            array(
                'box_id' => $box->getId(),
            )
        );

        foreach ($submissions as $submission) {
            $userId = $submission->getOwner()->getId();
            if (!array_key_exists($userId, $marks)) {
                $marks[$userId]['attempts'] = 1;
            } else {
                $marks[$userId]['attempts']++;
            }
        }
        return $marks;
    }

    /**
     * Gets the average mark for this set.
     *
     * @param  array $work
     * @return float
     */
    protected function calculateAverage($work)
    {
        $count = 0;
        $total = 0;
        foreach ($work as $workMarks) {
            foreach ($workMarks['marks'] as $mark) {
                if (isset($mark['mark'])) {
                    $count++;
                    $total += $mark['mark'];
                }
            }
        }
        return $count ? ($total / $count) : null;
    }

    /**
     * Is this task in all the folders that imported this course?
     *
     * @param  array $task
     * @return bool
     */
    protected function isTaskInAllFolders($task)
    {
        $workFolderIds = [];
        foreach ($task['work'] as $work) {
            $object = $work['object'];
            if ($object instanceof SubmissionBox || $object instanceof Model) {
                $workFolderIds[$object->getCategory()->getId()] = true;
            }
        }
        $folderIds = [];
        foreach ($this->allFolders as $folder) {
            $folderIds[$folder->getId()] = true;
        }

        // If there's any folder IDs that aren't in work folder list,
        // there's a missing folder.
        return empty(array_diff_key($folderIds, $workFolderIds));
    }

    /**
     * Extracts the nominated user's marks for the category
     *
     * @param User         $user
     * @param UnitInstance $unit The unit that this work originated from.
     * @param bool         $requiredInAllFolders If true, only get marks from
     *                                           tasks that are in all folders.
     *
     * @return array
     */
    public function extractUserMarks(User $user, UnitInstance $unit, $requiredInAllFolders = false)
    {
        $id     = $user->getId();
        $unitId = $unit->getUnitCategory()->getId();
        if (!isset($this->workMap[$unitId]['tasks'])) {
            return [];
        }
        $userMarks = [];
        foreach ($this->workMap[$unitId]['tasks'] as $taskId => $task) {
            if ($requiredInAllFolders && !$this->isTaskInAllFolders($task)) {
                continue;
            }
            $marks = [
                'attempts' => 0,
            ];
            foreach ($task['work'] as $workId => $work) {
                if (array_key_exists($id, $work['marks'])) {
                    $marks  = $work['marks'][$id];
                    $object = $work['object'];
                    if ($object instanceof SubmissionBox) {
                        $marks['work_id']   = $object->getId();
                        $marks['work_type'] = 'sb';
                    } elseif ($object instanceof Model) {
                        $marks['work_id']   = $object->getId();
                        $marks['work_type'] = 'oa';
                    } else {
                        $marks['work_id']   = '';
                        $marks['work_type'] = '';
                    }
                    break;
                }
            }
            $userMarks[$taskId] = $marks;
        }
        ksort($userMarks);
        return array_values($userMarks);
    }

    /**
     * Get all the assessment titles for the x-axis of the graph.
     *
     * @param UnitInstance $unit The unit that this work originated from.
     * @param bool         $requiredInAllFolders If true, only get marks from
     *                                           tasks that are in all folders.
     *
     * @return array
     */
    public function extractTitles(UnitInstance $unit, $requiredInAllFolders = false)
    {
        $unitId = $unit->getUnitCategory()->getId();
        if (!isset($this->workMap[$unitId]['tasks'])) {
            return [];
        }
        $allData = [];
        foreach ($this->workMap[$unitId]['tasks'] as $taskId => $task) {
            if ($requiredInAllFolders && !$this->isTaskInAllFolders($task)) {
                continue;
            }
            // Use the first object's name?
            $allData[$taskId] = [];
            foreach ($task['work'] as $workId => $work) {
                $object = $work['object'];
                if ($object instanceof SubmissionBox) {
                    $allData[$taskId] = $object->getName();
                } elseif ($object instanceof Model) {
                    $allData[$taskId] = $object->getTitle();
                } else {
                    $allData[$taskId] = '';
                }
                break;
            }
        }
        ksort($allData);
        return array_values($allData);
    }

    /**
     * Get all the students data for box and whisker averages
     *
     * @param UnitInstance $unit The unit that this work originated from.
     * @param bool         $requiredInAllFolders If true, only get marks from
     *                                           tasks that are in all folders.
     *
     * @return array
     */
    public function getAllUsersMarks(UnitInstance $unit, $requiredInAllFolders = false)
    {
        $unitId = $unit->getUnitCategory()->getId();
        if (!isset($this->workMap[$unitId]['tasks'])) {
            return [];
        }
        $allData = [];
        foreach ($this->workMap[$unitId]['tasks'] as $taskId => $task) {
            if ($requiredInAllFolders && !$this->isTaskInAllFolders($task)) {
                continue;
            }
            $allData[$taskId] = [];
            foreach ($task['work'] as $workId => $work) {
                foreach ($work['marks'] as $userId => $currentMark) {
                    if (!isset($currentMark['mark'])) {
                        $value = null;
                    } else {
                        $value = (float)$currentMark['mark'];
                    }
                    $allData[$taskId][] = array(
                        'info'  => null,
                        'uid'   => null,
                        'value' => $value,
                    );
                }
            }
        }
        ksort($allData);
        return array_values($allData);
    }

    /**
     * @return Collection|Folder[]
     */
    public function getAllFolders()
    {
        return $this->allFolders;
    }
}
