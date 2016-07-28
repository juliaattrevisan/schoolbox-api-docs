<?php
use Schoolbox\Assessment\StudentReportCard\ContextSelector;

require_once(dirname(__FILE__) . '/../../library/Bootstrap.php');

$termMapper = Schoolbox\Assessment\Term\Mapper::getInstance();

$termSlug = $this->arg[0];

if (isset($termSlug)) {
    try {
        $currentTerm = $termMapper->findOneByColumn('code', $termSlug);
    } catch (\Schoolbox\Mapper\Exception $e) {
        $this->localRedir('/submission/listSubmission');
        exit;
    }
} else {
    $currentTerm = $termMapper->findForToday();
}
$termSlug = $currentTerm->getCode();

$this->setFile(TEMPLATE_content, 'submission/listSubmission.html');
$this->setTitle('Submissions');
$this->setTab('submission');
$this->setBlock(TEMPLATE_content, 'submission_listing', 'submission_block');
$this->setBlock(TEMPLATE_content, 'category_listing', 'category_listing_block');

$this->setBlock('category_listing', 'class_list', 'class_list_block');
$this->setBlock('category_listing', 'members_list', 'members_list_block');

$this->setBlock('category_listing', 'no_assessments', 'no_assessments_block');
$this->setBlock('category_listing', 'assessment_output', 'assessment_output_block');

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
$this->setBlock(TEMPLATE_content, 'no_category_listing', 'no_category_listing_block');

$this->setVar('CURRENT_TERM_SLUG', $termSlug);

$submissionHandler = new Submission();
$groupHandler = new Group();

$contextSelector = new ContextSelector('submission/listSubmission');
$contextSelector->setTerms($termMapper->findAll(['order' => ['start' => 'desc']]));

/** @var Schoolbox\User $sessionUser */
$sessionUser = $_SESSION['user'];
if (!$sessionUser->getRole()->isStaff()) {
    error(19);
}

$subjectList = array();
/* Use provided ID, or check for the subjects the user is involved with */
if (isset($this->arg[1]) && is_numeric($this->arg[1])){
    $subjectList[] = $this->arg[1];

    //Selector rendored to maintain single category link
    $contextSelector->render(
        $this,
        'CONTEXT_SELECTOR',
        array('term' => $currentTerm, 'list' => $this->arg[1],)
    );
}
else {
    //Selector rendered without category
    $contextSelector->render(
        $this,
        'CONTEXT_SELECTOR',
        array('term' => $currentTerm,)
    );

    // This semester: get the classes I'm involved in, and my groups
    // Other semesters: just get my groups
    $getClasses = $currentTerm->isMarkableBy($sessionUser) && $currentTerm->contains(Alaress_Date_Format::now());
    $involvedIn = $groupHandler->involvedInCategoryList(
        $sessionUser->getId(),
        $getClasses,
        \Schoolbox\Permission\Access\UserAccess::ACCESS_WRITE
    );
    if (is_array($involvedIn)) {
        foreach ($involvedIn as $subject) {
            $subjectList[] = $subject['id'];
        }
    }

    /* Merge in all the rest that are not related to the user by folder but by owner
       note i am using an older function for a simple task that is why it does more than it should */
    $allUserBoxes = $submissionHandler->getAllForUser(
        $sessionUser->getId(),
        $currentTerm->getStart(),
        $currentTerm->getActualFinish()
    );
    if(is_array($allUserBoxes)) {
        foreach($allUserBoxes as $box) {
            $subjectList[] = $box['catid'];
        }
    }

    $subjectList = array_unique($subjectList);

}

$attemptHandler = new Assess_Attempt();
$groupHandler = new Group();

if (Cfg::get('external_enable')) {
    $synHandler = new External_Class();
}

$this->setVar(array('category_listing_block' => ''));

/* Iterate
 */
if (!empty($subjectList) && is_array($subjectList)) {
    $i = 0;

    /* $allCoursesUsedInFolder is a collection of folderId-courseFolders
     *
     * Get everything at the start in one query, rather than
     * ram them in to the weird loop below.
     *
     * Or you could have had a folder-course relation in the db
     */
    $allFolderIds = array();

    $unitInstanceMapper = new \Schoolbox\Assessment\Course\UnitInstanceMapper();
    $allCoursesUsedInFolder = $unitInstanceMapper->findAllCoursesUsedInFolders($subjectList);

    foreach ($subjectList as $sub) {
        /* @param  \Schoolbox\Resource\Folder\Model $curCategory */
        try {
            $curCategory = \Schoolbox\Resource\Folder\Mapper::getInstance()->findById($sub);
        }
        catch (Exception $e) {
            // Couldn't find category, probably as it wasn't set.
            continue;
        }

        $assessments = $submissionHandler->getAllForUserCategory(
            $curCategory->getId(),
            0,
            $currentTerm->getStart(),
            $currentTerm->getActualFinish()
        );

        // Is the assessment list empty?
        // Don't show this list if this isn't a subject.
        if (empty($assessments)) {
            if (count($curCategory->getCodes()) === 0) {
                continue;
            }
        }

        /* Get full student list of class to show those who have not submitted
         */
        if (count($curCategory->getCodes()) > 0 && Cfg::get('external_enable')) {
            /* If synergy code, get synergy students
             */
            $studentList = $synHandler->getStudents($curCategory->getCodeString());
            $this->setVar('STUDENTS', count($studentList));
            $this->setVar('CATEGORY_CODE', urlencode(urlencode($curCategory->getCodeString())));

            $this->setVar('members_list_block' ,'');
            $this->parse('class_list_block','class_list');
        } else {
            /* Retrieve student list from group membership
             */
            $studentList = $groupHandler->getMemberList($curCategory->getId());
            $this->setVar('STUDENTS', count($studentList));
            $this->setVar('class_list_block' ,'');
            $this->parse('members_list_block','members_list');
        }

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

        $this->setVar('submission_block', '');

        $i = 0;

        $readonly = !$currentTerm->isMarkableBy($sessionUser);

        $displayClass = false;
        if(is_array($assessments) && count($assessments) > 0 ) {
            $displayClass = true;
            /* Iterate over all the assessments and output them */
            foreach ($assessments as $assess) {


                $totals = $submissionHandler->getBoxCount($assess['id']);

                $openADF = new Alaress_Date_Format($assess['open'], 'U');
                $closeADF = new Alaress_Date_Format($assess['date'], 'U');

                if ($openADF->isAtStartOfDay()) {
                    $openString = $openADF->getLongDateOnly();
                } else {
                    $openString = $openADF->getLong();
                }

                if ($closeADF->isAtEndOfDay()) {
                    $closeString = $closeADF->getLongDateOnly();
                } else {
                    $closeString = $closeADF->getLong();
                }

                $this->setVar(array(
                    'ASSESS_START_TIMESTAMP' => $openADF->getUnixTimestamp(),
                    'ASSESS_FINISH_TIMESTAMP' => $closeADF->getUnixTimestamp(),
                ));

                if (($assess['type_id'] == Assess_Main::ONLINE_ASSESSMENT_TYPE_ID) /*&& ($assess['availability'] == 'assess')*/) {
                    $this->setVar(array(
                        'ASSESS_ASSESS_ID' => $assess['id'],
                        'ASSESS_TITLE' => Scrub::text($assess['name']),
                        'ASSESS_TYPE' => $assess['typeName'],
                        'JAVAASSDESC' => addslashes(Scrub::text($assess['name'])),
                        'ASSESS_START' => $openString,
                        'ASSESS_FINISH' => $closeString,
                        'ASSESS_ATTEMPTS' => $attemptHandler->numAttempted($assess['id']),
                        'ASSESS_REVIEWED' => $attemptHandler->numReviewed($assess['id']),
                        'ASSESS_VISIBILITY_ID' => $assess['mark_visibility'],
                        'ASSESS_VISIBILITY_CSS' => \Schoolbox\Assessment\Widgets::getCss($assess['mark_visibility']),
                        'ASSESS_VISIBILITY' => \Schoolbox\Assessment\Widgets::getName($assess['mark_visibility'])
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
                } else if ($assess['availability'] == '') {
                    $this->setVar(array(
                        'ID' => $assess['id'],
                        'ASSDESC' => Scrub::text($assess['name']),
                        'TYPE' => $assess['typeName'],
                        'JAVAASSDESC' => addslashes(Scrub::text($assess['name'])),
                        'OPENDATE' => $assess['accept_submission'] ? $openString : '',
                        'DUEDATE' => $closeString,
                        'MARKED' => (int)$totals['marked'],
                        'TOTAL' => $assess['accept_submission'] ? $totals['total'] : 'N/A',
                        'ASSESS_VISIBILITY_ID' => $assess['mark_visibility'],
                        'ASSESS_VISIBILITY_CSS' => \Schoolbox\Assessment\Widgets::getCss($assess['mark_visibility']),
                        'ASSESS_VISIBILITY' => \Schoolbox\Assessment\Widgets::getName($assess['mark_visibility']),
                    ));

                    // Don't include in start sorts if it doesn't accept submissions
                    if (!$assess['accept_submission']) {
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

            }
            $this->parse('assessment_output_block','assessment_output');
            $this->setVar('no_assessments_block','');
        }
        else {
            $this->setVar('assessment_output_block','');
            $this->parse('no_assessments_block','no_assessments');
        }



        $this->parse('category_listing_block', 'category_listing', true);


    }

    $this->setVar(array(
        'no_category_listing_block' => ''
    ));


}
else {
    // pre-emptively clear the blocks that might not get rendered
    $this->setVar(array(
		'category_listing_block' => '',
        'writable_assess_block' => '',
        'writable_duework_block' => '',
    ));

    $this->parse('no_category_listing_block', 'no_category_listing');
}
