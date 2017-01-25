<?php
/**
 * Evaluation_Import_Xml
 *
 * Import entire Evaluation items including sections, questions and solutions
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */
abstract class Evaluation_Import_Xml
{
    /** @var Evaluation_Main */
	protected $assessHandler;

	/** @var Evaluation_Section */
	protected $sectionHandler;

	/** @var Evaluation_Factory */
	protected $factory;

	/** @var SitePage */
	protected $page;

    /**
     * The homepage to create for
     *
     * @var int
     */
	protected $categoryId;

	/**
     * File contents send from browser
     *
     * @var string
     */
	protected $data;

    /**
     * Newly assigned assess_id
     *
     * @var int
     */
	protected $assess_id;

	/**
	 * The title of the Evaluation being imported
	 *
	 * @var string
	 */
	protected $title;

	/**
	 * Constructor
	 *
	 * @param int $categoryId
	 * @param Evaluation_Main $assessHandler
	 * @param Evaluation_Section $sectionHandler
	 * @param Evaluation_Factory $factory
	 * @param string $data
	 * @param SitePage $page
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
	 * Returns whether this Evaluation can count towards final year mark
	 *
	 * @return boolean
	 */
	public abstract function hasWeighting();

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
		$max_attempts = (string)$xml->maxattempts;
		if (strlen($max_attempts) === 0) {
			$allow_resit  = (string)$xml->allowresit;
			$max_attempts = ($allow_resit || strlen($allow_resit) === 0) ? 0 : 1;
		}

		// Build description and complete message
		$description = $this->parseRichTextNode($xml, 'description');
		$completionMessage = $this->parseRichTextNode($xml, 'completionmessage');

		/* Remember the title, for the course builder
		 */
		$this->setTitle((string)$xml->title);

		$dataArr = array(
			'category_id' => $this->getCategoryId(),
			'user_id' => $_SESSION['id'],
			'title' => $this->getTitle(),
			'description' => $description,
			'completion_message' => $completionMessage,
			'start' => $xml->start,
			'finish' => $xml->finish,
			'duration' => $xml->duration,
			'max_attempts' => $max_attempts,
			'availability' => $xml->availability,
			'mark_visibility' => $xml->markvisibility,
			'created_at' => 'NOW()',
		);

		if($this->hasWeighting()){
			$dataArr['weight'] = $xml->weight ?: 0;
		}

		$assess_id = $this->getAssessHandler()->create($dataArr);

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
				'description' => trim((string) $currentSection->description->text) ? trim((string) $currentSection->description->text) : $currentSection->description,
				'random' => $currentSection->random,
				'pool' => $currentSection->pool,
			));

			/* Process each question in the section
			 */
			for ($j=0; $j < count($currentSection->question); $j++) {
				$currentQuestion = $currentSection->question[$j];

				$questionHandler = $this->getFactory()->createQuestionModel((string)$currentQuestion->typeid);
				$answerHandler = $this->getFactory()->createAnswerModel((string)$currentQuestion->typeid);

				/* Only json-encode if there are config elements
				 *
				 * When you cast the std obj to an array, you can possibly
				 * end up with an empty config if the tags are separated
				 */
				if (count(array_keys((array)$currentQuestion->config))) {
					$config = json_encode((array)$currentQuestion->config);
				}
				else {

					/* -_-
					 */
					$config = 'NULL';
				}

				$question_id = $questionHandler->create(array(
					'question_id' => '',
					'section_id' => $section_id,
					'sequence' => $j+1,
					'type_id' => $currentQuestion->typeid,
					'marks' => $currentQuestion->marks,
					'question' => trim((string) $currentQuestion->questiontext->text),
					'config' => $config,
				));

				if (method_exists($answerHandler, 'importMoodleAnswer')) {
					$answer_id = $answerHandler->importMoodleAnswer($currentQuestion->answers, $question_id);
				}
			}
		}
		return $assess_id;
	}

	/**
	 * Parses a rich text node
	 *
	 * @param SimpleXMLElement $xml
	 * @param string           $nodename
	 *
	 * @return string
	 */
	protected function parseRichTextNode(SimpleXMLElement $xml, $nodename)
	{
		// Value should just be a single node, but maybe it wasn't wrapped in
		// <![CDATA[ ]]> tags.
		$value = trim("{$xml->$nodename}");
		if (!$xml->$nodename) {
			foreach ($xml->xpath("/quiz/$nodename/*") as $desc_node) {
				$value .= $desc_node->asXML();
			};
		}
		return $value;
	}

	/* Getters/Setters
	 */

	/**
	 * Set section handler
	 *
	 * @param Evaluation_Section $sectionHandler
	 */
	protected function setSectionHandler($sectionHandler) {
		$this->sectionHandler = $sectionHandler;
	}

	/**
	 * Get section handler
	 *
	 * @return Evaluation_Section
	 */
	protected function getSectionHandler() {
		return $this->sectionHandler;
	}

	/**
	 * @param Evaluation_Factory $factory
	 */
	protected function setFactory($factory) {
		$this->factory = $factory;
	}

	/**
	 * @return Evaluation_Factory
	 */
	protected function getFactory() {
		return $this->factory;
	}

	/**
	 * @param SitePage $page
	 */
	protected function setPage($page) {
		$this->page = $page;
	}

	/**
	 * @return SitePage
	 */
	protected function getPage() {
		return $this->page;
	}

	/**
	 * @param Evaluation_Main $assessHandler
	 */
	protected function setAssessHandler($assessHandler) {
		$this->assessHandler = $assessHandler;
	}

	/**
	 * @return Evaluation_Main
	 */
	protected function getAssessHandler() {
		return $this->assessHandler;
	}

	/**
	 * @param string $data
	 */
	protected function setData($data) {
		$this->data = $data;
	}

	/**
	 * @return string
	 */
	protected function getData() {
		return $this->data;
	}

	/**
	 * @param int $categoryId
	 */
	protected function setCategoryId($categoryId) {
		$this->categoryId = $categoryId;
	}

	/**
	 * @return int
	 */
	protected function getCategoryId() {
		return $this->categoryId;
	}

	/**
	 * @param int $assess_id
	 */
	protected function setAssessId($assess_id) {
		$this->assess_id = $assess_id;
	}

	/**
	 * @return int
	 */
	public function getAssessId() {
		return $this->assess_id;
	}

	/**
	 * @param string $title
	 */
	protected function setTitle($title) {
		$this->title = $title;
	}

	/**
	 * @return string
	 */
	public function getTitle() {
		return $this->title;
	}
}
