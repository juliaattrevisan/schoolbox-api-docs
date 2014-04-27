<?php
/**
 * Renderer
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
namespace Rubric\Renderer;

abstract class Base {

	/**
	 * An instance of sitepage
	 *
	 * @var null
	 */
	protected $page = null;

	/**
	 * An instance of Schoolbox\User
	 *
	 * @var null
	 */
	protected $user = null;

	/**
	 * A submission box, born of submission handler
	 *
	 * @var array
	 */
	protected $submissionBox = array();

	/**
	 * An array, born of rubric handler
	 *
	 * @var array
	 */
	protected $rubric = array();

	/**
	 * An instance of rubric handler
	 *
	 * @var null
	 */
	protected $rubricHandler = null;

	/**
	 * Constructor
	 *
	 * @param $page
	 * @param $submissionBox
	 * @param $user
	 */
	public function __construct($page, $submissionBox, $user) {
		$this->setPage($page);
		$this->setUser($user);
		$this->setSubmissionBox($submissionBox);

		$this->setRubricHandler(new \DueWork_Rubric_Handler());

		if ($this->getSubmissionBox()->isRubric()) {
			$this->setRubric($this->getRubricHandler()->getRubric($submissionBox->getId()));
		}

		$this->setup();
		$this->setupCommonBlocks();
	}

	/**
	 * Set up scheme-specific blocks and stuff
	 *
	 * Horizontal and vertical have different contexts and thus different
	 * phplib settings
s	 */
	abstract protected function setup();

	/**
	 * Blocks common to both vertical and horizontal scheme
	 */
	protected function setupCommonBlocks() {
		$this->getPage()->setBlock('mark_type_rubric', 'rubric_rating', 'rubric_rating_block');
		$this->getPage()->setBlock('mark_type_rubric', 'rubric_crit', 'rubric_crit_block');
		$this->getPage()->setBlock('rubric_crit', 'rubric_crit_rating', 'rubric_crit_rating_block');
		$this->getPage()->setBlock('rubric_crit', 'rubric_grouping', 'rubric_grouping_block');
	}

	/**
	 * Draw cube
	 */
	public function render() {
		if ($this->getSubmissionBox()->isRubric()) {
			$rubricMark = new \DueWork_Rubric_Mark($this->getRubric());
			$rubricMark->populateMarks($this->getUser()->getId());

			$data = $rubricMark->toArray();

			$json = array();
			$legs = array();

			for($i = 0; $i < count($data['criteria']); ++$i) {
				$json[] = array($i, $data['criteria'][$i]['value'] < 0 ? 0 : $data['criteria'][$i]['value']);
				$legs[] = array('label' => $data['criteria'][$i]['label']);
			}

			$this->getPage()->setVar(array(
				'GRAPH_DATA' => json_encode($json),
				'GRAPH_LEGS' => json_encode($legs),
				'GRAPH_MAX'  => count($data['criteria'][0]['results']) - 1
			));

			$colCount = count($data['criteria'][0]['results']);

			for($i = 0; $i < $colCount; ++$i) {
				$this->getPage()->parse('rubric_rating_block', 'rubric_rating', true);
			}

			for($i = 0; $i < count($data['criteria']); ++$i) {

				/* Render a group value so all the criterion labels are tied to the
				 * criteria themselves on the frontend, regardless of html structure
				 */
				$this->getPage()->setVar(array(
					'RUBRIC_GROUP_CLASS' => 'criteria-group-' . $i,
				));

				// display a grouping row
				if($i == 0 || $data['criteria'][$i]['group'] != $data['criteria'][$i - 1]['group']) {
					$this->getPage()->setVar(array(
						'RUBRIC_GROUP_SPAN' => $colCount + 1,
						'RUBRIC_GROUP_LABEL' => $data['criteria'][$i]['group'],
					));

					$this->getPage()->parse('rubric_grouping_block','rubric_grouping', false);
				}
				else {
					$this->getPage()->setVar('rubric_grouping_block', '');
				}

				$this->getPage()->setVar(array(
					'rubric_crit_rating_block' => '',
					'RUBRIC_CRIT_LABEL' => $data['criteria'][$i]['label'],
					'RUBRIC_CRIT_NAME' => preg_replace("#[^a-z0-9\-]#is", '', strtolower($data['criteria'][$i]['label'])),
					'RUBRIC_CRIT_INDEX' => $i,
					'RUBRIC_CRIT_ID' => $data['criteria'][$i]['id']
				));

				foreach($data['criteria'][$i]['results'] as $value => $descriptor) {
					$this->getPage()->setVar(array(
						'RUBRIC_CRIT_RATING_LABEL' => $descriptor,
						'RUBRIC_CRIT_RATING_VALUE' => $value,
						'RUBRIC_CRIT_RATING_SELECTED' => $data['criteria'][$i]['value'] == $value ? 'checked="checked"' : '',

						/* Render a group value so all the criterion labels are tied to the
						 * criteria themselves on the frontend, regardless of html structure
						 */
						'RUBRIC_CRIT_RATING_CLASS' => 'criteria-group-' . $i,
					));
					$this->getPage()->parse('rubric_crit_rating_block', 'rubric_crit_rating', true);
				}

				$this->getPage()->parse('rubric_crit_block', 'rubric_crit', true);
			}

			// calculate scores
			$sum = 0;
			$max = 0;

			for($i = 0; $i < count($data['criteria']); ++$i) {
				$sum += $data['criteria'][$i]['value'];
				$max += max(array_keys($data['criteria'][$i]['results']));
			}

			$this->getPage()->setVar(array(
				'RUBRIC_GRADE_SUM' => $sum,
				'RUBRIC_GRADE_MAX' => $max
			));
		}

		return $this;
	}

	/**
	 * An additional step, to retain compatibility with returnWork.php
	 */
	public function parseBlock() {
		if ($this->getSubmissionBox()->isRubric()) {
			$this->getPage()->parse("mark_type_rubric_block", "mark_type_rubric");
			$this->getPage()->parse('INJECTED_MARK_RUBRIC', 'rubric');
		}
		else {
			$this->getPage()->setVar(array(
				'mark_type_rubric_block' => '',
				'INJECTED_MARK_RUBRIC' => ''
			));
		}
	}

	/* Getters/Setters
	 */

	/**
	 * Set page
	 *
	 * @param null $page
	 */
	protected function setPage($page) {
		$this->page = $page;
	}

	/**
	 * Get page
	 *
	 * @return null
	 */
	protected function getPage() {
		return $this->page;
	}

	/**
	 * Set user
	 *
	 * @param array $user
	 */
	protected function setUser($user) {
		$this->user = $user;
	}

	/**
	 * Get user
	 *
	 * @return array
	 */
	protected function getUser() {
		return $this->user;
	}

	/**
	 * Set rubric handler
	 *
	 * @param null $rubricHandler
	 */
	protected function setRubricHandler($rubricHandler) {
		$this->rubricHandler = $rubricHandler;
	}

	/**
	 * Get rubric handler
	 *
	 * @return null
	 */
	protected function getRubricHandler() {
		return $this->rubricHandler;
	}

	/**
	 * Set rubric
	 *
	 * @param array $rubric
	 */
	protected function setRubric($rubric) {
		$this->rubric = $rubric;
	}

	/**
	 * Get rubric
	 *
	 * @return array
	 */
	protected function getRubric() {
		return $this->rubric;
	}

	/**
	 * Set submission box
	 *
	 * @param array $submissionBox
	 */
	protected function setSubmissionBox($submissionBox) {
		$this->submissionBox = $submissionBox;
	}

	/**
	 * Get submission box
	 *
	 * @return array
	 */
	protected function getSubmissionBox() {
		return $this->submissionBox;
	}
}