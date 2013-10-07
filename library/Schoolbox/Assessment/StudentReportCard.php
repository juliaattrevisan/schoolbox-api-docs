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

class StudentReportCard {

	// available permissions
	const CAN_SUBMIT = 0;
	const CAN_PUBLISH = 1;
	const CAN_EMAIL_TEACHER = 2;
	const CAN_VIEW_CLASS_AVERAGE = 3;
	const CAN_VIEW_SHOWCASE = 4;
	const CAN_VIEW_OVERALL_GRADE = 5;

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
	protected $categoriesInSemester = array();

	/**
	 * @var SubjectReportCard
	 */
	protected $subjectData;

	// mappers
	protected $submissionMapper;
	protected $showcaseMapper;
	protected $assessMapper;

	// 'andlers
	protected $groupHandler;

	public function __construct($studentUserId, $viewingAs = -1) {
		// parse arguments
		$this->viewingUserId = $viewingAs == -1 ? $_SESSION['id'] : $viewingAs;
		$this->targetUserId = $studentUserId;

		// dependencies
		$this->submissionMapper = new SubmissionBoxMapper();
		$this->showcaseMapper = new ShowcaseMapper();
		$this->assessMapper = new OnlineAssessmentMapper();
		$this->groupHandler = new \Group();

		// build permissions set
		$this->establishPermissions();

		// setup helper vars
		$this->currentYear = date('Y');
		$this->currentSemester = date('m') > 6 ? 2 : 1;
	}

	/**
	 * Establish permissions that the current combination of viewing-as and target user allows
	 */
	protected function establishPermissions() {

		// is the class average graph line displayed?
		$displayClassAverage        = \Cfg::get('display_grade_average') ? true : false;

		// are parents barred from this page?
		$restrictStudentSubmissions = \Cfg::get('restrict_student_submissions') ? true : false;

		// is the grade average allowed to be shown?
		$showOverallGrade           = \Cfg::get('show_overall_grade') ? true : false;


		// if viewing own, all permissions enabled
		if($this->viewingUserId == $this->targetUserId) {
			$this->permissionSet = array(true, true, true, $displayClassAverage, true, $showOverallGrade);
			$this->minimumVisibility = SubmissionBox::MARK_VISIBILITY_STUDENT;
		}

		// if superuser, all permissions enabled
		elseif($this->groupHandler->isSuperuser($this->viewingUserId)) {
			$this->permissionSet = array(true,true,true,true,true);
			$this->minimumVisibility = SubmissionBox::MARK_VISIBILITY_PRIVATE;
		}

		// if staff, all READ permissions enabled
		elseif($this->groupHandler->isStaff($this->viewingUserId)) {
			$this->permissionSet = array(false, false, true, true, true, true);
			$this->minimumVisibility = SubmissionBox::MARK_VISIBILITY_PRIVATE;
		}

		// if parent of child, use Cfg::get('restrict_student_submissions') for READ permissions (no WRITE)
		elseif($this->groupHandler->isParentOfChild($this->targetUserId)) {
			$this->permissionSet = array(
				false, false, !$restrictStudentSubmissions, false, !$restrictStudentSubmissions,
				(!$restrictStudentSubmissions) && $showOverallGrade
			);
			$this->minimumVisibility = SubmissionBox::MARK_VISIBILITY_PARENT_STUDENT + ($restrictStudentSubmissions ? 1 : 0);
		}

		// all other cases, no permissions granted
		else {
			$this->permissionSet = array(false, false, false, false, false, false);
			$this->minimumVisibility = 3; // max visibility is 2, so this will never be seen
		}
	}

	/**
	 * Check how a particular permission is set for the viewing user
	 *
	 * @param $action
	 * @return bool
	 */
	protected function checkPermission($action) {
		if(isset($this->permissionSet[$action]) && $this->permissionSet[$action] === true) {
			return true;
		}
		return false;
	}

	/**
	 * Defining the semester will self-populate with the subjects available to this semester
	 *
	 * @param $year
	 * @param $semester
	 * @throws \InvalidArgumentException
	 */
	public function setSemester($year, $semester) {
		if($year < 2000 || $year > 9999) {
			throw new \InvalidArgumentException("Invalid year '$year'");
		}
		if($semester < 1 || $semester > 2) {
			throw new \InvalidArgumentException("Invalid semester '$semester'");
		}

		// set class vars
		$this->targetYear = $year;
		$this->targetSemester = $semester;

		// populate subjects in this semester
		$this->findSubjectsForSemester();
	}

	/**
	 * Defining the subject will self-populate with the data available for the subject
	 *
	 * @param $subjectId
	 */
	public function setSubject($subjectId) {
		$this->setSubjectWithinSemester($subjectId, 0, 0);
	}

	public function setSubjectWithinSemester($subjectId) {
		$this->targetCategoryId = $subjectId;

		// populate data for the subject using the category and user
		$duework_visibilities = array(
			Component\SubmissionBox::MARK_VISIBILITY_PARENT_STUDENT,
		);
		if ($this->groupHandler->isStudent()) {
			$duework_visibilities[] = Component\SubmissionBox::MARK_VISIBILITY_STUDENT;
		}
		elseif ($this->groupHandler->isStaff() || $this->groupHandler->isSuperuser()) {
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
	 * @param string $template
	 * @param string $tab
	 */
	public function renderPage(\SitePage $page, $template = 'submission/allWork.html', $tab = 'submission') {

		// visibility minimum is too high, can't see anything
		if($this->minimumVisibility > SubmissionBox::MARK_VISIBILITY_PARENT_STUDENT) {
			error(19);
		}

		$page->setFile(TEMPLATE_content, $template);
		$page->setTab($tab);

		// global blocks
		$page->setBlock(TEMPLATE_content, 'subject', 'subject_block');

		// global vars
		$page->setVar(array(
			'WORK_USER_ID' => $this->targetUserId
		));

		$this->renderSemesterSelectors($page);
		$this->renderSubjectSelectors($page);

		// only attempt to render a subject's details if a subject has actually been selected
		if($this->hasSubjectSelected()) {
			$this->renderSubject($page);
		}
		else {
			$this->renderOverview($page);
		}
	}

	/**
	 * Renders the selectors for the semester
	 *
	 * @param \SitePage $page
	 */
	protected function renderSemesterSelectors(\SitePage $page) {
		$page->setBlock(TEMPLATE_content, 'semester_option', 'semester_option_block');
		$page->setBlock(TEMPLATE_content, 'year_option', 'year_option_block');

		// Semesters can be hardcoded, no need to look anything up (may change if a school wants Term breakdowns rather than Semester)
		$semesters = array(1, 2);
		foreach($semesters as $sem) {
			$page->setVar(array(
				'SEMESTER_SELECTED' => $sem == $this->targetSemester ? ' selected="selected"' : '',
				'SEMESTER_VALUE'    => $sem
			));
			$page->parse('semester_option_block','semester_option', true);
		}

		// Years is trickier, because we first need to find out all the years in which the student in question has submitted work
		// also, merging in this current year is a tad unfortunate
		$years = array_unique(
			array_merge(
				array($this->currentYear),
				$this->submissionMapper->getAllYearsByUser($this->targetUserId)
			)
		);
		foreach($years as $y) {
			$page->setVar(array(
				'YEAR_SELECTED' => $y == $this->targetYear ? ' selected="selected"' : '',
				'YEAR_VALUE'    => $y
			));
			$page->parse('year_option_block', 'year_option', true);
		}

		$page->setVar(array(
			'WORK_YEAR' => $this->targetYear,
			'WORK_SEMESTER' => $this->targetSemester
		));

	}

	/**
	 * Renders the selectors for the subjects
	 *
	 * @param \SitePage $page
	 * @throws \InvalidArgumentException
	 */
	protected function renderSubjectSelectors(\SitePage $page) {
		if(!$this->targetYear || !$this->targetSemester) {
			throw new \InvalidArgumentException("Select a semester to display");
		}

		$page->setBlock(TEMPLATE_content, 'subject_selector', 'subject_selector_block');

		if(!count($this->categoriesInSemester)) {
			$page->setVar('subject_selector_block', "No subjects");
			return;
		}

		foreach($this->categoriesInSemester as $catId => $cat) {
			// <li data-id="{ENR_SUBJ_ID}" class="{ENR_SUBJ_SELECTED}" title="{ENR_SUBJ_CODE}">{ENR_SUBJ_NAME}</li>
			$page->setVar(array(
				'ENR_SUBJ_ID'       => $catId,
				'ENR_SUBJ_SELECTED' => $catId == $this->targetCategoryId ? ' selected="selected"' : '',
				'ENR_SUBJ_CODE'     => $cat['code'] ? $cat['code'] : $cat['name'],
				'ENR_SUBJ_NAME'     => $cat['name'],
			));
			$page->parse('subject_selector_block', 'subject_selector', true);
		}
	}

	protected function renderSubject(\SitePage $page, $components = array()) {

		if(empty($components)) {
			$components = array('teachers','gradeAverage','classGraph','criteria','assessments');
		}

		if(!$this->subjectData instanceof SubjectReportCard) {
			throw new \InvalidArgumentException("No subject selected");
		}

		// subject globals
		$page->setVar(array(
			'SUBJ_ID'       => $this->subjectData->getCategory()->getId(),
			'SUBJ_NAME'     => $this->subjectData->getCategory()->getName(),
			'SUBJ_SEMESTER' => "Semester {$this->targetSemester}, {$this->targetYear}",
		));

		// iterate the components and output them if we know how
		foreach($components as $c) {
			$m = 'render'.ucfirst($c);
			if(method_exists($this, $m)) {
				$this->$m($page);
			}
		}

		$page->parse('subject_block', 'subject');
	}

	protected function renderTeachers(\SitePage $page) {

		// output teachers
		$page->setBlock('subject', 'subject_teacher', 'subject_teacher_block');
		$teachers = $this->subjectData->getTeachers();

		if(count($teachers)) {
			foreach($teachers as $teacher) {
				/* @var $teacher \Schoolbox\User */
				$page->setVar(array(
					'SUBJ_TCH_ID' => $teacher->getId(),
					'SUBJ_TCH_EXTID' => $teacher->getExternalId(),
					'SUBJ_TCH_NAME' => $teacher->formatName(),
					'SUBJ_TCH_MAILTO' => $this->checkPermission(self::CAN_EMAIL_TEACHER) ? $teacher->getEmail() : ''
				));

				$page->parse('subject_teacher_block', 'subject_teacher', true);
			}
		}
		else {
			$page->setVar('subject_teacher_block', '');
		}

	}

	protected function renderGradeAverage(\SitePage $page) {
		$page->setBlock('subject', 'grade_average', 'grade_average_block');

		// if you can't see the grade average...you, well, can't see the grade average
		if(!$this->checkPermission(self::CAN_VIEW_OVERALL_GRADE)) {
			$page->setVar('grade_average_block', '');
			return;
		}

		// output student grade average
		$gradeAvg = $this->subjectData->getGradeAverage();

		if(is_null($gradeAvg['grade'])) {
			$page->setVar(array(
				'SUBJ_GRADEIENT'   => '',
				'SUBJ_GRADE_AVG'   => 'N/A',
				'SUBJ_GRADE_TREND' => ''
			));
			$page->parse('grade_average_block', 'grade_average');
			return;
		}

		if ($gradeAvg['trend'] > 0) {
			$trendDir = '&#x25B2;';
		} elseif ($gradeAvg['trend'] < 0) {
			$trendDir = '&#x25BC;';
		}
		else {
			$trendDir = '';
		}

		$returnHandler = new \SubmissionReturn();

		$page->setVar(array(
			'SUBJ_GRADEIENT' => (isset($gradeAvg['grade']) ? (int)($gradeAvg['grade'] / 10) : ''),
			'SUBJ_GRADE_AVG' => $returnHandler->percent2letter($gradeAvg['grade']),
			'SUBJ_GRADE_TREND' => $gradeAvg['trend'] != 0 ? sprintf("%0.1f%% %s", $gradeAvg['trend'], $trendDir) : ''
		));

		$page->parse('grade_average_block', 'grade_average');

	}

	protected function renderClassGraph(\SitePage $page) {
		$colours = array(
			'user'    => '#579DCF',
			'average' => '#E99D12',
//			'trend'   => '#3DB114',
		);

		$averageSeries = array();
		$userSeries = array();

		$data = $this->subjectData->getFullClassHistory();

		// split into separate series, and mess around with php's loose typing
		foreach($data as $stamp => $d) {
			if($this->canViewMark($d['visibility'])) {
				if(!is_null($d['average_mark'])) {
					$averageSeries[] = array($stamp * 1000, sprintf("%0.2f", $d['average_mark']) * 1);
				}

				if(!is_null($d['user_mark'])) {
					$userSeries[] = array($stamp * 1000, sprintf("%0.2f", $d['user_mark']) * 1);
				}
			}
		}

		usort($averageSeries, function($a, $b) {
			return $a[0] < $b[0] ? $a[0] > $b[0] ? -1 : 1 : 0;
		});

		usort($userSeries, function($a, $b) {
			return $a[0] < $b[0] ? $a[0] > $b[0] ? -1 : 1 : 0;
		});

		$graph = array();

		// if permission to see class average, include the data series
		if($this->checkPermission(self::CAN_VIEW_CLASS_AVERAGE)) {
			$graph[] = array(
				'label' => 'Class',
				'data'  => $averageSeries
			);
		}
		// otherwise, remove the colour from the map (so the graph doesn't get confused)
		else {
			unset($colours['average']);
		}

		// add the user to the graph second, so it appears on top
		$graph[] = array(
			'label' => 'You',
			'data'  => $userSeries
		);

		// display the graph for the semester
		$start = mktime(0, 0, 0, ($this->targetSemester - 1) * 6 + 1, 0, $this->targetYear) * 1000;
		// add 6 months - 1 day
		$end = $start + ((365 / 2 - 1) * 86400000);

		$page->setVar(array(
			'GRAPH_JSON'    => json_encode($graph),
			'GRAPH_COLOURS' => json_encode(array_values($colours)),
			'GRAPH_XMIN'    => $start,
			'GRAPH_XMAX'    => $end
		));
	}

	protected function renderCriteria(\SitePage $page) {

		// output rubric criteria
		$page->setBlock('subject', 'subject_crit', 'subject_crit_block');
		$page->setBlock('subject_crit', 'subject_crit_group', 'subject_crit_group_block');
		$page->setBlock('subject_crit_group', 'subject_crit_item', 'subject_crit_item_block');

		// list of criteria in this category
		$criteria = $this->subjectData->getCriteria();

		if(count($criteria)) {

			// there are criteria, retrieve the criterion-grouped scores for the student, map, and output
			$rubricHandler = new \DueWork_Rubric_Handler();
			$returnHandler = new \SubmissionReturn();
			$results = $rubricHandler->getStudentMarksByCriteriaInCategory($this->targetUserId, $this->targetCategoryId);

			$page->setVar('subject_crit_group_block', '');
			foreach($criteria as $group => $items) {
				$page->setVar('subject_crit_item_block', '');
				$page->setVar('CRIT_GROUP_NAME', $group);
				foreach($items as $item) {
					$score = isset($results[$item['id']])
						? $returnHandler->percent2letter($results[$item['id']])
						: 'Not Assessed';
					$page->setVar(array(
						'CRIT_ITEM_NAME' => $item['name'],
						'CRIT_ITEM_SCORE' => $score
					));
					$page->parse('subject_crit_item_block', 'subject_crit_item', true);
				}
				$page->parse('subject_crit_group_block', 'subject_crit_group', true);
			}
			$page->parse('subject_crit_block', 'subject_crit', false);
		}
		else {
			$page->setVar('subject_crit_block', '');
		}

	}

	protected function renderAssessments(\SitePage $page) {
		// output assessments

		$page->setBlock('subject', 'work', 'work_block');
		$page->setBlock('work', 'work_actionable', 'work_actionable_block');
		$page->setBlock('work', 'work_publishable', 'work_publishable_block');

		$work = $this->subjectData->getAssessments();


		if(count($work)) {
			foreach($work as $w) {

				$object = $w['object'];
				$marks = $w['marks'];

				// clear all template vars
				$page->setVar(array(
					'ON_OFF' => '', 'WORK_ID' => '', 'WORK_TYPE' => '', 'WORK_DATE' => '', 'WORK_TIME' => '',
					'WORK_TITLE' => '', 'WORK_URGENCY' => '', 'WORK_GRADIENT' => '', 'WORK_GRADE' => '',
					'WORK_COMMENT' => '', 'WORK_HREF' => '', 'WORK_BTN_ICON' => '', 'WORK_BTN_LABEL' => '',
					'WORK_PUBLISH_ACTION' => '', 'WORK_PUBLISH_BTN_ICON' => '', 'WORK_PUBLISH_BTN_LABEL' => '',
					'work_actionable_block' => '', 'work_publishable_block' => ''
				));

				$existingShowcasedItem = null;

				if($object instanceof SubmissionBox) {
					$page->setVar($this->outputDueWorkRow($object, $marks));

					// is the viewing user allowed to [un]publish?
					if($this->checkPermission(self::CAN_PUBLISH)) {
						// allowed to perform publish actions

						// retrieve any publishing of this SB
						$existingShowcasedItem = $this->showcaseMapper->findByColumnList(array(
							'uid' => $this->targetUserId,
							'submission_box_id' => $object->getId()
						));

						// has this already been published?
						if(count($existingShowcasedItem)) {
							// yes: offer unpublish action
							$output['WORK_PUBLISH_ID']        = $existingShowcasedItem->first()->getId();
							$output['WORK_PUBLISH_TYPE']      = 'showcaseItemId';
							$output['WORK_PUBLISH_ACTION']    = 'unpublish';
							$output['WORK_PUBLISH_BTN_ICON']  = 'download';
							$output['WORK_PUBLISH_BTN_LABEL'] = 'Unpublish';
							$page->setVar($output);
							$page->parse('work_publishable_block', 'work_publishable');
						}
						else {
							// not published

							// has a mark been returned?
							if(array_key_exists('mark', $marks)) {
								// mark returned: offer publish action
								$output['WORK_PUBLISH_ID']        = $page->getVar('WORK_ID');
								$output['WORK_PUBLISH_TYPE']      = 'boxId';
								$output['WORK_PUBLISH_ACTION']    = 'publish';
								$output['WORK_PUBLISH_BTN_ICON']  = 'up-arrow';
								$output['WORK_PUBLISH_BTN_LABEL'] = 'Publish';
								$page->setVar($output);
								$page->parse('work_publishable_block', 'work_publishable');
							}
							else {
								// no mark returned: do not offer any action
								$page->setVar('action_publish_block', '');
							}
						}
					}
					else {
						// not allowed: do not offer actions
						$page->setVar('action_publish_block', '');
					}
				}
				elseif($object instanceof OnlineAssessment) {
					$page->setVar($this->outputOnlineAssessRow($object, $marks));

					// OA's cannot be published at the moment, refactor DueWork publish code if they become publishable
					$page->setVar('action_publish_block', '');
				}
				else {
					$page->setVar('work_publishable_block', '');
				}

				// if work actions are available, render the block
				if($page->getVar('WORK_BTN_ICON')) {
					$page->parse('work_actionable_block', 'work_actionable');
				}
				// otherwise hide it
				else {
					$page->setVar('work_actionable_block', '');
				}

				$page->parse('work_block', 'work', true);
			}
		}
		else {
			$page->setVar('work_block', '<td colspan="5">No assessments for this subject</td>');
		}

	}

	/**
	 * @param SubmissionBox $box
	 * @param $marks
	 * @return array
	 */
	protected function outputDueWorkRow(SubmissionBox $box, $marks) {
		$due = new \Alaress_Date_Relative($box->getDueDate());
		$urgency = max(0, min(10, (int)(($due->getUnixTimestamp() - time()) / 86400)));
		$output = array(
			'WORK_TITLE'   => $box->getName(),
			'WORK_ID'      => $box->getId(),
			'WORK_TYPE'    => $box->getWorkType()->getName(),
			'WORK_DATE'    => $due->getRelativeTime(),
			'WORK_TIME'    => $due->getDateOnly(),
			'WORK_URGENCY' => $urgency,
			'WORK_HREF'    => 'submission/submissionHistory/',
		);

        // marks have been returned
		if($marks['attempts'] > 0) {
			$output['WORK_URGENCY']  = 'none';
		}

		if($marks && $this->canViewMark($box)) {
			$output['WORK_GRADIENT'] = (isset($marks['mark']) ? (int)($marks['mark'] / 10) : '');
			$output['WORK_GRADE']    = $marks['display_mark'];
			$output['WORK_COMMENT']  = $marks['comment'];
		}

		// if the viewing user does not have permission to submit, blank out the actions
		if(!$this->checkPermission(self::CAN_SUBMIT)) {
			$output['WORK_BTN_ICON']  = '';
			$output['WORK_BTN_LABEL'] = '';
		}
        // is the box still open?
        elseif($box->getLateDate()->getUnixTimestamp() > time()) {
            // can potentially submit

            // is work already submitted?
            if($marks['attempts'] > 0) {
                // work submitted

                // is this multisubmit?
                if($box->getAcceptMultiple()) {
                    // is multi: offer REsubmission
                    $output['WORK_BTN_ICON']  = 'up-arrow';
                    $output['WORK_BTN_LABEL'] = 'Resubmit';
                }
                else {
                    // is single-submit: offer no action
                    $output['WORK_BTN_ICON']  = '';
                    $output['WORK_BTN_LABEL'] = '';
                }
            }
            else {
                // work not submitted: offer submission
                $output['WORK_BTN_ICON']  = 'up-arrow';
                $output['WORK_BTN_LABEL'] = 'Submit';
            }
        }
        else {
            // cannot submit: offer no action
            $output['WORK_BTN_ICON']  = '';
            $output['WORK_BTN_LABEL'] = '';
        }

		return $output;
	}

	/**
	 * @param OnlineAssessment $oa
	 * @param $marks
	 * @return array
	 */
	protected function outputOnlineAssessRow(OnlineAssessment $oa, $marks) {
		// marks are contained inside a single-element array, pull it out of itself
		$marks = $marks[0];

		$due = new \Alaress_Date_Relative($oa->getFinish());
		$urgency = max(0, min(10, (int)(($due->getUnixTimestamp() - time()) / 86400)));
		$output = array(
			'WORK_TITLE'   => $oa->getTitle(),
			'WORK_ID'      => $marks ? "{$oa->getId()}/{$marks['section_id']}/{$marks['attempt_id']}" : $oa->getId(),
			'WORK_TYPE'    => $oa->getWorkType()->getName(),
			'WORK_DATE'    => $due->getRelativeTime(),
			'WORK_TIME'    => $due->getDateOnly(),
			'WORK_URGENCY' => $urgency,
			'WORK_HREF'    => $marks ? "assess/studentReview/" : 'assess/intro/',
		);

		// if the viewing user does not have permission to submit, blank out the actions
		if(!$this->checkPermission(self::CAN_SUBMIT)) {
			$output['WORK_BTN_ICON']  = '';
			$output['WORK_BTN_LABEL'] = '';
		}
		// is the OA still open?
		elseif($oa->getFinish()->getUnixTimestamp() > time()) {
            // still open

            // has the student attempted?
            if($marks['attempts'] > 0) {
                // student has attempted at least once

                // does the student have attempts left?
                if($oa->getMaxAttempts() == 0 || $oa->getMaxAttempts() > $marks['attempts']) {
                    // attempts remaining: offer REsit action
                    $output['WORK_BTN_ICON']  = 'modify';
                    $output['WORK_BTN_LABEL'] = 'Resit';
                }
                else {
                    // no attempts remaining: offer no action
                    $output['WORK_BTN_ICON']  = '';
                    $output['WORK_BTN_LABEL'] = '';
                }
            }
            else {
                // student has not attempted: offer sit action
                $output['WORK_BTN_ICON']  = 'modify';
                $output['WORK_BTN_LABEL'] = 'Sit';
            }
        }
        else {
            // is closed: offer no action
            $output['WORK_BTN_ICON']  = '';
            $output['WORK_BTN_LABEL'] = '';

        }

		if($marks['attempts'] > 0) {
			$output['WORK_URGENCY']  = 'none';
		}
		if($marks) {
			$output['WORK_GRADIENT'] = (isset($marks['mark']) ? (int)($marks['mark'] / 10) : '');
			$output['WORK_GRADE']    = $marks['display_mark'];
			$output['WORK_COMMENT']  = $marks['comment'];
		}

		return $output;
	}

	/**
	 * No subject selected, so display an overview of the semester instead
	 *
	 * @param \SitePage $page
	 */
	protected function renderOverview(\SitePage $page) {
		// for now, this is just "please select a subject"
		$page->setVar('subject_block', 'Please select a subject to view.');
	}


	public function hasSubjectSelected() {
		return $this->targetCategoryId ? true : false;
	}

	protected function findSubjectsForSemester() {
		if($this->targetYear == $this->currentYear && $this->targetSemester == $this->currentSemester) {
			$categoryList = $this->groupHandler->involvedInCategoryList($this->targetUserId, true, READ_ACCESS);

			// filter this list, so that it only contains categories in which contain results
			foreach($categoryList as $k => $cat) {
				$count = 0;
				// is there any other way to do this? such that count(db_calls) < 2n
				$count += $this->submissionMapper->findByColumnList(
					array('category' =>  $cat['id'], 'task_id' => null)
				)->count();
				$count += $this->assessMapper->findByColumnList(
					array('category_id' =>  $cat['id'], 'task_id' => null)
				)->count();
				if(!$count) {
					unset($categoryList[$k]);
				}
			}
		}
		else {
			$categoryList = $this->submissionMapper->submittedToCategoryList(
				$this->targetUserId,
				$this->targetYear,
				$this->targetSemester
			);
		}

		if (is_array($categoryList)) {
			foreach($categoryList as $category) {
				$this->categoriesInSemester[$category['id']] = $category;
			}
		}
	}

	/**
	 * @param SubmissionBox|int $var
	 * @return bool
	 */
	protected function canViewMark($var) {
		if($var instanceof SubmissionBox) {
			return $var->getMarkVisibility() >= $this->minimumVisibility;
		}
		else {
			return $var >= $this->minimumVisibility;
		}
	}

}