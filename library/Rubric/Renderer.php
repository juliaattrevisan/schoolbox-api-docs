<?php
/**
 * Renderer
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
namespace Rubric;

class Renderer {

	/**
	 * An instance of sitepage
	 *
	 * @var null
	 */
	private $page = null;

	/**
	 * An instance of Schoolbox\User
	 *
	 * @var null
	 */
	private $user = null;

	/**
	 * An array, born of rubric handler
	 *
	 * @var array
	 */
	private $rubric = array();

	/**
	 * An instance of rubric handler
	 *
	 * @var null
	 */
	private $rubricHandler = null;

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

		$this->setRubricHandler(new \DueWork_Rubric_Handler());
		$this->setRubric($this->getRubricHandler()->getRubric($submissionBox['id']));

		$this->setup();
	}

	/**
	 * Set up blocks and stuff
	 */
	private function setup() {
		$this->getPage()->setBlock(TEMPLATE_content, 'mark_type_rubric', 'mark_type_rubric_block');
		$this->getPage()->setBlock('mark_type_rubric', 'rubric_rating', 'rubric_rating_block');
		$this->getPage()->setBlock('mark_type_rubric', 'rubric_crit', 'rubric_crit_block');
		$this->getPage()->setBlock('rubric_crit', 'rubric_crit_rating', 'rubric_crit_rating_block');
		$this->getPage()->setBlock('rubric_crit', 'rubric_grouping', 'rubric_grouping_block');
	}

	/**
	 * Draw cube
	 */
	public function render() {
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
			// display a grouping row
			if($i == 0 || $data['criteria'][$i]['group'] != $data['criteria'][$i - 1]['group']) {
				$this->getPage()->setVar(array(
					'RUBRIC_GROUP_SPAN' => $colCount + 1,
					'RUBRIC_GROUP_LABEL' => $data['criteria'][$i]['group']
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

			for($j = 0; $j < count($data['criteria'][0]['results']); ++$j) {
				$this->getPage()->setVar(array(
					'RUBRIC_CRIT_RATING_LABEL' => $data['criteria'][$i]['results'][$j],
					'RUBRIC_CRIT_RATING_VALUE' => $j,
					'RUBRIC_CRIT_RATING_CLASS' => 'lol',
					'RUBRIC_CRIT_RATING_SELECTED' => $data['criteria'][$i]['value'] == $j ? 'checked="checked"' : '',
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
			$max += count($data['criteria'][0]['results']) - 1;
		}

		$this->getPage()->setVar(array(
			'RUBRIC_GRADE_SUM' => $sum,
			'RUBRIC_GRADE_MAX' => $max
		));

		return $this;
	}

	/**
	 * An additional step, to retain compatibility with returnWork.php
	 */
	public function parseBlock() {
		$this->getPage()->parse("mark_type_rubric_block", "mark_type_rubric");
	}

	/* Getters/Setters
	 */

	/**
	 * Set page
	 *
	 * @param null $page
	 */
	private function setPage($page) {
		$this->page = $page;
	}

	/**
	 * Get page
	 *
	 * @return null
	 */
	private function getPage() {
		return $this->page;
	}

	/**
	 * Set user
	 *
	 * @param array $user
	 */
	private function setUser($user) {
		$this->user = $user;
	}

	/**
	 * Get user
	 *
	 * @return array
	 */
	private function getUser() {
		return $this->user;
	}

	/**
	 * Set rubric handler
	 *
	 * @param null $rubricHandler
	 */
	private function setRubricHandler($rubricHandler) {
		$this->rubricHandler = $rubricHandler;
	}

	/**
	 * Get rubric handler
	 *
	 * @return null
	 */
	private function getRubricHandler() {
		return $this->rubricHandler;
	}

	/**
	 * Set rubric
	 *
	 * @param array $rubric
	 */
	private function setRubric($rubric) {
		$this->rubric = $rubric;
	}

	/**
	 * Get rubric
	 *
	 * @return array
	 */
	private function getRubric() {
		return $this->rubric;
	}
}