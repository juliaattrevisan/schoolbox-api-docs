<?php
/**
 * Evaluation_Import_Xml
 *
 * Import entire Evaluation items including sections and questions
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */
class Evaluation_Import_Xml {
	protected $assessHandler = null;
	protected $sectionHandler = null;
	protected $factory = null;
	protected $page = null;

	/* The homepage to create for
	 */
	protected $categoryId = null;

	/* Temp filename to load from
	 */
	protected $data = '';

	/* Resultant assess id
	 */
	protected $assess_id = null;

	/**
	 * Constructor
	 *
	 * @param $categoryId
	 * @param $assessHandler
	 * @param $sectionHandler
	 * @param $factory
	 * @param $data
	 * @param $page
	 */
	public function __construct($categoryId, $assessHandler, $sectionHandler, $factory, $data, $page) {
		$this->setCategoryId($categoryId);
		$this->setAssessHandler($assessHandler);
		$this->setSectionHandler($sectionHandler);
		$this->setFactory($factory);
		$this->setData($data);
		$this->setPage($page);
	}

	/**
	 * Load data from source
	 *
	 * @return SimpleXMLElement
	 */
	private function loadData() {
		//
		// Try once to load this.
		// If that fails - decode then load.
		$use_errors = libxml_use_internal_errors(true);
		$result = simplexml_load_string($this->getData());
		if (!($result instanceof SimpleXMLElement)) {
			$result = simplexml_load_string(html_entity_decode($this->getData(), ENT_QUOTES, 'UTF-8'));
		}
		libxml_use_internal_errors($use_errors);
		return $result;
	}

	/**
	 * Import data
	 */
	public function import() {
		$xml = $this->loadData();

		//
		// Try to get max attempts if it's there, otherwise fall back to
		// allowresit (not allow_resit).
		// if allowresit is true, or is not unspecified:
		// max_attempts = 0 (unlimited), otherwise 1.
		$max_attempts = Scrub::text($xml->maxattempts);
		if (strlen($max_attempts) === 0) {
			$allow_resit = Scrub::text($xml->allowresit);
			$max_attempts = ($allow_resit || strlen($allow_resit) === 0) ? 0 : 1;
		}
		$assess_id = $this->getAssessHandler()->create(array(
			'category_id' => Scrub::text($this->getCategoryId()),
			'user_id' => $_SESSION['id'],
			'title' => html_entity_decode(Scrub::text($xml->title)),
			'description' => html_entity_decode(Scrub::text($xml->description)),
			'start' => Scrub::text($xml->start),
			'finish' => Scrub::text($xml->finish),
			'duration' => Scrub::text($xml->duration),
			'max_attempts' => $max_attempts,
			'availability' => Scrub::text($xml->availability),
			'created_at' => 'NOW()',
		));

		if (!$assess_id) {
			error(112);
		}

		$this->setAssessId($assess_id);

		/* Process each section in the assess
		 */
		for ($i=0; $i < count($xml->section); $i++) {
			$currentSection = $xml->section[$i];

			$section_id = $this->getSectionHandler()->create(array(
				'section_id' => '',
				'assess_id' => $assess_id,
				'sequence' => $i+1,
				'title' => $currentSection->title,
				'description' => $currentSection->description,
				'random' => $currentSection->random,
				'pool' => $currentSection->pool,
			));

			/* Process each question in the section
			 */
			for ($j=0; $j < count($currentSection->question); $j++) {
				$currentQuestion = $currentSection->question[$j];

				$questionHandler = $this->getFactory()->createQuestionModel(Scrub::text($currentQuestion->typeid));
				$answerHandler = $this->getFactory()->createAnswerModel(Scrub::text($currentQuestion->typeid));

				$question_id = $questionHandler->create(array(
					'question_id' => '',
					'section_id' => $section_id,
					'sequence' => $j+1,
					'type_id' => $currentQuestion->typeid,
					'marks' => $currentQuestion->marks,
					'question' => trim((string) $currentQuestion->questiontext->text),
				));

				if (method_exists($answerHandler, 'importMoodleAnswer')) {
					$answer_id = $answerHandler->importMoodleAnswer($currentQuestion->answers, $question_id);
				}
			}
		}
	}

	/* Getters/Setters
	 */

	/**
	 * Set section handler
	 *
	 * @param $sectionHandler
	 */
	protected function setSectionHandler($sectionHandler) {
		$this->sectionHandler = $sectionHandler;
	}

	/**
	 * Get section handler
	 *
	 * @return null
	 */
	protected function getSectionHandler() {
		return $this->sectionHandler;
	}

	/**
	 * Set factory
	 *
	 * @param $factory
	 */
	protected function setFactory($factory) {
		$this->factory = $factory;
	}

	/**
	 * Get factory
	 *
	 * @return null
	 */
	protected function getFactory() {
		return $this->factory;
	}

	/**
	 * Set page
	 *
	 * @param $page
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
	 * Set assess handler
	 *
	 * @param $assessHandler
	 */
	protected function setAssessHandler($assessHandler) {
		$this->assessHandler = $assessHandler;
	}

	/**
	 * Get assess handler
	 *
	 * @return null
	 */
	protected function getAssessHandler() {
		return $this->assessHandler;
	}

	/**
	 * Set data
	 *
	 * @param $data
	 */
	protected function setData($data) {
		$this->data = $data;
	}

	/**
	 * Get data
	 *
	 * @return string
	 */
	protected function getData() {
		return $this->data;
	}

	/**
	 * Set category id
	 *
	 * @param $categoryId
	 */
	protected function setCategoryId($categoryId) {
		$this->categoryId = $categoryId;
	}

	/**
	 * Get category id
	 *
	 * @return null
	 */
	protected function getCategoryId() {
		return $this->categoryId;
	}

	/**
	 * Set assess id
	 *
	 * @param $assess_id
	 */
	protected function setAssessId($assess_id) {
		$this->assess_id = $assess_id;
	}

	/**
	 * Get assess id
	 *
	 * @return null
	 */
	public function getAssessId() {
		return $this->assess_id;
	}
}
