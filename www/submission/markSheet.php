<?php

if(!isset($this)) {
    require_once dirname(__FILE__) . '/../../include/Bootstrap.php';
    SitePage::localRedir('/submission/markSheet');
}

$performExport = false;
if(is_numeric($this->arg[0])) {
    $categoryID = $this->arg[0];
    if ($this->arg[3] == 'export') {
        $performExport = true;
    }
}
else {
    error(2); // not enough info submitted ...is there a better error?
    exit;
}


if (isset($_GET['year']) || isset($_GET['semester'])) {
    $this->localRedir("/submission/markSheet/{$categoryID}/{$_GET['year']}/{$_GET['semester']}");
}

$currentYear = date('Y');
$currentSemester = date('m') > 6 ? 2 : 1;

if(isset($this->arg[1])) {
    if(!(is_numeric($this->arg[1]) && is_numeric($this->arg[2]))) {
        error(2); // TODO find a more appropriate error
    }

    if($this->arg[2] < 1 || $this->arg[2] > 2) {
        error(2); // TODO find a more appropriate error
    }

    $year = (int)$this->arg[1];
    $semester = (int)$this->arg[2];
}
else {
    $year = $currentYear;
    $semester = $currentSemester;
}

//
// Set year and semester into the template.
$this->setVar('SELECTED_YEAR',     $year);
$this->setVar('SELECTED_SEMESTER', $semester);

$subHandler = new Submission();
$retHandler = new SubmissionReturn();
$catHandler = new Category();
$groupHandler = new Group();
$userHandler = new User();

$factory = new Assess_Factory();
$assessHandler = new Assess_Main();
$attemptHandler = new Assess_Attempt();
$sectionHandler = new Assess_Section();
$responseHandler = new Assess_Response();

if ($performExport && $categoryID) {
    $category = $catHandler->getByID($categoryID);

    /* Open an output stream so we can use fputcsv
      */
    $filename = preg_replace('/[^0-9a-zA-Z()_-]/', '_', $category['name']);

    header("Pragma: ");
    header("Cache-control: ");
    header("Content-Type: text/csv");
    header("Content-Disposition: attachment; filename=\"" . $filename . ".csv\"");

    $fp = fopen('php://output', 'w');

    if (!$groupHandler->isStaff()) {
        error(19);
    }

    /* Get full student list of class  */
    if ($category['code'] && Cfg::get('external_enable')) {

        //if synergy code get synergy students
        $synHandler = new External_Class();
        $studentList = $synHandler->getStudents($category['code']);

        for ($x = 0; is_array($studentList[$x]); $x++) {
            $user = $userHandler->getBySynergy($studentList[$x]['synergy_id']);

            /* If the Synergetic user wasn't found in the portal, just remove
                * them from the list. Otherwise the future queries about submitted
                * work will break, because there are no uids.
                */
            if (!$user) {
                unset($studentList[$x]);
            } else {
                $studentList[$x]['uid'] = $user['uid'];
            }
        }
    } else {
        //Retrieve student list from group membership
        $groupHandler = new Group;
        $studentList = $groupHandler->getMemberList($categoryID);
    }

    $subList = $subHandler->getAllForUserCategory($categoryID, $_SESSION['id'], $year, $semester);

    if (is_array($subList)) {
        $csv = array('Synergetic ID', 'Surname', 'Firstname');

        /* Scrub::text() too aggressive, creates untranslatable &#039; for apostrophes?
           */
        foreach ($subList as $sub) {
            $csv[] = $sub['name'];
            $csv[] = '';
            $csv[] = '';
        }

        fputcsv($fp, $csv);

        /* Add subheadings
           * These are spacers for synergetic id, surname, firstname
           */
        $csv = array('', '', '');

        /* Actual subheadings
           */
        foreach ($subList as $sub) {
            $submissionType = $subHandler->getType($sub['type_id']);
            $csv[] = $submissionType['name'];
            $csv[] = '';
            $csv[] = '';
        }

        fputcsv($fp, $csv);

        /* Add third level subheadings
           */
        $csv = array('', '', '');

        /* Actual subheadings
           */
        foreach ($subList as $sub) {
            if ($sub['type_id'] == Assess_Main::ONLINE_ASSESSMENT_TYPE_ID) {
                $csv[] = 'Grade';
                $csv[] = 'Total';
            } else {
                $csv[] = 'Normalised Grade';
                $csv[] = 'Grade';
                $csv[] = 'Comment';
            }
        }

        fputcsv($fp, $csv);
    }

    if (is_array($studentList)) {
        foreach ($studentList as $student) {
            $csv = array();
            $csv[] = $student['uid'];
            $csv[] = $student['lastname'];
            $csv[] = $student['firstname'];

            if (is_array($subList)) {
                foreach ($subList as $sub) {
                    if ($sub['type_id'] == Assess_Main::ONLINE_ASSESSMENT_TYPE_ID) {
                        $firstSection = $sectionHandler->getFirst($sub['id']);
                        $mostRecentAttempt = $attemptHandler->getLatestForUser($sub['id'], $student['uid']);
                        $marks = $attemptHandler->getMarksForAttempt($mostRecentAttempt['attempt_id']);
                        $total = $attemptHandler->getTotalMarks($sub['id'], $mostRecentAttempt['attempt_id']);

                        /* return two columns, grade and total available marks
                               */
                        if ($marks && $total) {
                            $csv[] = Scrub::text($marks);
                            $csv[] = Scrub::text($total);
                        } else {
                            $csv[] = '';
                            $csv[] = '';
                        }
                    } else {

                        /* Return two columns, grade and comment
                         */
                        $return = $retHandler->getByUser($student['uid'], $sub['id']);
                        // if this is legacy, return Excel-escaped (ie. '-prepended) mark, otherwise norm_mark
                        $mark = $return[0]['slug'] == 'free' ? "'{$return[0]['mark']}" : $return[0]['norm_mark'];

                        $csv[] = $mark;
                        $csv[] = $return[0]['mark'];
                        $csv[] = $return[0]['comment'];
                    }
                }
            }

            fputcsv($fp, $csv);
        }
    }

    fclose($fp);

    exit;
}
elseif (is_numeric($categoryID)) {
    $this->setFile(TEMPLATE_content, 'submission/markSheet.html');
    $this->setTitle('Class Mark Sheet');
    $this->setTab('submission');

    $this->setBlock(TEMPLATE_content, 'sub_type_head', 'sub_type_head_block');
    $this->setBlock(TEMPLATE_content, 'sub_head', 'sub_head_block');
    $this->setBlock(TEMPLATE_content, 'assess_head', 'assess_head_block');
    $this->setBlock(TEMPLATE_content, 'sub_headers', 'sub_headers_block');
    $this->setBlock(TEMPLATE_content, 'assess_type_head', 'assess_type_head_block');
    $this->setBlock(TEMPLATE_content, 'average_type_head', 'average_type_head_block');
    $this->setBlock(TEMPLATE_content, 'student_list', 'student_list_block');
    $this->setBlock(TEMPLATE_content, 'student_mark_list', 'student_mark_list_block');
    $this->setBlock('student_mark_list', 'mark_average', 'mark_average_block');
    $this->setBlock('student_mark_list', 'markItem', 'markItem_block');
    $this->setBlock('markItem', 'mark', 'mark_block');
    $this->setBlock('markItem', 'onlineAssessmentMark', 'onlineAssessmentMark_block');
    $this->setBlock('mark', 'mark_type_percent', 'mark_type_percent_block');
    $this->setBlock('mark', 'mark_type_letter', 'mark_type_letter_block');
    $this->setBlock('mark', 'mark_type_raw', 'mark_type_raw_block');
    $this->setBlock('mark', 'mark_type_free', 'mark_type_free_block');

    $this->setBlock(TEMPLATE_content, 'no_submissions', 'no_submissions_block');
    $this->setBlock(TEMPLATE_content, 'no_assessments', 'no_assessments_block');

    $this->setBlock(TEMPLATE_content, 'semester_option', 'semester_option_block');
    $this->setBlock(TEMPLATE_content, 'year_option', 'year_option_block');

    if (!$groupHandler->isStaff()) {
        error(19);
    }

    // Semesters can be hardcoded, no need to look anything up (may change if a school wants Term breakdowns rather than Semester)
    $semesters = array(1, 2);
    foreach ($semesters as $sem) {
        $this->setVar(array(
            'SEMESTER_SELECTED' => $sem == $semester ? ' selected="selected"' : '',
            'SEMESTER_VALUE' => $sem
        ));
        $this->parse('semester_option_block', 'semester_option', true);
    }

    // Years is trickier, because we first need to find out all the years in which the teacher has ...taught
    // while we figure that out, let's go with "the past 6 years"
    for ($y = $currentYear; $y > $currentYear - 6; --$y) {
        $this->setVar(array(
            'YEAR_SELECTED' => $y == $year ? ' selected="selected"' : '',
            'YEAR_VALUE' => $y
        ));
        $this->parse('year_option_block', 'year_option', true);
    }

    $category = $catHandler->getByID($categoryID);

    if ($category['code'] != "") {
        $this->setVar('CATEGORY', $category['name'] . ' (' . $category['code'] . ')');
    }
    // no code
    else {
        $this->setVar('CATEGORY', $category['name']);
    }

    $dem = DueWork_ErrorMapper::getInstance();
    $errs = $dem->listDisplayValues();
    $this->setVar('COMBO_OPTIONS', json_encode($errs));

    $this->setVar('CID', $category['id']);

    /* Get full student list of class  */
    if ($category['code'] && Cfg::get('external_enable')) {

        //if synergy code get synergy students
        $synHandler = new External_Class();
        $studentList = $synHandler->getStudents($category['code']);

        for ($x = 0; is_array($studentList[$x]); $x++) {
            $user = $userHandler->getBySynergy($studentList[$x]['synergy_id']);
            $studentList[$x]['uid'] = $user['uid'];
        }
    }
    // retrieve student list from group membership
    else {
        $groupHandler = new Group;
        $studentList = $groupHandler->getMemberList($categoryID);
    }

    $subList = $subHandler->getAllForUserCategory($categoryID, $_SESSION['id'], $year, $semester);

    $this->setVar('sub_head_block', '');
    $this->setVar('sub_headers_block', '');
    $this->setVar('assess_head_block', '');
    $this->setVar('sub_type_head_block', '');
    $this->setVar('assess_type_head_block', '');

    $this->setVar('no_submissions_block', '');
    $this->setVar('no_assessments_block', '');
    if (is_array($subList)) {
        foreach ($subList as $sub) {
            $this->setVar(array(
                'BOX_ID' => $sub['id'],
                'SUB_NAME' => Scrub::text($sub['name']),
                'SUB_TYPE' => Scrub::text($sub['typeName'])
            ));

            if ($sub['type_id'] == Assess_Main::ONLINE_ASSESSMENT_TYPE_ID) {

                /* Perform automated marking on responses which support this feature. Only
                 * mark responses which haven't been automarked previously, which allows
                 * teachers to customise response marks without being overwritten again.
                 *
                 * Once automated marking has been performed, remember which responses were
                 * affected so we don't refetch them the next time the page loads.
                 */
                $allAttempts = $attemptHandler->getList($sub['id']);

                if (is_array($allAttempts)) {
                    foreach ($allAttempts as $currentAttempt) {
                        $allResponses = $responseHandler->getAutomarkableResponses($currentAttempt['attempt_id']);

                        if (is_array($allResponses)) {
                            foreach ($allResponses as $currentResponse) {
                                $answerHandler = $factory->createAnswerModel($currentResponse['type_id']);

                                $answerHandler->mark($currentResponse);

                                $markedResponseIDs[] = $currentResponse['response_id'];
                            }
                        }
                    }

                    $responseHandler->updatedAutomatedMarks($markedResponseIDs);
                }

                $this->setVar('sub_head_block', '');
                $this->parse('assess_head_block', 'assess_head');
            }
            else {
                $this->setVar('assess_head_block', '');
                $this->parse('sub_head_block', 'sub_head');
            }
            $this->parse('sub_type_head_block', 'sub_type_head', true);
            $this->parse('sub_headers_block', 'sub_headers', true);
        }
    }
    else {
        $this->setVar(array(
            'BOX_ID' => 'x',
            'SUB_NAME' => '',
            'SUB_TYPE' => ''
        ));
        $this->setVar('sub_head_block', '');
        $this->setVar('sub_type_head_block', '');
        $this->parse('sub_headers_block', 'sub_headers');
    }

    if (is_array($studentList)) {
        foreach ($studentList as $student) {

            $avgSum = 0;
            $avgCount = 0;

            /* If there is no uid associated with the student, prevent them
                * from appearing in the markSheet.
                */
            if (is_numeric($student['uid'])) {
                $output = array(
                    'SURNAME' => Scrub::text($student['lastname']),
                    'FIRSTNAME' => Scrub::text($student['firstname']),
                    'UID' => $student['uid'],
                    'ON_OFF' => ++$i % 2 ? '' : 'dark',
                );

                $this->setVar($output);

                $this->setVar('mark_block', '');
                $this->setVar('onlineAssessmentMark_block', '');

                if (is_array($subList)) {
                    foreach ($subList as $sub) {
                        if ($sub['type_id'] == Assess_Main::ONLINE_ASSESSMENT_TYPE_ID) {
                            $firstSection = $sectionHandler->getFirst($sub['id']);
                            $mostRecentAttempt = $attemptHandler->getLatestForUser($sub['id'], $student['uid']);
                            $marks = $attemptHandler->getMarksForAttempt($mostRecentAttempt['attempt_id']);
                            $total = $attemptHandler->getTotalMarks($sub['id'], $mostRecentAttempt['attempt_id']);

                            if($total != 0) {
                                $avgSum += ($marks / $total) * 100;
                                ++$avgCount;
                            }

                            $this->setVar(array(
                                'ASSESS_ASSESS_ID' => $sub['id'],
                                'ASSESS_FIRST_SECTION_ID' => $firstSection['section_id'],
                                'ATTEMPT_ID' => $mostRecentAttempt['attempt_id'],
                                'MARK' => ($marks && $total) ? number_format($marks, 2) . ' / ' . number_format($total, 2) : '',
                                'BOX_ID' => ''
                            ));

                            $this->setVar('mark_block', '');
                            $this->parse('onlineAssessmentMark_block', 'onlineAssessmentMark');
                        } else {
                            /* If a UID was found for the student, try to get his
                             * submissions. Otherwise skip him (this occurs when he
                             * exists in Synergetic but not in schoolbox)
                             */
                            if (is_numeric($student['uid'])) {
                                $return = $retHandler->getByUser($student['uid'], $sub['id']);

                                $subbox = $subHandler->getByID($sub['id']);

                                $marksheet = Marksheet_Factory::build($subbox['mark_type_slug']);
                                $mark = $marksheet->retrieveMark($student['uid'], $sub['id'], false, true);

                                if(isset($mark['norm_mark']) && $mark['norm_mark'] !== null) {
                                    $avgSum += $mark['norm_mark'];
                                    ++$avgCount;
                                }

                                /**
                                 * @param SitePage $page
                                 * @param $handle
                                 * @param $htmlFormID
                                 * @param $markArray
                                 */
                                $templateFile = 'writable';
                                $this->setFile('markTpl', "submission/marktype/$templateFile.html");
                                $this->setVar(array(
                                    'UFID'     => -1,
                                    'OWNER_ID' => $student['uid'],
                                    'BOX_ID'   => $sub['id']
                                ));


                                // blockify and clear all the marktypes
                                foreach ($subHandler->getMarkTypes(true) as $type) {
                                    $this->setBlock('markTpl', "mark_type_{$type['slug']}", "mark_type_{$type['slug']}_block");
                                    $this->setVar("mark_type_{$type['slug']}_block", '');
                                }

                                // each strategy knows how to output itself
                                $marksheet->outputUI($this, $mark);

                                // render the appropriate block and parse it into the handle we were provided
                                $slug = $marksheet->getSlug();
                                $this->parse("mark_type_{$slug}_block", "mark_type_{$slug}");

                                $this->parse('STUDENT_MARKING', 'markTpl');
                            }

                            $this->parse('mark_block', 'mark');
                            $this->setVar('onlineAssessmentMark_block', '');
                        }

                        $this->parse('markItem_block', 'markItem', true);
                        $this->setVar('mark_block', '');
                        $this->setVar('onlineAssessmentMark_block', '');

                    }
                } else {
                    $this->setVar('mark_block', '');
                    $this->setVar('onlineAssessmentMark_block', '');
                }

                // at the end of each student, output their average
                if($avgCount > 0) {
                    $studentAverage = $avgSum / $avgCount;
                    $this->setVar('STUDENT_AVERAGE_MARK', sprintf('%0.2f', $studentAverage));
                }
                else {
                    $this->setVar('STUDENT_AVERAGE_MARK', '&mdash;');
                }
                $this->parse('mark_average_block', 'mark_average');

                $this->parse('student_list_block', 'student_list', true);
                $this->parse('student_mark_list_block', 'student_mark_list', true);
                $this->setVar('markItem_block', '');
            }

        }
        // add the average header
        $this->parse('average_type_head_block', 'average_type_head');
    }
    else {
        $this->setVar(array(
            'average_type_head_block' => '',
            'mark_average_block' => '',
            'student_list_block' => '',
            'student_mark_list_block' => ''
        ));
    }
} else {
    error(2);
}
