<?php
use Schoolbox\Assessment\StudentReportCard\ContextSelector;

require_once(dirname(__FILE__) . '/../../include/Bootstrap.php');

if (isset($_GET['year']) || isset($_GET['semester'])) {
    $this->localRedir("/submission/listSubmission/{$_GET['year']}/{$_GET['semester']}");
}

$currentYear = date('Y');
$currentSemester = date('m') > 6 ? 2 : 1;

if (isset($this->arg[0])) {
    if(!(is_numeric($this->arg[0]) && is_numeric($this->arg[1]))) {
        $this->localRedir('/submission/listSubmission');
    }

    if ($this->arg[1] < 1 || $this->arg[1] > 2) {
        error(2); // TODO find a more appropriate error
    }

    $year = (int)$this->arg[0];
    $semester = (int)$this->arg[1];
    $semesterSlug = "/$year/$semester";
} else {
    $year = $currentYear;
    $semester = $currentSemester;
    $semesterSlug = '';
}


$this->setFile(TEMPLATE_content, 'submission/listSubmission.html');
$this->setTitle('Submissions');
$this->setTab('submission');
$this->setBlock(TEMPLATE_content, 'submission_listing', 'submission_block');
$this->setBlock(TEMPLATE_content, 'category_listing', 'category_block');

$this->setBlock('submission_listing', 'onlineAssessment', 'onlineAssessment_block');
$this->setBlock('onlineAssessment', 'readonly_assess', 'readonly_assess_block');
$this->setBlock('onlineAssessment', 'writable_assess', 'writable_assess_block');

$this->setBlock('submission_listing', 'normalSubmission', 'normalSubmission_block');
$this->setBlock('normalSubmission', 'readonly_duework', 'readonly_duework_block');
$this->setBlock('normalSubmission', 'writable_duework', 'writable_duework_block');

$this->setBlock('normalSubmission', 'readonly_open', 'readonly_open_block');
$this->setBlock('normalSubmission', 'readonly_close', 'readonly_close_block');

$this->setBlock('normalSubmission', 'writable_open', 'writable_open_block');
$this->setBlock('normalSubmission', 'writable_close', 'writable_close_block');

$this->setBlock('category_listing', 'learning_analytics', 'learning_analytics_block');

$this->setVar('SEM_SLUG', $semesterSlug);

$submissionHandler = new Submission();
$groupHandler = new Group();

$contextSelector = new ContextSelector('submission/listSubmission');

if(!$_SESSION['user']->getRole()->isStaff() ) {
    error(19);
}

// generate a "past 6 years" array
for($i = 5; $i >= 0; $i--) {
	$years[] = $currentYear - $i;
}
$contextSelector->setYears($years);

$subjectList = array();
/* Use provided ID, or check for the subjects the user is involved with */
if(isset($this->arg[2]) && is_numeric($this->arg[2])){
    $subjectList[] = $this->arg[2];

    //Selector rendored to maintain single category link
    $contextSelector->render($this, 'CONTEXT_SELECTOR', array(
            'year' => $year, 'semester' => $semester, 'list'=>$this->arg[2]
        ));
}
else {
    //Selector rendered without category
    $contextSelector->render($this, 'CONTEXT_SELECTOR', array(
            'year' => $year, 'semester' => $semester
        ));

    $involvedIn = $groupHandler->involvedInCategoryList($_SESSION['id'], true, WRITE_ACCESS);
    if(is_array($involvedIn)) {
        foreach($involvedIn as $subject) {
            $subjectList[] = $subject['id'];
        }
    }

    /* Merge in all the rest that are not related to the user by folder but by owner
       note i am using an older function for a simple task that is why it does more than it should */
    $allUserBoxes = $submissionHandler->getAllForUser($_SESSION['id'], $year, $semester);
    if(is_array($allUserBoxes)) {
        foreach($allUserBoxes as $box) {
            $subjectList[] = $box['catid'];
        }
    }

    $subjectList = array_unique($subjectList);
}



$subList = array();
foreach($subjectList as $subject) {
    $assessments = $submissionHandler->getAllForUserCategory($subject, 0, $year, $semester);
    if(is_array($assessments)) {
        $subList = array_merge($subList, $assessments);
    }
}

$attemptHandler = new Assess_Attempt();
$groupHandler = new Group();

if (Cfg::get('external_enable')) {
    $synHandler = new External_Class();
}

$this->setVar(array('category_block' => ''));



/* Iterate
 */
if (!empty($subList) && is_array($subList)) {
    $i = 0;

    /* $allCoursesUsedInFolder is a collection of folderId-courseFolders
     *
     * Get everything at the start in one query, rather than
     * ram them in to the weird loop below.
     *
     * Or you could have had a folder-course relation in the db
     */
    $allFolderIds = array();

    foreach ($subList as $currentSub) {
        $allFolderIds[] = $currentSub['category'];
    }

    $allFolderIds = array_values(array_unique($allFolderIds));

    $unitInstanceMapper = new \Schoolbox\Assessment\Course\UnitInstanceMapper();
    $allCoursesUsedInFolder = $unitInstanceMapper->findAllCoursesUsedInFolders($allFolderIds);

    foreach ($subList as $sub) {

        // TODO fully rewrite this awful copypasted loop
        if ($prevCat['category'] != $sub['category'] && $prevCat['category'] != '') {
            try {
                $curCategory = \Schoolbox\Resource\Folder\Mapper::getInstance()->findById($prevCat['category']);
                /* @param  \Schoolbox\Resource\Category $curCategory */

                /* Get full student list of class to show those who have not submitted
                 */
                if (count($curCategory->getCodes()) > 0 && Cfg::get('external_enable')) {
                    /* If synergy code, get synergy students
                     */
                    $studentList = $synHandler->getStudents($curCategory->getCodeString());
                } else {
                    /* Retrieve student list from group membership
                     */
                    $studentList = $groupHandler->getMemberList($curCategory->getId());
                }

                $this->setVar('STUDENTS', count($studentList));

                if (count($curCategory->getCodes()) > 0) {
                    $this->setVar('CATEGORY', Scrub::text($curCategory->getName()) . " (" . $curCategory->getCodeString() . ")");
                } else {
                    $this->setVar('CATEGORY', Scrub::text($curCategory->getName()));
                }

                $this->setVar('CATEGORY_ID', $curCategory->getId());

                /* Learning Analytics courses buttons go here
                 */
                $this->setVar(array(
                    'learning_analytics_block' => ''
                ));

                $allCoursesUsed = $allCoursesUsedInFolder->getOffset($curCategory->getId());

                if(isset($allCoursesUsed)) {
                    foreach ($allCoursesUsed as $courseFolder) {
                        $this->setVar(array(
                            'COURSE_FOLDER_ID' => $courseFolder->getId(),
                            'COURSE_FOLDER_NAME' => $courseFolder->getName()
                        ));

                        $this->parse('learning_analytics_block', 'learning_analytics', true);
                    }
                }

                $this->parse('category_block', 'category_listing', true);
                $this->setVar('submission_block', '');

                $i = 0;
            }
            catch (Exception $e) {
                // Couldn't find category, probably as it wasn't set.
            }
        }

        $totals = $submissionHandler->getBoxCount($sub['id']);

        $readonly = !($_SESSION['user']->isSuperuser() || date('Y',$sub['date']) >= date('Y'));

        $openADF = new Alaress_Date_Format($sub['open'], 'U');
        $closeADF = new Alaress_Date_Format($sub['date'], 'U');

        if($openADF->isAtStartOfDay()) {
            $openString = $openADF->getLongDateOnly();
        }
        else {
            $openString = $openADF->getLong();
        }

        if($closeADF->isAtEndOfDay()) {
            $closeString = $closeADF->getLongDateOnly();
        }
        else {
            $closeString = $closeADF->getLong();
        }

        $this->setVar(array(
            'ASSESS_START_TIMESTAMP' => $openADF->getUnixTimestamp(),
            'ASSESS_FINISH_TIMESTAMP' => $closeADF->getUnixTimestamp(),
        ));

        if (($sub['type_id'] == Assess_Main::ONLINE_ASSESSMENT_TYPE_ID) /*&& ($sub['availability'] == 'assess')*/) {
            $this->setVar(array(
                'ASSESS_ASSESS_ID' => $sub['id'],
                'ASSESS_TITLE' => Scrub::text($sub['name']),
                'ASSESS_TYPE' => $sub['typeName'],
                'JAVAASSDESC' => addslashes(Scrub::text($sub['name'])),
                'ASSESS_START' => $openString,
                'ASSESS_FINISH' => $closeString,
                'ASSESS_ATTEMPTS' => $attemptHandler->numAttempted($sub['id']),
                'ASSESS_REVIEWED' => $attemptHandler->numReviewed($sub['id']),
                'ASSESS_VISIBILITY_ID' => $sub['mark_visibility'],
                'ASSESS_VISIBILITY' => \Schoolbox\Assessment\Widgets::getName($sub['mark_visibility'])
            ));

            $this->setVar('normalSubmission_block', '');
            if ($readonly) {
                $this->parse('readonly_assess_block', 'readonly_assess');
                $this->setVar('writable_assess_block', '');

                $this->parse('readonly_open_block', 'readonly_open');
                $this->parse('readonly_close_block', 'readonly_close');
                $this->setVar('writable_open_block', '');
                $this->setVar('writable_close_block', '');
            } else {
                $this->parse('writable_assess_block', 'writable_assess');
                $this->setVar('readonly_assess_block', '');

                $this->parse('writable_open_block', 'writable_open');
                $this->parse('writable_close_block', 'writable_close');
                $this->setVar('readonly_open_block', '');
                $this->setVar('readonly_close_block', '');
            }

            $this->parse('onlineAssessment_block', 'onlineAssessment');
        } else if ($sub['availability'] == '') {
            $this->setVar(array(
                'ID' => $sub['id'],
                'ASSDESC' => Scrub::text($sub['name']),
                'TYPE' => $sub['typeName'],
                'JAVAASSDESC' => addslashes(Scrub::text($sub['name'])),
                'OPENDATE' => $sub['accept_submission'] ? $openString : '',
                'DUEDATE' => $closeString,
                'MARKED' => (int)$totals['marked'],
                'TOTAL' => $sub['accept_submission'] ? $totals['total'] : 'N/A',
                'ASSESS_VISIBILITY_ID' => $sub['mark_visibility'],
                'ASSESS_VISIBILITY' => \Schoolbox\Assessment\Widgets::getName($sub['mark_visibility']),
            ));

            // Don't include in start sorts if it doesn't accept submissions
            if (!$sub['accept_submission']) {
                $this->setVar('ASSESS_START_TIMESTAMP', 0);
            }

            $this->setVar('onlineAssessment_block', '');

            if ($readonly) {
                $this->parse('readonly_duework_block', 'readonly_duework');
                $this->setVar('writable_duework_block', '');

                $this->parse('readonly_open_block', 'readonly_open');
                $this->parse('readonly_close_block', 'readonly_close');
                $this->setVar('writable_open_block', '');
                $this->setVar('writable_close_block', '');
            } else {
                $this->parse('writable_duework_block', 'writable_duework');
                $this->setVar('readonly_duework_block', '');

                $this->parse('writable_open_block', 'writable_open');
                $this->parse('writable_close_block', 'writable_close');
                $this->setVar('readonly_open_block', '');
                $this->setVar('readonly_close_block', '');
            }

            $this->parse('normalSubmission_block', 'normalSubmission');
        } else {
            // something went wrong (ie. this scenario should not exist) but at any rate, hide the tokens from view
            $this->setVar('onlineAssessment_block', '');
            $this->setVar('normalSubmission_block', '');
        }
        $this->parse('submission_block', 'submission_listing', true);

        $prevCat = $sub;
    }

    /* Get full student list of class to show those who have not submitted
      */
    try {
        $curCategory = \Schoolbox\Resource\Folder\Mapper::getInstance()->findById($prevCat['category']);
        if (count($curCategory->getCodes()) > 0 && Cfg::get('external_enable')) {
            /* If synergy code, get synergy students
               */
            $studentList = $synHandler->getStudents($curCategory->getCodeString());
        } else {
            /* Retrieve student list from group membership
               */
            $studentList = $groupHandler->getMemberList($curCategory->getId());
        }

        $this->setVar('STUDENTS', count($studentList));

        if (count($curCategory->getCodes()) > 0) {
            $this->setVar("CATEGORY", Scrub::text($curCategory->getName()) . " (" . $curCategory->getCodeString() . ")");
        } else {
            $this->setVar("CATEGORY", Scrub::text($curCategory->getName()));
        }

        $this->setVar('CATEGORY_ID', $prevCat['category']);

        /* Learning Analytics courses buttons go here
         */
        $this->setVar(array(
            'learning_analytics_block' => ''
        ));

        $allCoursesUsed = $allCoursesUsedInFolder->getOffset($curCategory->getId());

        if(is_array($allCoursesUsed) || $allCoursesUsed instanceof \Traversable) {
                foreach ($allCoursesUsed as $courseFolder) {
                    $this->setVar(array(
                        'COURSE_FOLDER_ID' => $courseFolder->getId(),
                        'COURSE_FOLDER_NAME' => $courseFolder->getName()
                    ));

                    $this->parse('learning_analytics_block', 'learning_analytics', true);
                }
        }

        $this->parse("category_block", "category_listing", true);

        /* Clear children after parsing out the parent
         */
        $this->setVar(array(
            'learning_analytics_block' => ''
        ));
    }
    catch (Exception $e) {
        // Couldn't find category, probably as it wasn't set.
    }
}
else {
    // pre-emptively clear the blocks that might not get rendered
    $this->setVar(array(
		'category_block' => 'No submissions found.',
        'writable_assess_block' => '',
        'writable_duework_block' => '',
    ));

    //$this->parse('readonly_assess_block', 'readonly_assess');
    //$this->parse('readonly_duework_block', 'readonly_duework');
}
