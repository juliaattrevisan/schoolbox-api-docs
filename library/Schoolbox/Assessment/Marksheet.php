<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Assessment;

use Schoolbox\Assessment\Component\OnlineAssessment;
use Schoolbox\Assessment\Component\OnlineAssessmentMapper;
use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\Assessment\Component\SubmissionBoxMapper;
use Schoolbox\Collection;
use Schoolbox\IConfigLoader;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Assessment\Exception as AssessmentException;
use Schoolbox\Resource\Exception as ResourceException;
use Schoolbox\Resource\Category;
use Schoolbox\Resource\CategoryMapper;
use Schoolbox\User;
use Schoolbox\UserMapper;

/**
 * This class relies on handlers, because there isn't time to
 * implement the necessary functionality into mappers at this point.
 *
 * Class Marksheet
 * @package Schoolbox\Assessment
 */
class Marksheet {

	/** @var Category */
	protected $category;
	/** @var User */
	protected $sessionUser;

	/** @var \User */
	protected $userHandler;
	/** @var \Group */
	protected $groupHandler;
	/** @var \Category */
	protected $categoryHandler;
	/** @var \External_Class */
	protected $synergyHandler;

	/** @var \Submission */
	protected $submissionHandler;
	/** @var \SubmissionReturn */
	protected $returnHandler;
	/** @var \Marksheet_Factory */
	protected $marktypeFactoryHandler;

	/** @var \Assess_Factory */
	protected $assessFactoryHandler;
	/** @var \Assess_Main */
	protected $assessHandler;
	/** @var \Assess_Attempt */
	protected $attemptHandler;
	/** @var \Assess_Section */
	protected $sectionHandler;
	/** @var \Assess_Response */
	protected $responseHandler;

	/** @var CategoryMapper */
	protected $categoryMapper;
	/** @var UserMapper */
	protected $userMapper;
	/** @var OnlineAssessmentMapper */
	protected $oaMapper;
	/** @var SubmissionBoxMapper */
	protected $sbMapper;

	/** @var IConfigLoader */
	protected $conf;

	/** @var array */
	protected $studentDataSet;
	/** @var array */
	protected $statisticDataSet;
	/** @var array */
	protected $displayOrder;

	/** @var int */
	protected $year;
	/** @var int */
	protected $semester;


	/**
	 * @param int $categoryId
	 * @param int $sessionUserId
	 * @param array $handlerDependencies
	 * @param array $mapperDependencies
	 * @param $conf
	 * @throws AssessmentException
	 * @throws ResourceException
	 */
	public function __construct($categoryId, $sessionUserId, $handlerDependencies, $mapperDependencies, $conf) {

		$this->conf = $conf;

		// ultimately, this class should be refactored to replace handler dependencies with mapper dependencies
		foreach($handlerDependencies as $key => $handler) {
			$prop = $key.'Handler';
			if(property_exists($this, $prop)) {
				$this->$prop = $handler;
			}
		}

		foreach($mapperDependencies as $key => $mapper) {
			$prop = $key.'Mapper';
			if(property_exists($this, $prop)) {
				$this->$prop = $mapper;
			}
		}

		if(!$this->groupHandler->isStaff()) {
			throw new AssessmentException('Permission Denied', 19);
		}

		$this->category = $this->categoryMapper->findById($categoryId);
		$this->sessionUser = $this->userMapper->findById($sessionUserId);

	}

	/**
	 * Generate the data from the given category and session user
	 */
	public function generateData($year, $semester) {

		$catId = $this->category->getId();
		$this->year = $year;
		$this->semester = $semester;

		if($this->category->getCodes()->count() && $this->conf->get('external_enable')) {
			$studentList = $this->synergyHandler->getStudents($this->category->getCodes()->first()->getCode());

			for ($x = 0; is_array($studentList[$x]); $x++) {
				$user = $this->userHandler->getBySynergy($studentList[$x]['synergy_id']);

				/* If the Synergetic user wasn't found in the portal, just remove
					* them from the list. Otherwise the future queries about submitted
					* work will break, because there are no uids.
					*/
				if (!$user) {
					unset($studentList[$x]);
				}
				else {
					$studentList[$x]['uid']        = $user['uid'];
					$studentList[$x]['synergy_id'] = $user['synergy_id'];
				}
			}
		}
		else {
			$studentList = $this->groupHandler->getMemberList($this->category->getId());
		}

		$workList = $this->submissionHandler->getAllForUserCategory($catId, null, $year, $semester);

		if(is_array($workList)) {
			$statData = array();
			$order = array();

			foreach($workList as $work) {
				$isOA = $work['type_id'] == \Assess_Main::ONLINE_ASSESSMENT_TYPE_ID ? 1 : 0;

				if($isOA) {
					// automark if OA
					$this->automarkOnlineAssessment($work['id']);
					$mapper = $this->oaMapper;
					$mtSlug = 'raw';
				}
				else {
					$mapper = $this->sbMapper;
					$mtSlug = $work['slug'];
				}

				$stats = $mapper->getStatistics($work['id']);
				$object = $mapper->findById($work['id']);
//				$weight = $object->getWeight();

				$progress = $mapper->getProgress($work['id']);

				// use the accepts_submissions "flag" to determine this field's output
				if(!$progress['accepts_submissions']) {
					$progress['submission_count'] = '&ndash;';
				}
				unset($progress['accepts_submissions']);

				// this is a bit hacky, but it concisely gets the data into the desired format
				foreach($progress as &$p) {
					if(is_numeric($p)) {
						$p .= ' / ' . count($studentList);
					}
				}

				if(is_null($stats['mean'])) {
					$classAvg = null;
				}
				else {
					$classAvg = $this->marktypeFactoryHandler->build($mtSlug)->convertRawMark($stats['mean']);
				}


				$statData[$isOA][$work['id']] = array_merge(
					array(
						'avg' => $classAvg,
//						'weight' => $weight
					),
					$stats,
					$progress
				);

				/** @var $end \Alaress_Date_Format */
				$end = $isOA ? $object->getFinish() : $object->getDueDate();
				$stamp = $end->getUnixTimestamp();

				// effectively, this sets the order of the columns by chronology, then order created if collisions
				while(array_key_exists($stamp, $order)) {
					++$stamp;
				}
				$order[$stamp] = array(
					'isOA' => $isOA,
					'id' => $work['id'],
					'object' => $object
				);
				ksort($order);

			}
			unset($work);

			$this->statisticDataSet = $statData;
			$this->displayOrder = $order;
		}

		if(is_array($studentList)) {
			foreach($studentList as $student) {

				$userId = $student['uid'];

				// exclude if not a a valid user
				try {
					$row['student'] = $this->userMapper->findById($student['uid']);
				}
				catch(MapperException $e) {
					continue;
				}

				if(is_array($workList)) {
					foreach($workList as $work) {
						$isOA = $work['type_id'] == \Assess_Main::ONLINE_ASSESSMENT_TYPE_ID ? 1 : 0;

						if($isOA) {
							$mark = $this->getOnlineAssessMarkForStudent($work['id'], $userId);
						}
						else {
							$mark = $this->getSubmissionBoxMarkForStudent($work['id'], $userId);
						}
						$row['marks'][$isOA][$work['id']] = $mark;
					}
					unset($work);
				}

				$this->studentDataSet[$userId] = $row;
			}
			unset($student);
		}
	}


	/**
	 * Automatically marks the given OA.
	 *
	 * @param $assessId
	 */
	protected function automarkOnlineAssessment($assessId) {
		$allAttempts = $this->attemptHandler->getList($assessId);

		if (is_array($allAttempts)) {
			foreach ($allAttempts as $currentAttempt) {
				$allResponses = $this->responseHandler->getAutomarkableResponses($currentAttempt['attempt_id']);

				if (is_array($allResponses)) {
					foreach ($allResponses as $currentResponse) {
						$answerHandler = $this->assessFactoryHandler->createAnswerModel($currentResponse['type_id']);

						$answerHandler->mark($currentResponse);

						$markedResponseIDs[] = $currentResponse['response_id'];
					}
				}
			}

			$this->responseHandler->updatedAutomatedMarks($markedResponseIDs);
		}
	}


	/**
	 * Calculate the mark for the most recent attempt of the given OA by the given student
	 *
	 * @param int $assessId
	 * @param int $studentId
	 * @return array
	 */
	protected function getOnlineAssessMarkForStudent($assessId, $studentId) {
		$mostRecentAttempt = $this->attemptHandler->getLatestForUser($assessId, $studentId);
		$marks = $this->attemptHandler->getMarksForAttempt($mostRecentAttempt['attempt_id']);
		$total = $this->attemptHandler->getTotalMarks($assessId, $mostRecentAttempt['attempt_id']);

		$output = array('mark' => '', 'norm_mark' => '');

		if(!$total) {
			return $output;
		}

		$output['mark'] = (float)(number_format($marks, 2)) . ' / ' . (float)(number_format($total, 2));
		$output['norm_mark'] = $marks / $total;

		return $output;
	}

	protected function getSubmissionBoxMarkForStudent($boxId, $studentId) {
		$box = $this->submissionHandler->getByID($boxId);
		$marktype = $this->marktypeFactoryHandler->build($box['mark_type_slug']);
		$mark = $marktype->retrieveMark($studentId, $boxId, false, true);

		$pretty = $marktype->convertRawMark($mark['norm_mark']);

		return array(
			'norm_mark' => $mark['norm_mark'],
			'mark' => $pretty,
		);
	}


	/**
	 * Render the table in the given sitepage
	 *
	 * @param \SitePage $page
	 */
	public function renderSitepage($page) {

//		var_dump($this->statisticDataSet);
//		var_dump($this->studentDataSet);
//		var_dump($this->displayOrder);




		$page->setFile(TEMPLATE_content, 'submission/markSheet.html');
		$page->setTitle('Marksheet for '.$this->category->getName());
		$page->setTab('submission');

		// set global vars
		$page->setVar(array(
			'CATEGORY' => $this->category->getName(),
			'CID' => $this->category->getId(),
			'CATEGORY_CODE' => $this->category->getCodes()->count() ? $this->category->getCodes()->first()->getCode() : '',
			'WORK_YEAR' => $this->year,
			'WORK_SEMESTER' => $this->semester
		));

		// define blocks
		$page->setBlock(TEMPLATE_content, 'semester_option', 'semester_option_block');
		$page->setBlock(TEMPLATE_content, 'year_option', 'year_option_block');

		$page->setBlock(TEMPLATE_content, 'work_header', 'work_header_block');
		$page->setBlock(TEMPLATE_content, 'class_average', 'class_average_block');

		$page->setBlock(TEMPLATE_content, 'stat', 'stat_block');
		$page->setBlock('stat', 'stat_item', 'stat_item_block');
		$page->setBlock('stat_item', 'stat_editable', 'stat_editable_block');
		$page->setBlock('stat_item', 'stat_readonly', 'stat_readonly_block');

		$page->setBlock(TEMPLATE_content, 'student_row', 'student_row_block');
		$page->setBlock('student_row', 'student_work', 'student_work_block');
		$page->setBlock('student_row', '', '_block');


		// TODO iterate class averages and output (this is placeholding)
		$page->parse('class_average_block', 'class_average', true);
		$page->parse('class_average_block', 'class_average', true);
		$page->parse('class_average_block', 'class_average', true);
		$page->parse('class_average_block', 'class_average', true);
		$page->parse('class_average_block', 'class_average', true);

		// if mapping not defined here, uses ucfirst(:field)
		$statLabelLookup = array(
			'avg'               => 'Class Average',     // this is different to mean: same input value, but formatted according to the work marktype
			'stdev'             => 'Standard Deviation',
			'min'               => 'Minimum',
			'max'               => 'Maximum',
			'submission_count'  => 'Submitted',
			'return_count'      => 'Returned',
		);

		// stats that can be edited inline
		$writeable = array('weight');

		// transpose the data to output by row, not column, and output row headers while we're here
		$transposed = array();

		foreach($this->statisticDataSet as $isOA => $workList) {
			foreach($workList as $workId => $workData) {
				foreach($workData as $statName => $statValue) {
					$transposed[$statName]["$isOA-$workId"] = is_null($statValue) ? '&ndash;' : $statValue;
				}
			}
		}

		// iterate the transposed data and output
		foreach($transposed as $name => $boxes) {
			$page->setVar('stat_item_block', '');

			$page->setVar(array(
				'WORK_STAT_CLASS' => $name,
				'WORK_STAT_TITLE' => isset($statLabelLookup[$name]) ? $statLabelLookup[$name] : ucfirst($name),
			));

			foreach($boxes as $workId => $value) {
				$page->setVar(array(
					'WORK_NS_ID' => str_replace('-', '][', $workId), // it makes sense, I promise!
					'WORK_STAT_VALUE' => is_numeric($value) ? (float) round($value, 3) : $value // truncate to 3 decimals, then strip extraneous 0s
				));

				if(in_array($name, $writeable)) {
					$page->parse('stat_editable_block', 'stat_editable');
					$page->setVar('stat_readonly_block', '');
				}
				else {
					$page->parse('stat_readonly_block', 'stat_readonly');
					$page->setVar('stat_editable_block', '');
				}

				$page->parse('stat_item_block', 'stat_item', true);
			}
			$page->parse('stat_block', 'stat', true);
		}


		// iterate student list and output rows
		$i = 0;

		foreach($this->studentDataSet as $userId => $row) {
			/** @var $student User */
			$student = $row['student'];

			$page->setVar(array(
				'STUDENT_NAME_FORMAL' => $student->formatName('{lastname}, {firstname}'),
				'STUDENT_NAME' => $student->formatName(),
				'STUDENT_EXTID' => $student->getExternalId(),
				'STUDENT_ID' => $userId,
				'DARK' => ++$i%2 ? 'dark' : '',
				'STUDENT_AVG_GRADIENT' => '7',
				'STUDENT_AVG_CLEAN' => '42'

			));

			$page->setVar('student_work_block', '');

			// iterate the ORDER array, so the columns come out in the correct sequence
			foreach($this->displayOrder as $item) {
				// also, if this is the first pass, render the column headers as well
				if($i == 1) {
					if($item['object'] instanceof OnlineAssessment) {
						$page->setVar(array(
							'WORK_TITLE' => $item['object']->getTitle(),
							'WORK_ID'    => $item['object']->getId(),
							'WORK_PATH'  => '/assess/results',
							'WORK_TYPE'  => $item['object']->getWorkType()->getName()
						));
					}
					elseif($item['object'] instanceof SubmissionBox) {
						$page->setVar(array(
							'WORK_TITLE' => $item['object']->getName(),
							'WORK_ID'    => $item['object']->getId(),
							'WORK_PATH'  => '/submission/viewSubmission',
							'WORK_TYPE'  => $item['object']->getWorkType()->getName()
						));
					}
					$page->parse('work_header_block', 'work_header', true);
				}
				$mark = $row['marks'][$item['isOA']][$item['id']];
				$workId = $item['id'];

				$page->setVar(array(
					'WORK_TYPE' => $item['isOA'] ? 'assess' : 'submission',
					'WORK_ID' => $item['id'],
					'STUDENT_WORK_NORM_MARK' => $mark['norm_mark'],
					'STUDENT_WORK_MARK' => strlen($mark['mark']) ? $mark['mark'] : '&ndash;',
					'STUDENT_WORK_COMMENT' => isset($mark['comment']) ? $mark['comment'] : '',
					'STUDENT_WORK_PATH' => ($item['isOA'] ? "/assess/review/" : "/submission/submissionHistory/") . "$workId/$userId",
				));
				$page->parse('student_work_block', 'student_work', true);

			}

			$page->parse('student_row_block', 'student_row', true);
		}


	}

	/**
	 * Render a CSV and force download
	 */
	public function renderCsv() {

	}
}