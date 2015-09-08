<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Assessment;

use Eportfolio\ShowcaseMapper;
use Schoolbox\Assessment\Component\OnlineAssessment;
use Schoolbox\Assessment\Component\OnlineAssessmentMapper;
use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\Assessment\Component\SubmissionBoxMapper;
use Schoolbox\Assessment\StudentReportCard\Comment;
use Schoolbox\Assessment\StudentReportCard\Conversation;
use Schoolbox\Resource\Folder;
use Schoolbox\Role;
use Schoolbox\User;
use Schoolbox\UserMapper;
use Schoolbox\Assessment\StudentReportCard\ContextSelector;

class StudentReportCard
{

    // available permissions
    const CAN_SUBMIT             = 0;
    const CAN_PUBLISH            = 1;
    const CAN_EMAIL_TEACHER      = 2;
    const CAN_VIEW_CLASS_AVERAGE = 3;
    const CAN_VIEW_SHOWCASE      = 4;
    const CAN_VIEW_OVERALL_GRADE = 5;

    // binary? more like ternary!
    const DISABLED  = 0;
    const ENABLED   = 1;
    const MANDATORY = 2;

    // vars that come from user input
    protected $targetUserId;
    protected $targetYear;
    protected $targetSemester;
    protected $targetCategoryId;

    // 'session' vars
    protected $viewingUserId;
    protected $permissionSet = array();
    protected $minimumVisibility = 3;

    // helpful vars
    protected $currentYear;
    protected $currentSemester;

    // populated data
    /**
     * A collection of Folder models
     * 
     * @var \Schoolbox\Collection
     */
    protected $categoriesInSemester = array();

    /**
     * @var SubjectReportCard
     */
    protected $subjectData;

    // mappers
    protected $submissionMapper;
    protected $showcaseMapper;
    protected $assessMapper;

    /**
     * @var Folder\Mapper
     */
    protected $folderMapper;

    // 'andlers
    protected $groupHandler;

    public function __construct($studentUserId, $viewingAs = -1)
    {
        // parse arguments
        $this->viewingUserId = $viewingAs == -1 ? $_SESSION['id'] : $viewingAs;
        $this->targetUserId  = $studentUserId;
        // dependencies
        $this->submissionMapper = new SubmissionBoxMapper();
        $this->showcaseMapper   = new ShowcaseMapper();
        $this->assessMapper     = new OnlineAssessmentMapper();
        $this->groupHandler     = new \Group();
        $this->folderMapper     = new Folder\Mapper();
        // build permissions set
        $this->establishPermissions();
        // setup helper vars
        $this->currentYear     = date('Y');
        $this->currentSemester = date('m') > 6 ? 2 : 1;
    }

    /**
     * Establish permissions that the current combination of viewing-as and target user allows
     */
    protected function establishPermissions()
    {
        // is the class average graph line displayed?
        $displayClassAverage = \Cfg::get('display_grade_average') ? true : false;
        // are parents barred from this page?
        $restrictStudentSubmissions = \Cfg::get('restrict_parent_grades') ? true : false;
        // is the grade average allowed to be shown?
        $showOverallGrade = \Cfg::get('show_overall_grade') ? true : false;
        // if viewing own, all permissions enabled
        if ($this->viewingUserId == $this->targetUserId) {
            $this->permissionSet     = array(true, true, true, $displayClassAverage, true, $showOverallGrade);
            $this->minimumVisibility = SubmissionBox::MARK_VISIBILITY_STUDENT;
        } // if superuser, all permissions enabled
        elseif ($this->groupHandler->isSuperuser($this->viewingUserId)) {
            $this->permissionSet     = array(true, true, true, true, true, true);
            $this->minimumVisibility = SubmissionBox::MARK_VISIBILITY_PRIVATE;
        } // if staff, all READ permissions enabled
        elseif ($this->groupHandler->isStaff($this->viewingUserId)) {
            $this->permissionSet     = array(false, false, true, true, true, true);
            $this->minimumVisibility = SubmissionBox::MARK_VISIBILITY_PRIVATE;
        } // if parent of child, use Cfg::get('restrict_student_submissions') for READ permissions (no WRITE)
        elseif ($this->groupHandler->isParentOfChild($this->targetUserId)) {
            $this->permissionSet     = array(
                false,
                false,
                !$restrictStudentSubmissions,
                $displayClassAverage,
                !$restrictStudentSubmissions,
                (!$restrictStudentSubmissions) && $showOverallGrade
            );
            $this->minimumVisibility = SubmissionBox::MARK_VISIBILITY_PARENT_STUDENT + ($restrictStudentSubmissions ? 1 : 0);
        } // all other cases, no permissions granted
        else {
            $this->permissionSet     = array(false, false, false, false, false, false);
            $this->minimumVisibility = 3; // max visibility is 2, so this will never be seen
        }
    }

    /**
     * Check how a particular permission is set for the viewing user
     *
     * @param $action
     *
     * @return bool
     */
    protected function checkPermission($action)
    {
        if (isset($this->permissionSet[$action]) && $this->permissionSet[$action] === true) {
            return true;
        }
        return false;
    }

    /**
     * Defining the semester will self-populate with the subjects available to this semester
     *
     * @param $year
     * @param $semester
     *
     * @throws \InvalidArgumentException
     */
    public function setSemester($year, $semester)
    {
        if ($year < 2000 || $year > 9999) {
            throw new \InvalidArgumentException("Invalid year '$year'");
        }
        if ($semester < 1 || $semester > 2) {
            throw new \InvalidArgumentException("Invalid semester '$semester'");
        }
        // set class vars
        $this->targetYear     = $year;
        $this->targetSemester = $semester;
        // populate subjects in this semester
        $this->findSubjectsForSemester();
    }

    /**
     * Defining the subject will self-populate with the data available for the subject
     *
     * @param $subjectId
     */
    public function setSubject($subjectId)
    {
        $this->setSubjectWithinSemester($subjectId, 0, 0);
    }

    public function setSubjectWithinSemester($subjectId)
    {
        $this->targetCategoryId = $subjectId;
        // populate data for the subject using the category and user
        $duework_visibilities = array(
            Component\SubmissionBox::MARK_VISIBILITY_PARENT_STUDENT,
        );
        if ($this->groupHandler->isStudent()) {
            $duework_visibilities[] = Component\SubmissionBox::MARK_VISIBILITY_STUDENT;
        } elseif ($this->groupHandler->isStaff() || $this->groupHandler->isSuperuser()) {
            $duework_visibilities[] = Component\SubmissionBox::MARK_VISIBILITY_PRIVATE;
            $duework_visibilities[] = Component\SubmissionBox::MARK_VISIBILITY_STUDENT;
        }
        $this->subjectData = new SubjectReportCard(
            $subjectId,
            $this->targetUserId,
            $this->targetYear,
            $this->targetSemester,
            $duework_visibilities
        );
    }

    /**
     * @param \SitePage $page
     * @param string    $template
     * @param string    $tab
     */
    public function renderPage(\SitePage $page, $template = 'submission/allWork.html', $tab = 'submission')
    {
        // visibility minimum is too high, can't see anything
        if ($this->minimumVisibility > SubmissionBox::MARK_VISIBILITY_PARENT_STUDENT) {
            error(19);
        }
        $page->setFile(TEMPLATE_content, $template);
        $page->setTab($tab);
        // global blocks
        $page->setBlock(TEMPLATE_content, 'subject_container', 'subject_container_block');
        $page->setBlock(TEMPLATE_content, 'no_subjects', 'no_subjects_block');

        // global vars
        /** @var $target User */
        $target = UserMapper::getInstance()->findById($this->targetUserId);
        $page->setVar(
            array(
                'WORK_USER_ID'     => $target->getId(),
                'WORK_USER_NAME'   => $target->formatName(),
                'WORK_USER_EXT_ID' => $target->getExternalId(),
                'WORK_USER_BYLINE' => 'Year' . $target->getYear()
            )
        );
        $this->renderSemesterSelectors($page);

        // only attempt to render a subject's details if a subject has actually been selected
        if ($this->hasSubjectSelected()) {
            $this->renderSubject($page);
        } else {
            $this->renderOverview($page);
        }
    }

    /**
     * Renders the selectors for the semester
     *
     * @param \SitePage $page
     */
    protected function renderSemesterSelectors(\SitePage $page)
    {
        $contextSelector = new ContextSelector("submission/allWork/{$this->targetUserId}");

        // Years is tricksy, because we first need to find out all the years in which the student in question has submitted work
        // also, merging in this current year is a tad unfortunate
        $submitYears  = $this->submissionMapper->getAllYearsByUser($this->targetUserId);
        $earliestYear = empty($submitYears) ? $this->currentYear : min($submitYears);
        $years = range($this->currentYear, $earliestYear);

        $contextSelector->setYears($years);
        $contextSelector->setCategoryFirst(false);

        try {
            $contextSelector->setCategories($this->categoriesInSemester);
        }
        catch (\Exception $e) {
        }

        $contextSelector->render(
            $page, 'CONTEXT_SELECTOR', array(
                'year'     => $this->targetYear,
                'semester' => $this->targetSemester,
                'category' => ''
            )
        );

        /* Additional phplibs not in contextSelector instance
         */
        $page->setVar(array(
            'WORK_YEAR'     => $this->targetYear,
            'WORK_SEMESTER' => $this->targetSemester,
        ));
    }

    protected function renderSubject(\SitePage $page, $components = array())
    {
        if (empty($components)) {
            $components = array('teachers', 'gradeAverage', 'classGraph', 'criteria', 'assessments', 'comments');
        }
        if (!$this->subjectData instanceof SubjectReportCard) {
            throw new \InvalidArgumentException("No subject selected");
        }
        // subject globals
        $page->setVar(
            array(
                'RC_TITLE'      => $this->subjectData->getCategory()->getName(),
                'SUBJ_ID'       => $this->subjectData->getCategory()->getId(),
                'SUBJ_NAME'     => $this->subjectData->getCategory()->getName(),
                'SUBJ_SEMESTER' => "Semester {$this->targetSemester}, {$this->targetYear}",
            )
        );
        // iterate the components and output them if we know how
        foreach ($components as $c) {
            $m = 'render' . ucfirst($c);
            if (method_exists($this, $m)) {
                $this->$m($page);
            }
        }

        $page->parse('subject_container_block', 'subject_container');
        $page->setVar(array(
            'no_subjects_block' => ''
        ));
    }

    protected function renderTeachers(\SitePage $page)
    {
        // output teachers
        $page->setBlock('subject_container', 'subject_teacher', 'subject_teacher_block');
        $teachers = $this->subjectData->getTeachers();
        if (count($teachers)) {
            foreach ($teachers as $teacher) {
                /* @var $teacher \Schoolbox\User */
                $page->setVar(
                    array(
                        'SUBJ_TCH_ID'     => $teacher->getId(),
                        'SUBJ_TCH_EXTID'  => $teacher->getExternalId(),
                        'SUBJ_TCH_NAME'   => $teacher->formatName(),
                        'SUBJ_TCH_MAILTO' => $this->checkPermission(self::CAN_EMAIL_TEACHER) ? $teacher->getEmail() : ''
                    )
                );
                $page->parse('subject_teacher_block', 'subject_teacher', true);
            }
        } else {
            $page->setVar('subject_teacher_block', '');
        }
    }

    protected function renderGradeAverage(\SitePage $page)
    {
        $page->setBlock('subject_container', 'grade_average', 'grade_average_block');
        // if you can't see the grade average...you, well, can't see the grade average
        if (!$this->checkPermission(self::CAN_VIEW_OVERALL_GRADE)) {
            $page->setVar('grade_average_block', '');
            return;
        }
        // output student grade average
        $gradeAvg = $this->subjectData->getGradeAverage();
        if (is_null($gradeAvg['grade'])) {
            $page->setVar(
                array(
                    'SUBJ_GRADEIENT'   => '',
                    'SUBJ_GRADE_AVG'   => 'N/A',
                    'SUBJ_GRADE_TREND' => ''
                )
            );
            $page->parse('grade_average_block', 'grade_average');
            return;
        }
        if ($gradeAvg['trend'] > 0) {
            $trendDir = '&#x25B2;';
        } elseif ($gradeAvg['trend'] < 0) {
            $trendDir = '&#x25BC;';
        } else {
            $trendDir = '';
        }
        $returnHandler = new \SubmissionReturn();

        $page->setVar(
            array(
                'SUBJ_GRADEIENT'   => (isset($gradeAvg['grade']) ? (int)($gradeAvg['grade'] / 10) : ''),
                'SUBJ_GRADE_AVG'   => $returnHandler->percent2letter($gradeAvg['grade']),
                'SUBJ_GRADE_TREND' => $gradeAvg['trend'] != 0
                    ? sprintf("%0.1f%% %s", $gradeAvg['trend'], $trendDir)
                    : ''
            )
        );
        $page->parse('grade_average_block', 'grade_average');
    }

    protected function renderClassGraph(\SitePage $page)
    {
        // Not showing graph? Output dummy data
        if (!\Config::get('display_grade_graph')) {
            $page->setVar(
                array(
                    'GRAPH_IS_DISPLAYED' => 0,
                    'GRAPH_JSON'         => json_encode(array()),
                    'GRAPH_COLOURS'      => json_encode(array()),
                    'GRAPH_XMIN'         => 0,
                    'GRAPH_XMAX'         => 100
                )
            );
            return;
        }
        // Otherwise calculate graph data
        $colours       = array(
            'user'    => '#579DCF',
            'average' => '#E99D12',
//            'trend'   => '#3DB114',
        );
        $averageSeries = array();
        $userSeries    = array();
        $data          = $this->subjectData->getFullClassHistory();
        // split into separate series, and mess around with php's loose typing
        foreach ($data as $stamp => $d) {
            if ($this->canViewMark($d['visibility'])) {
                if (!is_null($d['average_mark'])) {
                    $averageSeries[] = array($stamp * 1000, sprintf("%0.2f", $d['average_mark']) * 1);
                }
                if (!is_null($d['user_mark'])) {
                    $userSeries[] = array($stamp * 1000, sprintf("%0.2f", $d['user_mark']) * 1);
                }
            }
        }
        usort(
            $averageSeries,
            function ($a, $b) {
                return $a[0] < $b[0] ? $a[0] > $b[0] ? -1 : 1 : 0;
            }
        );
        usort(
            $userSeries,
            function ($a, $b) {
                return $a[0] < $b[0] ? $a[0] > $b[0] ? -1 : 1 : 0;
            }
        );
        $graph = array();
        // if permission to see class average, include the data series
        if ($this->checkPermission(self::CAN_VIEW_CLASS_AVERAGE)) {
            $graph[] = array(
                'name' => 'Class',
                'data'  => $averageSeries
            );
        } // otherwise, remove the colour from the map (so the graph doesn't get confused)
        else {
            unset($colours['average']);
        }
        // add the user to the graph second, so it appears on top
        $graph[] = array(
            'name' => 'You',
            'data'  => $userSeries
        );
        // display the graph for the semester
        $start = mktime(0, 0, 0, ($this->targetSemester - 1) * 6 + 1, 0, $this->targetYear) * 1000;
        // add 6 months - 1 day
        $end = $start + ((365 / 2 - 1) * 86400000);
        $page->setVar(
            array(
                'GRAPH_IS_DISPLAYED' => 1,
                'GRAPH_JSON'         => json_encode($graph),
                'GRAPH_COLOURS'      => json_encode(array_values($colours)),
                'GRAPH_XMIN'         => $start,
                'GRAPH_XMAX'         => $end
            )
        );
    }

    protected function renderCriteria(\SitePage $page)
    {
        $page->setBlock('subject_container', 'subject_rubric', 'subject_rubric_block');
        $page->setBlock('subject_rubric', 'subject_rubric_group', 'subject_rubric_group_block');
        $page->setBlock('subject_rubric', 'subject_rubric_submissionbox', 'subject_rubric_submissionbox_block');
        $page->setBlock('subject_rubric_group', 'subject_rubric_criterion', 'subject_rubric_criterion_block');
        $page->setBlock('subject_rubric_criterion', 'subject_rubric_criterion_mark', 'subject_rubric_criterion_mark_block');

        $page->setVar(array(
            'subject_rubric_block' => '',
            'subject_rubric_group_block' => '',
            'subject_rubric_criterion_block' => '',
            'subject_rubric_criterion_mark_block' => '',
        ));

        $submissionBoxMapper = $this->submissionMapper;
        $rubricHandler = new \DueWork_Rubric_Handler();

        $allDueWork = $submissionBoxMapper->getAllDueWork($this->targetUserId, array($this->targetCategoryId), $this->targetYear, $this->targetSemester);

        /* Oh. My. GOD.
         *
         * Get all the submission boxes for this particular year and semester,
         * for this user and category. lol.
         */
        $allSubmissionBoxes = new \Schoolbox\Collection();

        foreach ($allDueWork as $currentDueWork) {

            // Check visibility
            if (!$this->canViewMark($currentDueWork['mark_visibility'])) {
                continue;
            }

            /* Submission box info
             */
            $box = new \Schoolbox\Assessment\Component\SubmissionBox();
            $box->setId($currentDueWork['bid']);
            $box->setName($currentDueWork['name']);

            /* Get rubrics output for this return
             */
            $rubrics = $rubricHandler->getStudentMarksWithDescription($this->targetUserId, $box->getId());

            $struct = array(
                'box' => $box,
                'rubrics' => $rubrics
            );

            $allSubmissionBoxes->add($struct);
        }

        /* If enabled
         */
        if (!\Cfg::get('enable_grades_criteria')) {
            return;
        }

        /* Get a list of criteria in this category
        */
        $allCriteria = $this->subjectData->getCriteria();

        // there are criteria, retrieve the criterion-grouped scores for the student, map, and output
        if (count($allCriteria)) {
            $allStructures = array();

            foreach ($allCriteria as $group => $currentCriteria) {
                foreach ($currentCriteria as $currentCriterion) {
                    $allStructures[$group][$currentCriterion->getId()]['criterion'] = $currentCriterion;

                    /* Foreach return, get the result for this criterion
                     */
                    foreach ($allSubmissionBoxes as $currentSubmission) {
                        $allValues = $currentSubmission['rubrics'];

                        $string = $allValues[$currentCriterion->getId()]['description'];

                        /* Do you want to show the rubric int as well?
                         */
//                            if ($allValues[$currentCriterion->getId()]['mark']) {
//                                $string .= " ({$allValues[$currentCriterion->getId()]['mark']})";
//                            }

                        if ($string) {
                            $allStructures[$group][$currentCriterion->getId()]['results'][$currentSubmission['box']->getName()] = array(
                                'id' => $currentSubmission['box']->getId(),
                                'descriptor' => $string
                            );
                        }
                    }

                    if (!count($allStructures[$group][$currentCriterion->getId()]['results'])) {
                        unset($allStructures[$group][$currentCriterion->getId()]);
                    }
                }

                if (!count($allStructures[$group])) {
                    unset($allStructures[$group]);
                }
            }

            /* Render that all out in the land of phpliberty
             */
            foreach ($allStructures as $group => $currentStructure) {
                $page->setVar(array(
                    'SUBJECT_RUBRIC_GROUP_NAME' => $group
                ));

                foreach ($currentStructure as $criterionId => $data) {
                    $page->setVar(array(
                        'SUBJECT_RUBRIC_CRITERION_NAME' => $data['criterion']->getName()
                    ));

                    foreach ($data['results'] as $boxName => $currentResult) {
                        $page->setVar(array(
                            'SUBJECT_RUBRIC_SUBMISSIONBOX_ID'   => $currentResult['id'],
                            'SUBJECT_RUBRIC_SUBMISSIONBOX_NAME' => $boxName,
                            'SUBJECT_RUBRIC_CRITERION_MARK'     => $currentResult['descriptor'],
                        ));

                        $page->parse('subject_rubric_criterion_mark_block', 'subject_rubric_criterion_mark', true);
                    }

                    $page->parse('subject_rubric_criterion_block', 'subject_rubric_criterion', true);

                    /* Clear children after parsing out the parent
                     */
                    $page->setVar(array(
                        'subject_rubric_criterion_mark_block' => ''
                    ));
                }

                $page->parse('subject_rubric_group_block', 'subject_rubric_group', true);

                /* Clear children after parsing out the parent
                 */
                $page->setVar(array(
                    'subject_rubric_criterion_block' => ''
                ));
            }

            $page->parse('subject_rubric_block', 'subject_rubric');
        }
    }

    protected function renderAssessments(\SitePage $page)
    {
        // output assessments
        $page->setBlock('subject_container', 'no_work', 'no_work_block');
        $page->setBlock('subject_container', 'has_work', 'has_work_block');
        $page->setBlock('has_work', 'work', 'work_block');
        $page->setBlock('work', 'work_actionable', 'work_actionable_block');
        $page->setBlock('work', 'work_publishable', 'work_publishable_block');
        $page->setBlock('work', 'work_publishable_label', 'work_publishable_label_block');
        $page->setBlock('work', 'work_clickable', 'work_clickable_block');
        $page->setBlock('work', 'work_unclickable', 'work_unclickable_block');
        $work = $this->subjectData->getAssessments();
        if (count($work)) {
            $userMapper = new \Schoolbox\UserMapper();
            $user       = $userMapper->findById($this->targetUserId);
            foreach ($work as $w) {
                $object = $w['object'];
                $marks  = $w['marks'];
                // clear all template vars
                $page->setVar(
                    array(
                        'ON_OFF'                       => '',
                        'WORK_ID'                      => '',
                        'WORK_TYPE'                    => '',
                        'WORK_DATE'                    => '',
                        'WORK_TIME'                    => '',
                        'WORK_TITLE'                   => '',
                        'WORK_URGENCY'                 => '',
                        'WORK_GRADIENT'                => '',
                        'WORK_GRADE'                   => '',
                        'WORK_COMMENT'                 => '',
                        'WORK_HREF'                    => '',
                        'WORK_BTN_ICON'                => '',
                        'WORK_BTN_LABEL'               => '',
                        'WORK_PUBLISH_ACTION'          => '',
                        'WORK_PUBLISH_BTN_ICON'        => '',
                        'WORK_PUBLISH_BTN_LABEL'       => '',
                        'work_actionable_block'        => '',
                        'work_publishable_block'       => '',
                        'work_publishable_label_block' => '',
                        'work_clickable_block'         => '',
                        'work_unclickable_block'       => '',
                    )
                );
                $existingShowcasedItem = null;
                if ($object instanceof SubmissionBox) {
                    // Don't output to students if it's not published yet?
                    if ($object->getPublishDate()->getUnixTimestamp() > $_SERVER['REQUEST_TIME']) {
                        if (!(
                            $user->isSuperuser()
                            || $user->getRole()->isStaff()
                            || $user->getId() == $object->getOwner()->getId()
                        )) {
                            continue;
                        }
                    }
                    $rowData = $this->outputDueWorkRow($object, $marks);
                    $page->setVar($rowData);
                    /* Hide related buttons if eportfolio is not enabled
                                 */
                    // is the viewing user allowed to [un]publish?
                    if ($user->getEportfolioEnable() && $this->checkPermission(self::CAN_PUBLISH)) {
                        // allowed to perform publish actions
                        // retrieve any publishing of this SB
                        $existingShowcasedItem = $this->showcaseMapper->findByColumnList(
                            array(
                                'uid'               => $this->targetUserId,
                                'submission_box_id' => $object->getId()
                            )
                        );
                        // has this already been published?
                        if (count($existingShowcasedItem)) {
                            // yes: offer unpublish action
                            $output['WORK_PUBLISH_ID']        = $existingShowcasedItem->first()->getId();
                            $output['WORK_PUBLISH_TYPE']      = 'showcaseItemId';
                            $output['WORK_PUBLISH_ACTION']    = 'unpublish';
                            $output['WORK_PUBLISH_BTN_ICON']  = 'download';
                            $output['WORK_PUBLISH_BTN_LABEL'] = 'Unpublish';
                            $page->setVar($output);
                            $page->parse('work_publishable_block', 'work_publishable');
                            $page->parse('work_publishable_label_block', 'work_publishable_label');
                        } else {
                            // not published
                            // has a mark been returned?
                            if (array_key_exists('mark', $marks)) {
                                // mark returned: offer publish action
                                $output['WORK_PUBLISH_ID']        = $page->getVar('WORK_ID');
                                $output['WORK_PUBLISH_TYPE']      = 'boxId';
                                $output['WORK_PUBLISH_ACTION']    = 'publish';
                                $output['WORK_PUBLISH_BTN_ICON']  = 'up-arrow';
                                $output['WORK_PUBLISH_BTN_LABEL'] = 'Publish';
                                $page->setVar($output);
                                $page->parse('work_publishable_block', 'work_publishable');
                                $page->parse('work_publishable_label_block', 'work_publishable_label');
                            } else {
                                // no mark returned: do not offer any action
                                $page->setVar('action_publish_block', '');
                            }
                        }
                    } else {
                        // not allowed: do not offer actions
                        $page->setVar('action_publish_block', '');
                    }
                    $page->parse('work_clickable_block', 'work_clickable', true);
                } elseif ($object instanceof OnlineAssessment) {
                    // Quizzes that aren't open shouldn't show here either?
                    $rowData = $this->outputOnlineAssessRow($object, $marks, $page);
                    // OA's cannot be published at the moment, refactor DueWork publish code if they become publishable
                    $page->setVar('action_publish_block', '');
                } else {
                    $page->setVar('work_publishable_block', '');
                    $page->setVar('work_publishable_label_block', '');
                }
                // if work actions are available, render the block
                $page->setVar('work_actionable_block', '');
                foreach ($rowData['buttons'] as $currentButton) {
                    $page->setVar($currentButton);
                    $page->parse('work_actionable_block', 'work_actionable', true);
                }
                $page->parse('work_block', 'work', true);
                /* Clear children after parsing out the parent
                          */
                $page->setVar(
                    array(
                        'work_actionable_block' => ''
                    )
                );
            }

            $page->parse('has_work_block', 'has_work');

            $page->setVar(array(
                'no_work_block' => '',
                'work_publishable_label_block' => '',
            ));
        }
        else {
            $page->setVar(array(
                'has_work_block' => '',
                'work_block' => '',
                'work_publishable_label_block' => '',
            ));

            $page->parse('no_work_block', 'no_work');
        }
    }

    /**
     * @param SubmissionBox $box
     * @param               $marks
     *
     * @return array
     */
    protected function outputDueWorkRow(SubmissionBox $box, $marks)
    {
        $workTypeMapper = new \Schoolbox\Assessment\Component\SubmissionBox\WorkTypeMapper();
        $workType       = $workTypeMapper->findById($box->getWorkType()->getId());
        $urgency        = $box->getGradientIndex();
        $output         = array(
            'WORK_TITLE'   => $box->getName(),
            'WORK_ID'      => $box->getId(),
            'WORK_TYPE'    => $workType->getName(),
            'WORK_DATE'    => $box->getDueDate()->getRelativeTime(),
            'WORK_TIME'    => $box->getDueDate()->getDateOnly(),
            'WORK_URGENCY' => $urgency,
            'WORK_HREF'    => 'submission/submissionHistory/',
            'buttons'      => array()
        );
        $buttonResubmit = array(
            'WORK_BTN_HREF'  => 'submission/submissionHistory/' . $box->getId() . '/' . $this->targetUserId,
            'WORK_BTN_ICON'  => 'up-arrow',
            'WORK_BTN_LABEL' => 'Resubmit'
        );
        $buttonSubmit   = array(
            'WORK_BTN_HREF'  => 'submission/submissionHistory/' . $box->getId() . '/' . $this->targetUserId,
            'WORK_BTN_ICON'  => 'up-arrow',
            'WORK_BTN_LABEL' => 'Submit'
        );
        // marks have been returned
        if ($marks['attempts'] > 0) {
            $output['WORK_URGENCY'] = 'none';
        }
        if ($marks && $this->canViewMark($box)) {
            $output['WORK_GRADIENT'] = (isset($marks['mark']) ? (int)($marks['mark'] / 10) : '');
            $output['WORK_GRADE']    = $marks['display_mark'];
            $output['WORK_COMMENT']  = $marks['comment'];
        }
        return $output;
    }

    /**
     * @param OnlineAssessment $oa
     * @param                  $marks
     * @param \SitePage        $page  The page unto which to parse blocks
     *
     * @return array
     */
    protected function outputOnlineAssessRow(OnlineAssessment $oa, $marks, \SitePage $page)
    {
        $workTypeMapper = new \Schoolbox\Assessment\Component\SubmissionBox\WorkTypeMapper();
        $workType       = $workTypeMapper->findById($oa->getWorkType()->getId());
        // marks are contained inside a single-element array, pull it out of itself
        $marks                     = $marks[0];
        $urgency                   = $oa->getGradientIndex();
        $output                    = array(
            'WORK_TITLE'   => $oa->getTitle(),
            'WORK_ID'      => $marks ? "{$oa->getId()}/{$marks['section_id']}/{$marks['attempt_id']}" : $oa->getId(),
            'WORK_TYPE'    => $workType->getName(),
            'WORK_DATE'    => $oa->getFinish()->getRelativeTime(),
            'WORK_TIME'    => $oa->getFinish()->getDateOnly(),
            'WORK_URGENCY' => $urgency,
            'WORK_HREF'    => 'assess/intro/',
            'buttons'      => array()
        );
        $buttonSeePreviousAttempts = array(
            'WORK_BTN_HREF'  => 'assess/attempts/' . $oa->getId() . '/' . $this->targetUserId,
            'WORK_BTN_ICON'  => 'icon-quiz',
            'WORK_BTN_LABEL' => 'View all attempts'
        );

        if ($marks['attempts'] > 0) {
            $output['WORK_URGENCY'] = 'none';
        }
        if ($marks) {
            $output['WORK_GRADIENT'] = (isset($marks['mark']) ? (int)($marks['mark'] / 10) : '');
            $output['WORK_GRADE']    = $marks['display_mark'];
            $output['WORK_COMMENT']  = $marks['comment'];
        }
        $page->setVar($output);

        $clickable = false;
        // if the viewing user does not have permission to submit, blank out the actions
        if (!$this->checkPermission(self::CAN_SUBMIT)) {
        } // is the OA still open?
        elseif ($oa->getFinish()->gt(\Alaress_Date_Format::now())) {
            // still open
            // has the student attempted?
            if ($marks['attempts'] > 0) {
                $output['buttons'][] = $buttonSeePreviousAttempts;
                // Do I still have attempts left?
                $maxAttempts = $oa->getMaxAttempts();
                if ($maxAttempts == 0) {
                    $clickable = true;
                } elseif ($marks['attempts'] < $maxAttempts) {
                    $clickable = true;
                }
            } else {
                $clickable = true;
            }
        } else {
            // is closed: offer no action
            if ($marks['attempts'] > 0) {
                $output['buttons'][] = $buttonSeePreviousAttempts;
            }
        }
        if ($clickable) {
            $page->parse('work_clickable_block', 'work_clickable', true);
        } else {
            $page->parse('work_unclickable_block', 'work_unclickable', true);
        }
        return $output;
    }

    protected function renderComments(\SitePage $page)
    {
        $page->setBlock('subject_container', 'subject_comments', 'subject_comments_block');
        $page->setBlock('subject_comments', 'comment', 'comment_block');
        $page->setBlock('subject_comments', 'create_comment', 'create_comment_block');
        $page->setBlock('comment', 'comment_modify', 'comment_modify_block');

        if(!\Config::get('grade_comments_enable')) {
            $page->setVar('subject_comments_block', '');
            return;
        }

        // sessionuser var
        $sessionUser  = UserMapper::getInstance()->findById($this->viewingUserId);
        $sessionRole  = $sessionUser->getRole()->getType();
        $conversation = new Conversation($this->getTargetUser(), $this->getTargetCategory());
        // show or hide the create comment form
        if ($conversation->canComment($sessionUser)) {
            $sessionUserOutput = array(
                'ID'     => $sessionUser->getId(),
                'NAME'   => $sessionUser->formatName(),
                'BYLINE' => $sessionUser->getRole()->getName(),
                'EXTID'  => $sessionUser->getExternalId()
            );
            foreach ($sessionUserOutput as $k => $v) {
                $page->setVar('SESSION_USER_' . $k, $v);
            }
            $page->parse('create_comment_block', 'create_comment');
            $visibilities = array(
                'STUDENT' => self::DISABLED,
                'PARENT'  => self::DISABLED
            );
            // determine default visibilities from submission_access config
            switch (\Cfg::get('submission_access')) {
                case SubmissionBox::MARK_VISIBILITY_PARENT_STUDENT:
                    $visibilities['PARENT'] = self::ENABLED;
                    continue;
                case SubmissionBox::MARK_VISIBILITY_STUDENT:
                    $visibilities['STUDENT'] = self::ENABLED;
                    continue;
                case SubmissionBox::MARK_VISIBILITY_PRIVATE:
                    break;
            }
            if ($sessionRole == Role::PARENT) {
                $visibilities['PARENT'] = self::MANDATORY;
            } elseif ($sessionRole == Role::STUDENT) {
                $visibilities['STUDENT'] = self::MANDATORY;
            }
            // render the checkboxes on the form
            foreach ($visibilities as $group => $visible) {
                $attrs = '';
                switch ($visible) {
                    case self::MANDATORY:
                        $attrs .= ' disabled="disabled"'; // fall through
                    case self::ENABLED:
                        $attrs .= ' checked="checked"';
                        break;
                }
                $page->setVar('COMMENT_VIS_' . $group . '_CHECKED', $attrs);
            }
        } // hide the create comment form because you're not allowed to
        else {
            $page->setVar('create_comment_block', '');
        }
        // retrieve the comments visible to the session user
        $comments = $conversation->getVisibleComments($sessionUser);
        // if there are comments, output them
        if ($comments->count()) {
            foreach ($comments as $comment) {
                /** @var Comment $comment */
                $date = $comment->getCreated()->getRelativeTime();

                if ($comment->getModified()->getUnixTimeStamp() > $comment->getCreated()->getUnixTimeStamp()) {
                    $date .= " &ndash; edited";
                }

                $commentData = array(
                    'ID'          => $comment->getId(),
                    'USER_NAME'   => $comment->getCommenter()->formatName(),
                    'USER_BYLINE' => $comment->getCommenter()->getRole()->getName(),
                    'USER_EXTID'  => $comment->getCommenter()->getExternalId(),
                    'USER_ID'     => $comment->getCommenter()->getId(),
                    'DATE'        => $date,
                    'DATETIME'    => $comment->getCreated()->getDatabase(),
                    'BODY'        => $comment->getBody(),
                );
                // these vars determine the state of the little person icons
                if ($comment->isVisibleToStudent()) {
                    $commentData['VIS_STUDENT']       = 'on';
                    $commentData['VIS_STUDENT_TITLE'] = 'Visible to student';
                } else {
                    $commentData['VIS_STUDENT']       = 'off';
                    $commentData['VIS_STUDENT_TITLE'] = 'Hidden from student';
                }
                if ($comment->isVisibleToParent()) {
                    $commentData['VIS_PARENT']       = 'on';
                    $commentData['VIS_PARENT_TITLE'] = 'Visible to parents';
                } else {
                    $commentData['VIS_PARENT']       = 'off';
                    $commentData['VIS_PARENT_TITLE'] = 'Hidden from parents';
                }
                // render all comment vars
                foreach ($commentData as $k => $v) {
                    if (!is_object($v)) {
                        $page->setVar('COMMENT_' . $k, $v);
                    }
                }
                // you can only modify a comment if you made it
                if ($sessionUser->getId() == $comment->getCommenter()->getId()) {
                    $page->parse('comment_modify_block', 'comment_modify');
                } else {
                    $page->setVar('comment_modify_block', '');
                }
                $page->parse('comment_block', 'comment', true);
            }
        } // no comments in this conversation
        else {
            $page->setVar('comment_block', '');
        }
        $page->parse('subject_comments_block', 'subject_comments');
    }

    /**
     * No subject selected, so display an overview of the semester instead
     *
     * @param \SitePage $page
     */
    protected function renderOverview(\SitePage $page)
    {
        $page->setVar('RC_TITLE', "Semester {$this->targetSemester}, {$this->targetYear}");
        // we need to inject content into subject_container_block rather than use its template
        $page->setFile('subjectListTpl', 'submission/allWorkOverview.html');
        $page->setFile('subjectTpl', 'activity/subjectlist.html');
        $page->setBlock('subjectTpl', 'teacher', 'teacher_block');
        $page->setBlock('subjectTpl', 'subject_grade', 'subject_grade_block');
        /**
         * @var $returnMapper  SubmissionBox\ReturnedWorkMapper
         * @var $attemptMapper OnlineAssessment\AttemptMapper
         */
        $returnMapper  = SubmissionBox\ReturnedWorkMapper::getInstance();
        $attemptMapper = OnlineAssessment\AttemptMapper::getInstance();
        $returnHandler = new \SubmissionReturn();
        $groupHandler  = new \Group();
        $synClass      = new \External_Class;
        $userHandler   = new \User();
        $j             = 0;
        $visibilities  = array(
            SubmissionBox::MARK_VISIBILITY_PARENT_STUDENT,
        );
        if ($groupHandler->isStudent()) {
            $visibilities[] = SubmissionBox::MARK_VISIBILITY_STUDENT;
        } elseif ($groupHandler->isStaff() || $groupHandler->isSuperuser()) {
            $visibilities[] = SubmissionBox::MARK_VISIBILITY_PRIVATE;
            $visibilities[] = SubmissionBox::MARK_VISIBILITY_STUDENT;
        }
        foreach ($this->categoriesInSemester as $category) {
            $categoryID   = $category->getId();
            $categoryCode = $category->getCodeString();
            /* yes it actually requires THIS MUCH code to get a teachers name. */
            if (\Cfg::get('external_enable')) {
                $teacherOutput = array();
                $teacherList   = array();
                foreach ($category->getCodes() as $code) {
                    if (is_array($temp = $synClass->getTeacherName($code->getCode()))) {
                        $teacherList = array_merge($teacherList, $temp);
                    }
                }
                if (is_array($teacherList)) {
                    foreach ($teacherList as $teacherSynID) {
                        $teacher = $userHandler->getBySynergy($teacherSynID);
                        if (is_array($teacher)) {
                            $teacherName     = $userHandler->formatName($teacher);
                            $emailObj        = new \Email();
                            $emailAddress    = $emailObj->formatEmailFromArray($teacher);
                            $teacherOutput[] = array(
                                'id'    => $teacher['uid'],
                                'name'  => $teacherName,
                                'email' => $_SESSION['mailhandle'] . $emailAddress,
                                'synid' => $teacherSynID,
                            );
                        }
                    }
                }
            }
            // Ensure that grades are properly ordered by date.
            if (!(\Cfg::get('restrict_parent_grades') && $groupHandler->isParent())) {
                $studentGrades = array_merge(
                    $returnMapper->getStudentMarks(
                        $categoryID,
                        $this->targetUserId,
                        array('visibility' => $visibilities)
                    ),
                    $attemptMapper->getStudentMarks($categoryID, $this->targetUserId)
                );
                usort(
                    $studentGrades,
                    function ($a, $b) {
                        return $a['date'] > $b['date'] ? -1 : 1;
                    }
                );
                $studentGrades = array_map(
                    function ($row) {
                        return $row['mark'];
                    },
                    $studentGrades
                );
            } else {
                $studentGrades = array();
            }
            $gradeAvg    = -1;
            $grade_count = count($studentGrades);
            $show_grades = \Cfg::get('show_overall_grade');
            $trendDir    = '';
            $trendAmt    = '';
            if ($show_grades) {
                $card  = new SubjectReportCard(
                    $categoryID,
                    $this->targetUserId,
                    $this->targetYear,
                    $this->targetSemester,
                    $visibilities
                );
                $grade = $card->getGradeAverage();
                if (isset($grade['grade'])) {
                    $gradeAvg = $grade['grade'];
                    $trendAmt = $grade['trend'];
                    if ($trendAmt > 0) {
                        $trendDir = '&#x25B2;';
                    } elseif ($trendAmt < 0) {
                        $trendDir = '&#x25BC;';
                    }
                    if ($trendAmt != 0) {
                        $trendAmt = sprintf('%0.1f', $trendAmt) . '%';
                    }
                }
            }
            // calculate grade averages and trending
            $output = array(
                'SUBJ_CAT_ID'       => $categoryID,
                'SUBJ_SUBJECT_NAME' => \Scrub::Text($category->getName()),
                'SUBJ_SUBJECT_ICON' => '/images/mime/60x60/man.png',
                'SUBJ_SUBJECT_CODE' => $categoryCode,
                'SUBJ_SUBJECT_URL'  => "/submission/allWork/{$this->targetUserId}/{$this->targetYear}/{$this->targetSemester}/$categoryID",
//                'SUBJ_TOTAL' => $summary[$category['id']]['total'],
                'SUBJ_TREND_DIR'    => $trendDir,
                'SUBJ_TREND_AMT'    => $trendAmt,
                'SUBJ_HAS_GRADE'    => $show_grades ? 'grade' : '',
                'DARK'              => ++$j % 2 ? '' : 'dark'
            );
            $page->setVar($output);
            if ($gradeAvg == -1) {
                $page->setVar('subject_grade_block', '');
            } else {
                // All letters together?
                $page->setVar(
                    array(
                        'SUBJ_GRADE'     => $returnHandler->percent2letter($gradeAvg),
                        'SUBJ_GRADEIANT' => (int)($gradeAvg / 10),
                    )
                );
                $page->parse('subject_grade_block', 'subject_grade');
            }
            if (\Cfg::get('external_enable')) {
                $page->setVar('teacher_block', '');
                if (isset($teacherOutput) && count($teacherOutput)) {
                    foreach ($teacherOutput as $teacher) {
                        $tOutput = array(
                            'SUBJ_TEACHER_ID'           => $teacher['id'],
                            'SUBJ_TEACHER_NAME'         => $teacher['name'],
                            'SUBJ_TEACHER_URL'          => $teacher['email'],
                            'SUBJ_TEACHER_ROLE'         => 'Teacher',
                            'SUBJ_TEACHER_AVATAR'       => "/portrait?userId={$teacher['synid']}&size=square64",
                            'SUBJ_TEACHER_AVATAR_TITLE' => $teacher['name']
                        );
                        $page->setVar($tOutput);
                        $page->parse('teacher_block', 'teacher', true);
                    }
                }
            } else {
                $page->setVar('teacher_block', '');
            }
            // $this->parse('icon_block', 'icon');
            $page->parse('SUBJECT_LIST', 'subjectTpl', true);
        }

        if (count($this->categoriesInSemester)) {
            $page->parse('subject_container_block', 'subjectListTpl');

            $page->setVar(array(
                'no_subjects_block' => ''
            ));
        }
        else {
            $page->setVar(array(
                'subject_container_block' => ''
            ));

            $page->parse('no_subjects_block', 'no_subjects');
        }
    }


    public function hasSubjectSelected()
    {
        return $this->targetCategoryId ? true : false;
    }

    /**
     * Find all the subjects a student is involved with for a given
     * year and semester
     */
    protected function findSubjectsForSemester()
    {
        $categoryList = array();
        /* Subjects involved with currently
            */
        if ($this->targetYear == $this->currentYear && $this->targetSemester == $this->currentSemester) {
            $categoryList = $this->groupHandler->involvedInCategoryList($this->targetUserId, true, READ_ACCESS);
            // filter this list, so that it only contains categories in which contain results
            foreach ($categoryList as $k => $cat) {
                $count = 0;
                // is there any other way to do this? such that count(db_calls) < 2n
                $count += $this->submissionMapper->findByColumnList(
                    array('category' => $cat['id'], 'task_id' => null)
                )->count();
                $count += $this->assessMapper->findByColumnList(
                    array('category_id' => $cat['id'], 'task_id' => null)
                )->count();
                if (!$count) {
                    unset($categoryList[$k]);
                }
            }
        }
        /* Always merge with a list of subjects that the student has submitted to
            * to allow them access to work completed in classes in which they may no
            * longer be participating
            */
        $submittedList = $this->submissionMapper->submittedToCategoryList(
            $this->targetUserId,
            $this->targetYear,
            $this->targetSemester
        );
        /* Combine lists alphabetically
            */
        $categoryList = array_merge($categoryList, $submittedList);
        $this->categoriesInSemester = $this->folderMapper->findByColumn(
            'id',
            array_map(
                function($v) { return $v['id']; },
                $categoryList
            ),
            ['order' => ['name' => 'asc']]
        );
        // Ensure folder codes are populated
        Folder\Code\Mapper::getInstance()->applyCodesToCategories($this->categoriesInSemester);
    }

    /**
     * For sorting arrays of subjects returned by involvedInCategoryList()
     * and submittedToCategoryList()
     *
     * @param $a
     * @param $b
     *
     * @return bool
     */
    private function sort($a, $b)
    {
        return strcasecmp($a['name'], $b['name']);
    }

    /**
     * @param SubmissionBox|int $var
     *
     * @return bool
     */
    protected function canViewMark($var)
    {
        if ($var instanceof SubmissionBox) {
            return $var->getMarkVisibility() >= $this->minimumVisibility;
        } else {
            return $var >= $this->minimumVisibility;
        }
    }

    public function getTargetUser()
    {
        return UserMapper::getInstance()->findById($this->targetUserId);
    }

    public function getTargetCategory()
    {
        return $this->folderMapper->findById($this->targetCategoryId);
    }

    public function getTargetYear()
    {
        return $this->targetYear;
    }

    public function getTargetSemester()
    {
        return $this->targetSemester;
    }
}
