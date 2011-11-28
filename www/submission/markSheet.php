<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/../library/assess/Assess.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/../library/assess/AssessAttempt.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/../library/assess/AssessSection.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/../library/assess/AssessResponse.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/../library/assess/AssessFactory.php');

$subHandler = new Submission();
$retHandler = new SubmissionReturn();
$catHandler = new Category();
$groupHandler = new Group();
$userHandler = new DeprecatedUser();

$factory = new AssessFactory();
$assessHandler = new Assess();
$attemptHandler = new AssessAttempt();
$sectionHandler = new AssessSection();
$responseHandler = new AssessResponse();

if ($this->arg[0] == 'export' && is_numeric($this->arg[1])) {
	if (!$groupHandler->isStaff()) {
		error(19);
	}

	$category = $catHandler->getByID($this->arg[1]);

	/* Get full student list of class  */
    if ($category['code'] && Cfg::get('external_enable')) {

        //if synergy code get synergy students
        $synHandler = new External_Class();
        $studentList = $synHandler->getStudents($category['code']);

		for ($x=0; is_array($studentList[$x]);$x++){
			$user = $userHandler->getBySynergy($studentList[$x]['synergy_id']);

			/* If the Synergetic user wasn't found in the portal, just remove
			 * them from the list. Otherwise the future queries about submitted
			 * work will break, because there are no uids.
			 */
			if (!$user) {
				unset($studentList[$x]);
			}
			else {
				$studentList[$x]['uid'] = $user['uid'];
			}
		}
    }
    else {
        //Retrieve student list from group membership
        $groupHandler = new Group;
        $studentList = $groupHandler->getMemberList($this->arg[1]);
    }

	$subList = $subHandler->getAllForUserCategory($this->arg[1], $_SESSION['id']);

	if (is_array($subList)) {
        $csv = 'Synergetic ID,Surname,Firstname,';

		foreach($subList as $sub) {
            $csv .= Scrub::text(str_replace(',', ' ', $sub['name'])).',';
		}
	}

    $csv .= "\n";

	if (is_array($studentList)) {
		foreach($studentList as $student) {
            $csv .= $student['uid'].','.Scrub::text($student['lastname']).','.Scrub::text($student['firstname']).',';

			if (is_array($subList)) {
				foreach($subList as $sub) {
                    if ($sub['type_id'] == Assess::ONLINE_ASSESSMENT_TYPE_ID) {
                        $firstSection = $sectionHandler->getFirst($sub['id']);
                        $mostRecentAttempt = $attemptHandler->getLatestForUser($sub['id'], $student['uid']);
                        $marks = $attemptHandler->getMarksForAttempt($mostRecentAttempt['attempt_id']);
                        $total = $attemptHandler->getTotalMarks($sub['id'], $mostRecentAttempt['attempt_id']);

                        if ($marks && $total) {
                            $val = $marks . ' / ' . $total;
                        }
                        else {
                            $val = '';
                        }

                        $csv .= Scrub::text($val).',';
                    }
                    else {
                        $return = $retHandler->getByUser($student['uid'],$sub['id']);
                        $csv .= Scrub::text($return[0]['mark']).',';
                    }
				}
			}
            $csv .="\n";
		}
	}

    $filename = preg_replace('/[^0-9a-zA-Z()_-]/','_', $category['name']);

    header ("Pragma: ");
    header ("Cache-control: ");
    header ("Content-Type: text/csv");
    header ("Content-Disposition: attachment; filename=\"".$filename.".csv\"");
    echo $csv;

    exit;
}
else if(is_numeric($this->arg[0])) {
	$this->setFile(TEMPLATE_content, 'submission/markSheet.html');
	$this->setTitle('Class Mark Sheet');
	$this->setTab('submission');

	$this->setBlock(TEMPLATE_content, 'sub_head', 'sub_head_block');
	$this->setBlock(TEMPLATE_content, 'assess_head', 'assess_head_block');
	$this->setBlock(TEMPLATE_content, 'student_list', 'student_list_block');
	$this->setBlock('student_list', 'markItem', 'markItem_block');
	$this->setBlock('markItem', 'mark', 'mark_block');
    $this->setBlock('markItem', 'onlineAssessmentMark', 'onlineAssessmentMark_block');

	$this->setBlock(TEMPLATE_content, 'no_submissions', 'no_submissions_block');
	$this->setBlock(TEMPLATE_content, 'no_assessments', 'no_assessments_block');

	if (!$groupHandler->isStaff()) {
		error(19);
	}

    $category = $catHandler->getByID($this->arg[0]);

	if ($category['code'] != "") {
		$this->setVar('CATEGORY', $category['name'] . ' (' . $category['code'] . ')');
	}
	else {
		$this->setVar('CATEGORY', $category['name']);
	}

	$this->setVar('CID', $category['id']);

	/* Get full student list of class  */
    if ($category['code'] && Cfg::get('external_enable')) {

        //if synergy code get synergy students
        $synHandler = new External_Class();
        $studentList = $synHandler->getStudents($category['code']);

		for ($x=0; is_array($studentList[$x]);$x++){
			$user = $userHandler->getBySynergy($studentList[$x]['synergy_id']);
			$studentList[$x]['uid'] = $user['uid'];
		}
    }
    else {
        //Retrieve student list from group membership
        $groupHandler = new Group;
        $studentList = $groupHandler->getMemberList($this->arg[0]);
    }

	$subList = $subHandler->getAllForUserCategory($this->arg[0], $_SESSION['id']);

	$this->setVar('sub_head_block', '');
	$this->setVar('assess_head_block', '');

	$this->setVar('no_submissions_block', '');
	$this->setVar('no_assessments_block', '');

	if (is_array($subList)) {
		foreach($subList as $sub) {
            $this->setVar(array(
				'BOX_ID' => $sub['id'],
				'SUB_NAME' => Scrub::text($sub['name']),
				'SUB_TYPE' => Scrub::text($sub['typeName'])
            ));

            if ($sub['type_id'] == Assess::ONLINE_ASSESSMENT_TYPE_ID) {

                /* Perform automated marking on responses which support this feature. Only
                 * mark responses which haven't been automarked previously, which allows
                 * teachers to customise response marks without being overwritten again.
                 *
                 * Once automated marking has been performed, remember which responses were
                 * affected so we don't refetch them the next time the page loads.
                 */
                $allAttempts = $attemptHandler->getList($sub['id']);

                if (is_array($allAttempts)) {
                    foreach($allAttempts as $currentAttempt) {
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

				$this->parse('assess_head_block','assess_head', true);

            }
			else {
				$this->parse('sub_head_block','sub_head', true);

			}
		}
	}
	else {
		$this->setVar('sub_head_block', '');
	}

	if (is_array($studentList)) {
		foreach($studentList as $student) {

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

				$this->setVar('mark_block','');
				$this->setVar('onlineAssessmentMark_block','');

				if (is_array($subList)) {
					foreach($subList as $sub) {
						if ($sub['type_id'] == Assess::ONLINE_ASSESSMENT_TYPE_ID) {
							$firstSection = $sectionHandler->getFirst($sub['id']);
							$mostRecentAttempt = $attemptHandler->getLatestForUser($sub['id'], $student['uid']);
							$marks = $attemptHandler->getMarksForAttempt($mostRecentAttempt['attempt_id']);
							$total = $attemptHandler->getTotalMarks($sub['id'], $mostRecentAttempt['attempt_id']);

							$this->setVar(array(
								'ASSESS_ASSESS_ID' => $sub['id'],
								'ASSESS_FIRST_SECTION_ID' => $firstSection['section_id'],
								'ATTEMPT_ID' => $mostRecentAttempt['attempt_id'],
								'MARK' => ($marks && $total) ? number_format($marks, 2) . ' / ' . number_format($total, 2)  : '',
								'BOX_ID' => ''
							));

							$this->setVar('mark_block','');
							$this->parse('onlineAssessmentMark_block', 'onlineAssessmentMark');
						}
						else {
							/* If a UID was found for the student, try to get his
							 * submissions. Otherwise skip him (this occurs when he
							 * exists in Synergetic but not in schoolbox)
							 */
							if (is_numeric($student['uid'])) {
								$return = $retHandler->getByUser($student['uid'],$sub['id']);

								$this->setVar(array(
									'MARK' => Scrub::text($return[0]['mark']),
									'BOX_ID' => $sub['id'],
								));
							}

							$this->parse('mark_block','mark');
							$this->setVar('onlineAssessmentMark_block', '');
						}

						$this->parse('markItem_block', 'markItem', true);
						$this->setVar('mark_block', '');
						$this->setVar('onlineAssessmentMark_block', '');
					}
				}
				else {
					$this->setVar('mark_block','');
					$this->setVar('onlineAssessmentMark_block', '');
				}

				$this->parse('student_list_block', 'student_list', true);
				$this->setVar('markItem_block', '');
			}
		}
	}
	else {
		$this->setVar('student_list_block', '');
    }
}
else {
	error(2);
}
