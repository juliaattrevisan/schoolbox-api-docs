<?php
/**
 * Evaluation_Export_Xml
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */
abstract class Evaluation_Export_Xml {
	protected $assess = array();
	protected $sectionHandler = null;
	protected $factory = null;
	protected $page = null;

	/* All the sections to iterate through to render the xml
	 */
	protected $allSections = array();

	protected $evaluationType = '';
	protected $templateFile = '';

	/**
	 * Constructor
	 *
	 * @param $assess
	 * @param $factory
	 * @param $sectionHandler
	 * @param $page
	 */
	public function __construct($assess, $factory, $sectionHandler, $page) {
		$this->setAssess($assess);
		$this->setSectionHandler($sectionHandler);
		$this->setFactory($factory);
		$this->setPage($page);

		/* Collection to iterate through
		 */
		$this->setAllSections($this->getSectionHandler()->getList($assess['assess_id']));

		/* Setup blocks and content and stuff
		 */
		$this->setup();
	}

	/**
	 * Set up xml content
	 */
	protected function setup() {

		/* Set up template
		 */
		$this->getPage()->setFile('xml', $this->getTemplateFile());
		$this->getPage()->setBlock('xml', 'section', 'section_block');
		$this->getPage()->setBlock('section', 'answerInputs', 'answerInputs_block');
		$this->getPage()->setBlock('section', 'question', 'question_block');

		$assess = $this->getAssess();

		$this->getPage()->setVar(array(
			'ASSESS_TITLE' => htmlspecialchars($assess['title'], ENT_QUOTES),
			'ASSESS_DESCRIPTION' => Scrub::HTML($assess['description']),
			'ASSESS_START' => $assess['start'],
			'ASSESS_FINISH' => $assess['finish'],
			'ASSESS_DURATION' => $assess['duration'],
			'ASSESS_MAX_ATTEMPTS' => $assess['max_attempts'],
			'ASSESS_AVAILABILITY' => $assess['availability'],
		));

		if (is_array($this->getAllSections()) && count($this->getAllSections())) {
			foreach($this->getAllSections() as $currentSection) {
				$this->getPage()->setVar(array(
					'SECTION_SECTION_ID' => $currentSection['section_id'],
					'SECTION_TITLE' => htmlspecialchars($currentSection['title'], ENT_QUOTES),
					'SECTION_DESCRIPTION' => htmlspecialchars($currentSection['description'], ENT_QUOTES),
					'SECTION_RANDOM' => $currentSection['random'],
					'SECTION_POOL' => $currentSection['pool'],
				));

				$allQuestions = $this->getSectionHandler()->getContents($assess['assess_id'], $currentSection['section_id']);

				if ($allQuestions) {
					foreach($allQuestions as $currentQuestion) {

						/* This if() clause allows for sections containing no questions.
						 * because they come out with getContents()
						 */
						if ($currentQuestion['type_id']) {
							$answerHandler = $this->getFactory()->createAnswerModel($currentQuestion['type_id']);

							/* Load answer template into answerOption block
							 */
							$this->getPage()->setFile('answerFormFragment', $this->getEvaluationType() . '/answer/' . $this->getFactory()->getQuestionType($currentQuestion['type_id']) . '/exportToMoodle.html');
							$this->getPage()->setBlock('answerFormFragment', 'answerOption', 'answerOption_block');

							/* Reset answer options from previous iteration
							 */
							$this->getPage()->setVar('answerOption_block', '');

							$this->getPage()->setVar(array(
								'QUESTION_QUESTION' => Scrub::Html($currentQuestion['question']),
								'QUESTION_MARKS' => $currentQuestion['marks'],
								'QUESTION_TYPE_ID' => $currentQuestion['type_id'],
								'QUESTION_TYPE_MOODLE' => $this->getFactory()->getMoodleQuestionType($currentQuestion['type_id']),
							));

							/* Load individual answers
							 */
							$answerHandler->exportMoodleAnswer($this->getPage(), $currentQuestion['question_id']);

							$this->getPage()->parse('answerInputs_block', 'answerFormFragment', true);
							$this->getPage()->parse('question_block', 'question', true);

							$this->getPage()->setVar('answerFormFragment', '');
							$this->getPage()->setVar('answerInputs_block', '');
						}
					}
				}
				else {
					$this->getPage()->setVar('question_block', '');
				}

				$this->getPage()->parse('section_block', 'section', true);
				$this->getPage()->setVar('question_block', '');
			}
		}
	}

	/**
	 * Render headers and xml data
	 */
	public function render() {
		header('Content-type: text/xml');
		header('Content-Disposition: attachment; filename="' . $this->generateFilename() . '"');

		$this->getPage()->template->pparse('finaloutput', 'xml');

		/* Prevent other template crap from rendering ;)
		 */
		die();
	}

	/**
	 * Generate a filename for the exported xml
	 *
	 * @return string
	 */
	protected function generateFilename() {
		$assess = $this->getAssess();

		$filename = preg_replace('/[^a-zA-Z0-9\-\_]/', '_', $assess['title']) . '-' . date('YmdHis') . '.xml';

		return $filename;
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
	 * Set assess
	 *
	 * @param $assess
	 */
	protected function setAssess($assess) {
		$this->assess = $assess;
	}

	/**
	 * Get assess
	 *
	 * @return array
	 */
	protected function getAssess() {
		return $this->assess;
	}

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
	 * Get template file
	 *
	 * @return string
	 */
	protected function getTemplateFile() {
		return $this->templateFile;
	}

	/**
	 * Get evaluation type
	 *
	 * @return string
	 */
	protected function getEvaluationType() {
		return $this->evaluationType;
	}

	/**
	 * Set all sections
	 *
	 * @param $allSections
	 */
	protected function setAllSections($allSections) {
		$this->allSections = $allSections;
	}

	/**
	 * Get all sections
	 *
	 * @return array
	 */
	protected function getAllSections() {
		return $this->allSections;
	}
}
