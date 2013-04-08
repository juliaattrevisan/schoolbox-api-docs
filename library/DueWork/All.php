<?php

use \Schoolbox\Assessment\Component\SubmissionBoxMapper;

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class DueWork_All {

    private $page;
    private $user = array();

    private $userHandler;
    private $groupHandler;
	private $submissionHandler;
    private $returnHandler;

    private $assessHandler;
    private $attemptHandler;
    private $responseHandler;
    private $sectionHandler;

    private $currentYear = 0;
    private $currentSemester = 0;

    private $year = 0;
    private $semester = 0;

    private $categories = array();

    protected $submission_mapper;

    public function __construct(SitePage $page, $userID = 0) {

        $this->userHandler = new User();
        $this->groupHandler = new Group();
        $this->submissionHandler = new Submission();
        $this->returnHandler = new SubmissionReturn();

        $this->submission_mapper = new SubmissionBoxMapper();

        $this->assessHandler = new Assess_Main();
        $this->attemptHandler = new Assess_Attempt();
        $this->responseHandler = new Assess_Response();
        $this->sectionHandler = new Assess_Section();

        $this->page = $page;

        if(!$userID) {
            $userID = $_SESSION['id'];
        }
        $this->user = $this->userHandler->getByID($userID);

        // do some permission checking
        if (!isset($this->user['uid'])) {
            error(2);
        }

        if ($this->groupHandler->isParent() && Cfg::get('restrict_student_submissions')) {
            error(19);
        }

        $this->currentYear = date('Y');
        $this->currentSemester = self::getSemester();

    }


    public function output($year = 0, $semester = 0) {

        $this->year = $year;
        $this->semester = $semester;

        $this->setupTemplates();
        $this->parseArchiveSelector();

        $this->prepareCategoryList();

        $dueSuccess = $this->parseDueWork();
        $assessSuccess = $this->parseAssess();

        // both returned nothing, so just output a single 'not found'
        if(!($dueSuccess || $assessSuccess)) {
            $this->page->setVar('subject_block', 'No due work nor online assessments found.');
            $this->page->setVar('allAssess_block', '');
        }
        // output no due work found
        elseif(!$dueSuccess) {
            $this->page->setVar('subject_block', 'No due work found.');
        }
        // output no assessments found
        elseif(!$assessSuccess) {
            $this->page->setVar('allAssess_block', 'No online assessments found.');
        }
    }


    public function setupTemplates() {

        // page tab
        $this->page->setTab('submission');

        // template file
        $this->page->setFile(TEMPLATE_content, 'submission/allDueWork.html');

        // archive selector
        $this->page->setBlock(TEMPLATE_content, 'semester_option', 'semester_option_block');
        $this->page->setBlock(TEMPLATE_content, 'year_option', 'year_option_block');

        // due work
        $this->page->setBlock(TEMPLATE_content, 'subject', 'subject_block');
        $this->page->setBlock('subject', 'work', 'work_block');
        $this->page->setBlock('work', 'download', 'download_block');
        $this->page->setBlock('work', 'status_late', 'status_late_block');
        $this->page->setBlock('work', 'status_closed', 'status_closed_block');
        $this->page->setBlock('work', 'status_due', 'status_due_block');
        $this->page->setBlock('work', 'status_awaiting_review', 'status_awaiting_review_block');
        $this->page->setBlock('work', 'status_marked', 'status_marked_block');

        // online assess
        $this->page->setBlock(TEMPLATE_content, 'allAssess', 'allAssess_block');
        $this->page->setBlock('allAssess', 'attempt', 'attempt_block');
        $this->page->setBlock('attempt', 'viewMarks', 'viewMarks_block');
        $this->page->setBlock('attempt', 'takeTest', 'takeTest_block');
        $this->page->setBlock('attempt', 'unmarked', 'unmarked_block');

        // output user details
        $this->page->setVar(array(
            'WORK_USER_ID'   => $this->user['uid'],
            'WORK_USER_NAME' => $this->user['uid'] == $_SESSION['id'] ? 'My' : $this->userHandler->formatName($this->user)."'s",
        ));
    }


    public function parseArchiveSelector() {
        // Semesters can be hardcoded, no need to look anything up (may change if a school wants Term breakdowns rather than Semester)
        $semesters = array(1, 2);
        foreach($semesters as $sem) {
            $this->page->setVar(array(
                'SEMESTER_SELECTED' => $sem == $this->semester ? ' selected="selected"' : '',
                'SEMESTER_VALUE'    => $sem
            ));
            $this->page->parse('semester_option_block','semester_option', true);
        }

        // Years is trickier, because we first need to find out all the years in which the student in question has submitted work
        // also, merging in thie current year is a tad unfortunate
//        $years = array_unique(array_merge(array($this->currentYear), $this->submissionHandler->getAllYearsByUser($this->user['uid'])));
        $years = array_unique(array_merge(array($this->currentYear), $this->submission_mapper->getAllYearsByUser($this->user['uid'])));
        foreach($years as $y) {
            $this->page->setVar(array(
                'YEAR_SELECTED' => $y == $this->year ? ' selected="selected"' : '',
                'YEAR_VALUE'    => $y
            ));
            $this->page->parse('year_option_block', 'year_option', true);
        }
    }


    public function prepareCategoryList() {
        if($this->year == $this->currentYear && $this->semester == $this->currentSemester) {
            $categoryList = $this->groupHandler->involvedInCategoryList($this->user['uid'], true, READ_ACCESS);
        }
        else {
//            $categoryList = $this->submissionHandler->submittedToCategoryList($this->user['uid'], $this->year, $this->semester);
            $categoryList = $this->submission_mapper->submittedToCategoryList($this->user['uid'], $this->year, $this->semester);
        }

        if (is_array($categoryList)) {
            foreach($categoryList as $category) {
                $this->categories[$category['id']] = $category;
            }
        }
    }


    public function parseDueWork() {
        // Catch if not involved in any categories
        if (!count($this->getCategoryIDList())) {
            return false;
        }

        $allWork = $this->submissionHandler->getAllDueWork($this->user['uid'], $this->getCategoryIDList(), $this->year, $this->semester);
//        var_dump($this->submission_mapper->getAllDueWork($this->user['uid'], $this->getCategoryIDList(), $this->year, $this->semester));

        if (!is_array($allWork) || !count($allWork)) {
            return false;
        }

        $prevSubject = false;
        $first = true;
        $j = 0;

        foreach($allWork as $currentWork) {

            // Start a new subject heading?
            $prevSubject = $currentWork['categoryName'];
            if (($prevSubject != $currentWork['categoryName']) && (!$first)) {
                $this->page->parse('subject_block', 'subject', true);
                $this->page->setVar('work_block', '');
            }

            $dueDate = new Alaress_Date_Format($currentWork['dueDate'], 'U');
            $lateDate = new Alaress_Date_Format($currentWork['lateDate'], 'U');

            //
            // Only show mark and comment if display isn't blocked.
            $this->page->setVar(array(
                'WORK_CATEGORYNAME' => Scrub::Text($currentWork['categoryName']),
                'WORK_DESCRIPTION'  => Scrub::Text($currentWork['name']),
                'WORK_DUEDATE'      => $dueDate->getDateOnly(),
                'WORK_MARK'         => $currentWork['mark'],
                'WORK_COMMENT'      => $currentWork['comment'],
                'WORK_BOX_ID'       => $currentWork['bid'],
                'ROW_COLOR'         => $j % 2 ? 'dark' : ''
            ));

            $this->page->setVar(array(
                'status_due_block'              => '',
                'status_late_block'             => '',
                'status_closed_block'           => '',
                'status_awaiting_review_block'  => '',
                'status_marked_block'           => ''
            ));

            if ($currentWork['submitted'] != null || $currentWork['returned'] != null) {
                if ($currentWork['returned'] != null) {
                    $this->page->parse('status_marked_block', 'status_marked');
                }
                else {
                    $this->page->parse('status_awaiting_review_block', 'status_awaiting_review');
                }
            }
            // If it hasn't been submitted check whether it's overdue,then check if it's closed and display accordingly
            else {
                // Open
                if ($dueDate->getUnixTimestamp() > time() ) {
                    $this->page->parse('status_due_block', 'status_due');
                }
                // Late
                else if (($dueDate->getUnixTimestamp() < time()) && $lateDate->getUnixTimestamp() > time()) {
                    $this->page->parse('status_late_block', 'status_late');
                }
                // Closed
                else if ($lateDate->getUnixTimestamp() < time()) {
                    $this->page->parse('status_closed_block', 'status_closed');
                }
            }

            $this->page->parse('work_block', 'work', true);

            // $prevSubject = $currentWork['categoryName'];
            $first = false;
            $j++;
        }

        $this->page->parse('subject_block', 'subject', true);

        return true;
    }


    public function parseAssess() {
        if(!count($this->getCategoryIDList())) {
            return false;
        }

        // Make a list of assessments
        $allAssessments = $this->assessHandler->getList($this->getCategoryIDList(), false, $this->year, $this->semester);

        if (!$allAssessments) {
            return false;
        }

        foreach($allAssessments as $currentAssessment) {
            if ($currentAssessment['allow_resit']) {
                $allAttempts = $this->attemptHandler->getListForUser($currentAssessment['assess_id'], $this->user['uid']);
            }
            else {
                $allAttempts = array($this->attemptHandler->getLatestForUser($currentAssessment['assess_id'], $this->user['uid']));
            }

            $firstSection = $this->sectionHandler->getFirst($currentAssessment['assess_id']);

            if (!is_array($allAttempts[0])) {
                $this->page->setVar(array(
                    'ASSESS_CATEGORY'               => $this->categories[$currentAssessment['category_id']]['name'],
                    'ASSESS_ASSESS_ID'              => $currentAssessment['assess_id'],
                    'ASSESS_TITLE'                  => Scrub::Text($currentAssessment['title']),
                    'ASSESS_FINISH'                 => '',
                    'ASSESS_FIRST_SECTION_ID'       => $firstSection['section_id'],
                    'ATTEMPT_ATTEMPT_ID'            => '',
                    'ATTEMPT_TOTAL_MARKS'           => number_format(0, 2),
                    'ATTEMPT_TOTAL_AVAILABLE_MARKS' => number_format($totalAvailableMarks, 2),
                    'ATTEMPT_PERCENTAGE'            => 0,
                    'ROW_COLOR'                     => $j % 2 ? 'dark' : ''
                ));

                $this->page->setVar('viewMarks_block', '');
                $this->page->parse('takeTest_block', 'takeTest');
                $this->page->setVar('unmarked_block', '');
                $this->page->parse('attempt_block', 'attempt', true);
            }
            elseif (is_array($allAttempts) && count($allAttempts)) {

                foreach($allAttempts as $currentAttempt) {
                    // Finish time
                    if ($currentAttempt['finish'] != '0000-00-00 00:00:00') {
                        $finish = date('jS F Y, g:i:sa', strtotime($currentAttempt['finish']));
                        $timeTaken = $this->assessHandler->sec2hms($currentAttempt['timeTaken']);
                    }
                    else {
                        $finish = '';
                        $timeTaken = '';
                    }

                    // The total available marks for this attempt
                    $totalAvailableMarks = $this->attemptHandler->getTotalMarks($currentAssessment['assess_id'], $currentAttempt['attempt_id']);

                    if ($totalAvailableMarks > 0) {
                        $marks = $this->attemptHandler->getMarksForAttempt($currentAttempt['attempt_id']);
                        $percentage = round(100 * $marks / $totalAvailableMarks);
                    }
                    else {
                        $percentage = 0;
                    }

                    // Count how many responses haven't been automarked, if it hasn't been reviewed yet.
                    if ($currentAttempt['reviewed_at'] == '') {
                        $this->page->setVar('ATTEMPT_UNMARKED_RESPONSES', $this->responseHandler->numNotAutomarked($currentAttempt['attempt_id']));
                        $this->page->parse('unmarked_block', 'unmarked');
                    }
                    else {
                        $this->page->setVar('unmarked_block', '');
                    }

                    $this->page->setVar(array(
                        'ASSESS_CATEGORY'               => $this->categories[$currentAssessment['category_id']]['name'],
                        'ASSESS_ASSESS_ID'              => $currentAssessment['assess_id'],
                        'ASSESS_TITLE'                  => Scrub::Text($currentAssessment['title']),
                        'ASSESS_FINISH'                 => $finish,
                        'ASSESS_FIRST_SECTION_ID'       => $firstSection['section_id'],
                        'ATTEMPT_ATTEMPT_ID'            => $currentAttempt['attempt_id'],
                        'ATTEMPT_TOTAL_MARKS'           => number_format($marks, 2),
                        'ATTEMPT_TOTAL_AVAILABLE_MARKS' => number_format($totalAvailableMarks, 2),
                        'ATTEMPT_PERCENTAGE'            => $percentage,
                        'ROW_COLOR'                     => $j % 2 ? 'dark' : ''
                    ));

                    $this->page->setVar('takeTest_block', '');

                    // Find out if this test has been marked; if it has, allow the student to see the feedback, otherwise remove link altogether
                    if ($currentAttempt['reviewed_at']) {
                        $this->page->parse('viewMarks_block', 'viewMarks');
                    }
                    else {
                        $this->page->setVar('viewMarks_block', '');
                    }

                    $this->page->parse('attempt_block', 'attempt', true);
                }
            }
            else {
                $this->page->setVar('attempt_block', '');
            }
        }

        $this->page->parse('allAssess_block', 'allAssess');

        return true;
    }



    public function getCategoryList() {
        return array_values($this->categories);
    }

    public function getCategoryIDList() {
        return array_keys($this->categories);
    }

    /**
     * Determine whether a date is in semester 1 or 2
     * @param int $timestamp
     * @param bool $asLogicTest
     * @return int
     */
    public static function getSemester($timestamp = 0) {
        if(!$timestamp) {
            $timestamp = time();
        }
        // currently we assume Jan - June is 1, July - Dec is 2
        // but this logic is self-contained, so replace it with something more sophisticated if needed
        return date('m', $timestamp) < 7 ? 1 : 2;
    }

    public static function getSemesterLogic($semester = 0) {
        if(!$semester) {
            $semester = self::getSemester();
        }
        return $semester == 1 ? '< 7' : '> 6';
    }

}
